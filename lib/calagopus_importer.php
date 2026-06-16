<?php

/**
 * Calagopus Pterodactyl Importer
 *
 * Migrates packages and services from the official Pterodactyl module to the
 * Calagopus module. Nests, eggs, and locations are matched by name between the
 * two panels, and servers are matched by their external ID (falling back to
 * the server UUID translated through the old Pterodactyl API).
 *
 * @package blesta
 * @subpackage blesta.components.modules.calagopus.lib
 * @copyright Copyright (c) 2024, Calagopus
 * @link https://calagopus.com Calagopus
 */
class CalagopusImporter
{
    /**
     * @var array A cache of API clients keyed by module row ID
     */
    private $apis = [];

    /**
     * @var array A cache of panel catalogs (nests/eggs/locations) keyed by module row ID
     */
    private $catalogs = [];

    /**
     * @var array A list of client IDs whose panel username has been copied
     */
    private $migrated_clients = [];

    /**
     * Initialize
     */
    public function __construct()
    {
        Loader::loadComponents($this, ['Record', 'Input']);
        Loader::loadModels($this, ['ModuleManager', 'ModuleClientMeta']);
        Loader::load(dirname(__FILE__) . DS . 'calagopus_api.php');
    }

    /**
     * Retrieves a list of Input errors, if any
     *
     * @return mixed An array of errors or false if none
     */
    public function errors()
    {
        return $this->Input->errors();
    }

    /**
     * Gets the installed Pterodactyl module for the current company, if any.
     *
     * @return stdClass|null The Pterodactyl module, or null if not installed
     */
    public function getPterodactylModule()
    {
        $modules = $this->ModuleManager->getByClass('pterodactyl', Configure::get('Blesta.company_id'));

        return $modules[0] ?? null;
    }

    /**
     * Counts the packages and services currently assigned to the Pterodactyl module.
     *
     * @param int $pterodactyl_module_id The ID of the Pterodactyl module
     * @return array An array containing 'packages' and 'services' counts
     */
    public function getCounts($pterodactyl_module_id)
    {
        $packages = $this->Record->select(['packages.id'])
            ->from('packages')
            ->where('packages.module_id', '=', $pterodactyl_module_id)
            ->numResults();

        $services = $this->Record->select(['services.id'])
            ->from('services')
            ->innerJoin('package_pricing', 'package_pricing.id', '=', 'services.pricing_id', false)
            ->innerJoin('packages', 'packages.id', '=', 'package_pricing.package_id', false)
            ->where('packages.module_id', '=', $pterodactyl_module_id)
            ->numResults();

        return ['packages' => $packages, 'services' => $services];
    }

    /**
     * Imports all packages and services from the Pterodactyl module. Sets Input
     * errors on fatal failure.
     *
     * @param stdClass $calagopus_module The Calagopus module (with ID) to import into
     * @param array $row_map An array mapping Pterodactyl module row IDs to
     *  Calagopus module row IDs. Rows mapped to an empty value are skipped.
     * @return array|null A results array containing counts and notes, or null on fatal error
     */
    public function import($calagopus_module, array $row_map)
    {
        $results = [
            'packages' => 0,
            'packages_skipped' => 0,
            'services' => 0,
            'services_unmatched' => 0,
            'notes' => []
        ];

        $pterodactyl_module = $this->getPterodactylModule();
        if (!$pterodactyl_module) {
            $this->Input->setErrors(
                ['import' => ['module' => Language::_('Calagopus.!error.import.module', true)]]
            );
            return null;
        }

        // Index the module rows on both sides
        $pterodactyl_rows = [];
        foreach ($this->ModuleManager->getRows($pterodactyl_module->id) as $row) {
            $pterodactyl_rows[$row->id] = $row;
        }
        $calagopus_rows = [];
        foreach ($this->ModuleManager->getRows($calagopus_module->id) as $row) {
            $calagopus_rows[$row->id] = $row;
        }

        // Build the effective row mapping
        $map = [];
        foreach ($row_map as $pterodactyl_row_id => $calagopus_row_id) {
            if ($calagopus_row_id !== ''
                && isset($pterodactyl_rows[$pterodactyl_row_id])
                && isset($calagopus_rows[$calagopus_row_id])
            ) {
                $map[$pterodactyl_row_id] = $calagopus_row_id;
            }
        }

        if (empty($map)) {
            $this->Input->setErrors(
                ['import' => ['row_map' => Language::_('Calagopus.!error.import.row_map', true)]]
            );
            return null;
        }

        // Fetch all Pterodactyl packages
        $packages = $this->Record->select(['packages.id', 'packages.module_row', 'packages.module_group'])
            ->from('packages')
            ->where('packages.module_id', '=', $pterodactyl_module->id)
            ->fetchAll();

        try {
            $this->Record->begin();

            foreach ($packages as $package) {
                // Resolve group-deployed packages to the first mapped row in the group
                $pterodactyl_row_id = $package->module_row;
                if (empty($pterodactyl_row_id) && !empty($package->module_group)) {
                    $group = $this->ModuleManager->getGroup($package->module_group);
                    foreach (($group->rows ?? []) as $group_row) {
                        if (isset($map[$group_row->id])) {
                            $pterodactyl_row_id = $group_row->id;
                            break;
                        }
                    }
                }

                if (!isset($map[$pterodactyl_row_id])) {
                    $results['packages_skipped']++;
                    $results['notes'][] = Language::_('Calagopus.import.note_package_unmapped', true, $package->id);
                    continue;
                }

                $pterodactyl_row = $pterodactyl_rows[$pterodactyl_row_id];
                $calagopus_row = $calagopus_rows[$map[$pterodactyl_row_id]];

                if (!$this->importPackage($package, $pterodactyl_row, $calagopus_row, $calagopus_module, $results)) {
                    $results['packages_skipped']++;
                    continue;
                }

                $results['packages']++;
                if (!empty($package->module_group)) {
                    $results['notes'][] = Language::_(
                        'Calagopus.import.note_package_group',
                        true,
                        $package->id
                    );
                }

                // Migrate the services on this package
                $services = $this->Record
                    ->select(['services.id', 'services.client_id', 'services.status'])
                    ->from('services')
                    ->innerJoin('package_pricing', 'package_pricing.id', '=', 'services.pricing_id', false)
                    ->where('package_pricing.package_id', '=', $package->id)
                    ->fetchAll();

                foreach ($services as $service) {
                    $this->importService(
                        $service,
                        $pterodactyl_row,
                        $calagopus_row,
                        $pterodactyl_module->id,
                        $calagopus_module->id,
                        $results
                    );
                    $results['services']++;
                }
            }

            $this->Record->commit();
        } catch (Throwable $e) {
            $this->Record->rollBack();
            $this->Input->setErrors(['import' => ['exception' => $e->getMessage()]]);
            return null;
        }

        return $results;
    }

    /**
     * Converts a single package to the Calagopus module. The caller is expected
     * to wrap this in a transaction.
     *
     * @param stdClass $package The package row (id, module_row, module_group)
     * @param stdClass $pterodactyl_row The Pterodactyl module row it belongs to
     * @param stdClass $calagopus_row The Calagopus module row to assign
     * @param stdClass $calagopus_module The Calagopus module
     * @param array &$results The results array to append notes to
     * @return bool True if the package was converted, false if it was skipped
     */
    private function importPackage($package, $pterodactyl_row, $calagopus_row, $calagopus_module, array &$results)
    {
        // Read the existing meta
        $meta = [];
        $meta_rows = $this->Record->select()
            ->from('package_meta')
            ->where('package_id', '=', $package->id)
            ->fetchAll();
        foreach ($meta_rows as $row) {
            if ($row->encrypted) {
                // The Pterodactyl module does not encrypt package meta; leave any
                // unexpected encrypted values behind rather than corrupt them
                continue;
            }
            $meta[$row->key] = $row->serialized ? unserialize($row->value) : $row->value;
        }

        // Resolve the nest, egg, and location to their Calagopus equivalents by name
        $nest_uuid = $this->matchNest($pterodactyl_row, $calagopus_row, $meta['nest_id'] ?? '');
        $egg_uuid = $nest_uuid
            ? $this->matchEgg($pterodactyl_row, $calagopus_row, $meta['nest_id'] ?? '', $meta['egg_id'] ?? '', $nest_uuid)
            : null;
        $location_uuid = $this->matchLocation($pterodactyl_row, $calagopus_row, $meta['location_id'] ?? '');

        if (!$nest_uuid || !$egg_uuid || !$location_uuid) {
            $unresolved = !$nest_uuid ? 'nest' : (!$egg_uuid ? 'egg' : 'location');
            $results['notes'][] = Language::_(
                'Calagopus.import.note_package_unresolved',
                true,
                $package->id,
                Language::_('Calagopus.import.entity_' . $unresolved, true)
            );
            return false;
        }

        // Convert the meta fields
        $drop = ['nest_id', 'egg_id', 'location_id', 'dedicated_ip', 'port_range', 'pack_id'];
        $rename = [
            'io' => 'io_weight',
            'image' => 'docker_image',
            'startup' => 'startup_command',
            'databases' => 'database_limit',
            'allocations' => 'allocations_limit',
            'backups' => 'backup_limit'
        ];

        $new_meta = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, $drop)) {
                continue;
            }
            $new_meta[isset($rename[$key]) ? $rename[$key] : $key] = $value;
        }

        $new_meta['nest_uuid'] = $nest_uuid;
        $new_meta['egg_uuid'] = $egg_uuid;
        $new_meta['node_uuid'] = '';
        $new_meta['location_uuids'] = [$location_uuid];

        // Apply Calagopus defaults for fields Pterodactyl left blank or doesn't have
        $defaults = [
            'allocations_limit' => '1',
            'database_limit' => '0',
            'backup_limit' => '0',
            'schedule_limit' => '0',
            'memory_overhead' => '0',
            'skip_installer' => '0',
            'start_on_completion' => '1',
            'hugepages_passthrough' => '0',
            'kvm_passthrough' => '0'
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($new_meta[$key]) || $new_meta[$key] === '') {
                $new_meta[$key] = $value;
            }
        }

        // Replace the meta and reassign the package to the Calagopus module
        $this->Record->from('package_meta')
            ->where('package_id', '=', $package->id)
            ->where('encrypted', '=', 0)
            ->delete();
        foreach ($new_meta as $key => $value) {
            $serialize = !is_scalar($value);
            $this->Record->insert('package_meta', [
                'package_id' => $package->id,
                'key' => $key,
                'value' => $serialize ? serialize($value) : $value,
                'serialized' => $serialize ? 1 : 0,
                'encrypted' => 0
            ]);
        }

        $this->Record->where('id', '=', $package->id)->update(
            'packages',
            [
                'module_id' => $calagopus_module->id,
                'module_row' => $calagopus_row->id,
                'module_group' => null
            ],
            ['module_id', 'module_row', 'module_group']
        );

        return true;
    }

    /**
     * Converts a single service's fields to the Calagopus module.
     *
     * @param stdClass $service The service row (id, client_id, status)
     * @param stdClass $pterodactyl_row The Pterodactyl module row
     * @param stdClass $calagopus_row The Calagopus module row
     * @param int $pterodactyl_module_id The Pterodactyl module ID
     * @param int $calagopus_module_id The Calagopus module ID
     * @param array &$results The results array to append notes to
     */
    private function importService(
        $service,
        $pterodactyl_row,
        $calagopus_row,
        $pterodactyl_module_id,
        $calagopus_module_id,
        array &$results
    ) {
        // Read the existing service fields
        $fields = [];
        $field_rows = $this->Record->select()
            ->from('service_fields')
            ->where('service_id', '=', $service->id)
            ->fetchAll();
        foreach ($field_rows as $row) {
            $fields[$row->key] = $row;
        }

        // Locate the server on the Calagopus panel
        $server = $this->findServer($fields, $pterodactyl_row, $calagopus_row);

        // Build the new field set, dropping Pterodactyl-only fields
        $drop = ['server_id', 'server_description', 'server_username', 'server_password'];
        $new_fields = [];
        foreach ($fields as $key => $row) {
            if (in_array($key, $drop)) {
                continue;
            }
            $new_fields[$key] = [
                'value' => $row->value,
                'serialized' => $row->serialized,
                'encrypted' => $row->encrypted
            ];
        }

        $new_fields['server_uuid'] = ['value' => $server->uuid ?? '', 'serialized' => 0, 'encrypted' => 0];
        if ($server) {
            if (!empty($server->external_id)) {
                $new_fields['external_id'] = ['value' => $server->external_id, 'serialized' => 0, 'encrypted' => 0];
            }
            $allocation = $server->allocation ?? null;
            if ($allocation) {
                $new_fields['server_ip'] = [
                    'value' => $allocation->ip_alias ?? ($allocation->ip ?? ''),
                    'serialized' => 0,
                    'encrypted' => 0
                ];
                $new_fields['server_port'] = [
                    'value' => $allocation->port ?? '',
                    'serialized' => 0,
                    'encrypted' => 0
                ];
            }
            if (empty($new_fields['server_name']['value']) && !empty($server->name)) {
                $new_fields['server_name'] = ['value' => $server->name, 'serialized' => 0, 'encrypted' => 0];
            }
        } elseif ($service->status != 'canceled') {
            $results['services_unmatched']++;
            $results['notes'][] = Language::_('Calagopus.import.note_service_unmatched', true, $service->id);
        }

        // Replace the service fields
        $this->Record->from('service_fields')
            ->where('service_id', '=', $service->id)
            ->delete();
        foreach ($new_fields as $key => $field) {
            $this->Record->insert('service_fields', [
                'service_id' => $service->id,
                'key' => $key,
                'value' => $field['value'],
                'serialized' => $field['serialized'],
                'encrypted' => $field['encrypted']
            ]);
        }

        // Copy the tracked panel username for this client
        if (!isset($this->migrated_clients[$service->client_id])) {
            $this->migrated_clients[$service->client_id] = true;
            $username = $this->ModuleClientMeta->get(
                $service->client_id,
                'pterodactyl_username',
                $pterodactyl_module_id
            );
            if ($username && $username->value != '') {
                $this->ModuleClientMeta->set(
                    $service->client_id,
                    $calagopus_module_id,
                    0,
                    [
                        ['key' => 'calagopus_username', 'value' => $username->value, 'encrypted' => 0]
                    ]
                );
            }
        }
    }

    /**
     * Finds the Calagopus server for a set of Pterodactyl service fields. Looks
     * up the server by external ID first, then by the server UUID translated
     * through the old Pterodactyl API.
     *
     * @param array $fields The raw service field rows keyed by field
     * @param stdClass $pterodactyl_row The Pterodactyl module row
     * @param stdClass $calagopus_row The Calagopus module row
     * @return stdClass|null The Calagopus server, or null if not found
     */
    private function findServer(array $fields, $pterodactyl_row, $calagopus_row)
    {
        $calagopus_api = $this->getCalagopusApi($calagopus_row);

        $external_id = $fields['external_id']->value ?? '';
        if ($external_id !== '') {
            $response = $calagopus_api->apiRequest(
                'GET',
                '/api/admin/servers/external/' . rawurlencode($external_id)
            );
            if ($response->status() < 400 && isset($response->response()->server)) {
                return $response->response()->server;
            }
        }

        // Translate the numeric panel ID to a UUID through the old Pterodactyl API
        $server_id = $fields['server_id']->value ?? '';
        if ($server_id !== '') {
            $pterodactyl_api = $this->getPterodactylApi($pterodactyl_row);
            $response = $pterodactyl_api->apiRequest('GET', '/api/application/servers/' . $server_id);
            $uuid = $response->status() < 400 ? ($response->response()->attributes->uuid ?? null) : null;

            if ($uuid) {
                $response = $calagopus_api->apiRequest('GET', '/api/admin/servers/' . $uuid);
                if ($response->status() < 400 && isset($response->response()->server)) {
                    return $response->response()->server;
                }
            }
        }

        return null;
    }

    /**
     * Matches a Pterodactyl nest ID to a Calagopus nest UUID by name.
     *
     * @param stdClass $pterodactyl_row The Pterodactyl module row
     * @param stdClass $calagopus_row The Calagopus module row
     * @param string $nest_id The Pterodactyl nest ID
     * @return string|null The Calagopus nest UUID, or null
     */
    private function matchNest($pterodactyl_row, $calagopus_row, $nest_id)
    {
        if ($nest_id === '') {
            return null;
        }

        $nests = $this->getPterodactylCatalog($pterodactyl_row, 'nests', '/api/application/nests');
        $name = null;
        foreach ((array)$nests as $nest) {
            if (($nest->attributes->id ?? null) == $nest_id) {
                $name = $nest->attributes->name ?? null;
                break;
            }
        }
        if ($name === null) {
            return null;
        }

        $calagopus_nests = $this->getCalagopusCatalog($calagopus_row, 'nests', '/api/admin/nests', 'nests');
        foreach ((array)$calagopus_nests as $nest) {
            if (strcasecmp(trim($nest->name ?? ''), trim($name)) === 0) {
                return $nest->uuid;
            }
        }

        return null;
    }

    /**
     * Matches a Pterodactyl egg ID to a Calagopus egg UUID by name.
     *
     * @param stdClass $pterodactyl_row The Pterodactyl module row
     * @param stdClass $calagopus_row The Calagopus module row
     * @param string $nest_id The Pterodactyl nest ID the egg belongs to
     * @param string $egg_id The Pterodactyl egg ID
     * @param string $nest_uuid The matched Calagopus nest UUID
     * @return string|null The Calagopus egg UUID, or null
     */
    private function matchEgg($pterodactyl_row, $calagopus_row, $nest_id, $egg_id, $nest_uuid)
    {
        if ($egg_id === '') {
            return null;
        }

        $eggs = $this->getPterodactylCatalog(
            $pterodactyl_row,
            'eggs_' . $nest_id,
            '/api/application/nests/' . $nest_id . '/eggs'
        );
        $name = null;
        foreach ((array)$eggs as $egg) {
            if (($egg->attributes->id ?? null) == $egg_id) {
                $name = $egg->attributes->name ?? null;
                break;
            }
        }
        if ($name === null) {
            return null;
        }

        $calagopus_eggs = $this->getCalagopusCatalog(
            $calagopus_row,
            'eggs_' . $nest_uuid,
            '/api/admin/nests/' . $nest_uuid . '/eggs',
            'eggs'
        );
        foreach ((array)$calagopus_eggs as $egg) {
            if (strcasecmp(trim($egg->name ?? ''), trim($name)) === 0) {
                return $egg->uuid;
            }
        }

        return null;
    }

    /**
     * Matches a Pterodactyl location ID to a Calagopus location UUID by name.
     * Both the long and short location names are tried.
     *
     * @param stdClass $pterodactyl_row The Pterodactyl module row
     * @param stdClass $calagopus_row The Calagopus module row
     * @param string $location_id The Pterodactyl location ID
     * @return string|null The Calagopus location UUID, or null
     */
    private function matchLocation($pterodactyl_row, $calagopus_row, $location_id)
    {
        if ($location_id === '') {
            return null;
        }

        $locations = $this->getPterodactylCatalog($pterodactyl_row, 'locations', '/api/application/locations');
        $names = [];
        foreach ((array)$locations as $location) {
            if (($location->attributes->id ?? null) == $location_id) {
                $names = array_filter([
                    trim($location->attributes->long ?? ''),
                    trim($location->attributes->short ?? '')
                ]);
                break;
            }
        }
        if (empty($names)) {
            return null;
        }

        $calagopus_locations = $this->getCalagopusCatalog(
            $calagopus_row,
            'locations',
            '/api/admin/locations',
            'locations'
        );
        foreach ($names as $name) {
            foreach ((array)$calagopus_locations as $location) {
                if (strcasecmp(trim($location->name ?? ''), $name) === 0) {
                    return $location->uuid;
                }
            }
        }

        return null;
    }

    /**
     * Fetches and caches a paginated list from the Pterodactyl application API.
     *
     * @param stdClass $row The Pterodactyl module row
     * @param string $cache_key The cache key for this list
     * @param string $endpoint The API endpoint
     * @return array|null The list of items, or null on failure
     */
    private function getPterodactylCatalog($row, $cache_key, $endpoint)
    {
        $cache_key = 'p' . $row->id . '_' . $cache_key;
        if (array_key_exists($cache_key, $this->catalogs)) {
            return $this->catalogs[$cache_key];
        }

        $api = $this->getPterodactylApi($row);
        $items = [];
        $page = 1;
        do {
            $response = $api->apiRequest('GET', $endpoint, ['page' => $page, 'per_page' => 100]);
            if ($response->status() >= 400 || $response->status() === 0) {
                return $this->catalogs[$cache_key] = null;
            }

            $body = $response->response();
            foreach (($body->data ?? []) as $item) {
                $items[] = $item;
            }

            $total_pages = $body->meta->pagination->total_pages ?? 1;
            $page++;
        } while ($page <= $total_pages && $page <= 100);

        return $this->catalogs[$cache_key] = $items;
    }

    /**
     * Fetches and caches a paginated list from the Calagopus admin API.
     *
     * @param stdClass $row The Calagopus module row
     * @param string $cache_key The cache key for this list
     * @param string $endpoint The API endpoint
     * @param string $wrapper The response key wrapping the list
     * @return array|null The list of items, or null on failure
     */
    private function getCalagopusCatalog($row, $cache_key, $endpoint, $wrapper)
    {
        $cache_key = 'c' . $row->id . '_' . $cache_key;
        if (array_key_exists($cache_key, $this->catalogs)) {
            return $this->catalogs[$cache_key];
        }

        $api = $this->getCalagopusApi($row);
        $items = [];
        $page = 1;
        do {
            $response = $api->apiRequest('GET', $endpoint, ['page' => $page, 'per_page' => 100]);
            if ($response->status() >= 400 || $response->status() === 0) {
                return $this->catalogs[$cache_key] = null;
            }

            $data = (array)($response->response()->{$wrapper}->data ?? []);
            foreach ($data as $item) {
                $items[] = $item;
            }
            $page++;
        } while (count($data) == 100 && $page <= 100);

        return $this->catalogs[$cache_key] = $items;
    }

    /**
     * Returns an API client for the given Pterodactyl module row. The generic
     * Calagopus client is reused since both panels accept a Bearer token with
     * JSON requests.
     *
     * @param stdClass $row The Pterodactyl module row
     * @return CalagopusApi The API client
     */
    private function getPterodactylApi($row)
    {
        $key = 'p' . $row->id;
        if (!isset($this->apis[$key])) {
            $this->apis[$key] = new CalagopusApi(
                $row->meta->host_name,
                $row->meta->application_api_key,
                ($row->meta->use_ssl ?? 'true') == 'true'
            );
        }

        return $this->apis[$key];
    }

    /**
     * Returns an API client for the given Calagopus module row.
     *
     * @param stdClass $row The Calagopus module row
     * @return CalagopusApi The API client
     */
    private function getCalagopusApi($row)
    {
        $key = 'c' . $row->id;
        if (!isset($this->apis[$key])) {
            $this->apis[$key] = new CalagopusApi(
                $row->meta->host_name,
                $row->meta->api_key,
                ($row->meta->use_ssl ?? 'true') == 'true'
            );
        }

        return $this->apis[$key];
    }
}
