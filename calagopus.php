<?php

use Blesta\Core\Util\Validate\Server;
use Blesta\Core\Util\Common\Traits\Container;

/**
 * Calagopus Module
 *
 * Provisions and manages game servers via the Calagopus panel.
 *
 * @package blesta
 * @subpackage blesta.components.modules.calagopus
 * @copyright Copyright (c) 2024, Calagopus
 * @link https://calagopus.com Calagopus
 */
class Calagopus extends Module
{
    // Load traits
    use Container;

    /**
     * @var Blesta\Core\ServiceProviders\Logger Container logger
     */
    private $logger;

    /**
     * Initializes the module
     */
    public function __construct()
    {
        Loader::loadComponents($this, ['Input']);

        Language::loadLang('calagopus', null, dirname(__FILE__) . DS . 'language' . DS);
        Language::loadLang('calagopus_package', null, dirname(__FILE__) . DS . 'language' . DS);
        Language::loadLang('calagopus_service', null, dirname(__FILE__) . DS . 'language' . DS);
        Language::loadLang('calagopus_rule', null, dirname(__FILE__) . DS . 'language' . DS);

        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        Configure::load('calagopus', dirname(__FILE__) . DS . 'config' . DS);

        $this->logger = $this->getFromContainer('logger');
    }

    /**
     * Loads a library class
     *
     * @param string $command The filename of the class to load
     */
    private function loadLib($command)
    {
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . $command . '.php');
    }

    /**
     * Returns an array of available service delegation order methods.
     *
     * @return array An array of order methods in key/value pairs
     */
    public function getGroupOrderOptions()
    {
        return [
            'first' => Language::_('Calagopus.order_options.first', true)
        ];
    }

    /**
     * Determines which module row should be attempted when a service is provisioned.
     *
     * @param int $module_group_id The module group to select from
     * @return int The module row ID to attempt to add the service with
     */
    public function selectModuleRow($module_group_id)
    {
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        $group = $this->ModuleManager->getGroup($module_group_id);

        if ($group) {
            switch ($group->add_order) {
                default:
                case 'first':
                    foreach ($group->rows as $row) {
                        return $row->id;
                    }
                    break;
            }
        }

        return 0;
    }

    /**
     * Sets the active module row to use based on the given package.
     *
     * @param stdClass $package A stdClass object representing the selected package
     */
    private function setModuleRowFromPackage($package)
    {
        if ($package->module_group) {
            $this->setModuleRow($this->getModuleRow($this->selectModuleRow($package->module_group)));
        } else {
            $this->setModuleRow($this->getModuleRow($package->module_row));
        }
    }

    /**
     * Attempts to validate service info. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request (optional)
     * @return bool True if the service validates, false otherwise
     */
    public function validateService($package, array $vars = null)
    {
        return $this->getServiceRules($vars, $package);
    }

    /**
     * Attempts to validate an existing service against a set of service info updates.
     *
     * @param stdClass $service A stdClass object representing the service to validate for editing
     * @param array $vars An array of user-supplied info to satisfy the request (optional)
     * @return bool True if the service update validates, false otherwise
     */
    public function validateServiceEdit($service, array $vars = null)
    {
        $package = $service->package ?? null;
        return $this->getServiceRules($vars, $package, true);
    }

    /**
     * Returns the rule set for adding/editing a service.
     *
     * @param array $vars A list of input vars (optional)
     * @param stdClass $package A stdClass object representing the selected package (optional)
     * @param bool $edit True to get the edit rules, false for the add rules (optional)
     * @return bool True if the service validates, false otherwise
     */
    private function getServiceRules(array $vars = null, $package = null, $edit = false)
    {
        $this->loadLib('calagopus_service');
        $service_helper = new CalagopusService();

        $vars = (array)$vars;
        $egg_variables = [];

        if ($package) {
            $this->setModuleRowFromPackage($package);

            $egg_variables = $this->getEggVariables(
                $package->meta->nest_uuid ?? '',
                $package->meta->egg_uuid ?? ''
            );

            if (!empty($egg_variables)) {
                $defaults = $service_helper->getEnvironmentVariables($vars, $package, $egg_variables);
                foreach ($defaults as $variable) {
                    $key = strtolower($variable['env_variable']);
                    if (!isset($vars[$key])) {
                        $vars[$key] = $variable['value'];
                    }
                }
            }
        }

        $this->Input->setRules($service_helper->getServiceRules($vars, $package, $edit, $egg_variables));
        return $this->Input->validates($vars);
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request (optional)
     * @param stdClass $parent_package A stdClass object representing the parent package (optional)
     * @param stdClass $parent_service A stdClass object representing the parent service (optional)
     * @param string $status The status of the service being added (optional)
     * @return array A numerically indexed array of meta fields to be stored for this service
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        Loader::loadModels($this, ['ModuleClientMeta', 'Clients']);

        $vars = (array)$vars;
        $meta = [];

        $package = $this->getConfigurableOptions($vars, $package);

        $egg_response = $this->apiRequest(
            'GET',
            '/api/admin/nests/' . ($package->meta->nest_uuid ?? '') . '/eggs/' . ($package->meta->egg_uuid ?? '')
        );
        if ($this->Input->errors()) {
            return;
        }
        $egg = $egg_response->egg ?? null;
        $egg_variables = $this->getEggVariables($package->meta->nest_uuid ?? '', $package->meta->egg_uuid ?? '');

        $this->validateService($package, $vars);
        if ($this->Input->errors()) {
            return;
        }

        $this->loadLib('calagopus_service');
        $service_helper = new CalagopusService();

        if (($vars['use_module'] ?? 'true') == 'true') {
            $module = $this->getModule();
            $row = $this->getModuleRow();
            $language = $row->meta->default_language ?? 'en';

            $client = $this->Clients->get($vars['client_id'] ?? null);

            $panel_user = $this->findOrCreateUser($client, $service_helper, $language, $row);
            if ($this->Input->errors() || !$panel_user) {
                return;
            }

            $this->ModuleClientMeta->set(
                $vars['client_id'],
                $module->id,
                0,
                [
                    ['key' => 'calagopus_username', 'value' => $panel_user->username ?? '', 'encrypted' => 0]
                ]
            );

            $vars['external_id'] = 'bl-' . ($vars['client_id'] ?? '0') . '-' . uniqid();

            $payload = $service_helper->addServerParameters($vars, $package, $panel_user, $egg, $egg_variables);

            $node_uuid = trim($package->meta->node_uuid ?? '');
            if ($node_uuid !== '') {
                $alloc_response = $this->apiRequest(
                    'GET',
                    '/api/admin/nodes/' . $node_uuid . '/allocations/available',
                    ['page' => 1, 'per_page' => 10]
                );
                if ($this->Input->errors()) {
                    return;
                }

                $allocations = $alloc_response->allocations->data ?? [];
                if (empty($allocations)) {
                    $this->Input->setErrors(
                        ['allocation' => ['available' => Language::_('Calagopus.!error.allocation.available', true)]]
                    );
                    return;
                }

                $payload['node_uuid'] = $node_uuid;
                $payload['allocation_uuid'] = $allocations[0]->uuid;
                $payload['allocation_uuids'] = [];

                $server_response = $this->apiRequest('POST', '/api/admin/servers', $payload);
            } else {
                $location_uuids = $package->meta->location_uuids ?? [];
                if (is_string($location_uuids)) {
                    $location_uuids = array_filter(array_map('trim', explode(',', $location_uuids)));
                }
                $location_uuids = array_values((array)$location_uuids);

                if (empty($location_uuids)) {
                    $this->Input->setErrors(
                        ['deploy' => ['location' => Language::_('Calagopus.!error.deploy.location', true)]]
                    );
                    return;
                }

                $payload['deployment'] = [
                    'location_uuids' => $location_uuids,
                    'allow_overallocation' => false,
                ];

                $server_response = $this->apiRequest('POST', '/api/admin/servers/deploy', $payload);
            }

            if ($this->Input->errors()) {
                return;
            }

            $server = $server_response->server;
            $vars['server_uuid'] = $server->uuid;
            $vars['external_id'] = $server->external_id ?? $vars['external_id'];

            $allocation = $server->allocation ?? null;
            if ($allocation) {
                $vars['server_ip'] = $allocation->ip_alias ?? ($allocation->ip ?? null);
                $vars['server_port'] = $allocation->port ?? null;
            }
        }

        return $this->getServiceMeta($vars, new stdClass(), $package, $egg_variables, $service_helper);
    }

    /**
     * Edits the service on the remote server. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info to satisfy the request (optional)
     * @param stdClass $parent_package A stdClass object representing the parent package (optional)
     * @param stdClass $parent_service A stdClass object representing the parent service (optional)
     * @return array A numerically indexed array of meta fields to be stored for this service
     */
    public function editService(
        $package,
        $service,
        array $vars = null,
        $parent_package = null,
        $parent_service = null
    ) {
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $vars = (array)$vars;

        $this->validateServiceEdit($service, $vars);
        if ($this->Input->errors()) {
            return;
        }

        $this->loadLib('calagopus_service');
        $service_helper = new CalagopusService();

        $package = $this->getConfigurableOptions($vars, $package);
        $egg_variables = $this->getEggVariables($package->meta->nest_uuid ?? '', $package->meta->egg_uuid ?? '');

        if (($vars['use_module'] ?? 'false') == 'true') {
            $server = $this->getServer($service);
            if (!$server) {
                $this->Input->setErrors(
                    ['server' => ['missing' => Language::_('Calagopus.!error.server.missing', true)]]
                );
                return;
            }

            $update_data = $service_helper->updateServerParameters($package);
            if (!empty($vars['server_name'])) {
                $update_data['name'] = $vars['server_name'];
            }

            $this->apiRequest('PATCH', '/api/admin/servers/' . $server->uuid, $update_data);
            if ($this->Input->errors()) {
                return;
            }

            $variables = $service_helper->getEnvironmentVariables($vars, $package, $egg_variables, $service_fields);
            if (!empty($variables)) {
                $this->apiRequest(
                    'PUT',
                    '/api/admin/servers/' . $server->uuid . '/variables',
                    ['variables' => $variables]
                );
                if ($this->Input->errors()) {
                    return;
                }
            }
        }

        return $this->getServiceMeta($vars, $service_fields, $package, $egg_variables, $service_helper);
    }

    /**
     * Suspends the service on the remote server. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent package (optional)
     * @param stdClass $parent_service A stdClass object representing the parent service (optional)
     * @return mixed null to maintain the existing meta fields
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        $server = $this->getServer($service);

        if ($server) {
            $this->apiRequest('PATCH', '/api/admin/servers/' . $server->uuid, ['suspended' => true]);
        }

        return null;
    }

    /**
     * Unsuspends the service on the remote server. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent package (optional)
     * @param stdClass $parent_service A stdClass object representing the parent service (optional)
     * @return mixed null to maintain the existing meta fields
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        $server = $this->getServer($service);

        if ($server) {
            $this->apiRequest('PATCH', '/api/admin/servers/' . $server->uuid, ['suspended' => false]);
        }

        return null;
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent package (optional)
     * @param stdClass $parent_service A stdClass object representing the parent service (optional)
     * @return mixed null to maintain the existing meta fields
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        $server = $this->getServer($service);

        if ($server) {
            $this->apiRequest('DELETE', '/api/admin/servers/' . $server->uuid, [
                'force' => false,
                'delete_backups' => true,
            ]);
        }

        return null;
    }

    /**
     * Finds or creates a Calagopus panel user for the given client.
     *
     * @param stdClass $client The Blesta client
     * @param CalagopusService $service_helper The service helper
     * @param string $language The default language for new users
     * @param stdClass $row The module row
     * @return stdClass|null The panel user object, or null on failure (Input errors set)
     */
    private function findOrCreateUser($client, $service_helper, $language, $row)
    {
        $api = $this->getApiFromRow($row);
        $external_id = 'bl-' . $client->id;

        $response = $api->apiRequest('GET', '/api/admin/users/external/' . rawurlencode($external_id));
        if ($response->status() < 400 && isset($response->response()->user)) {
            return $response->response()->user;
        }

        $params = $service_helper->addUserParameters($client, $language);
        $create_response = $api->apiRequest('POST', '/api/admin/users', $params);
        $this->log('POST /api/admin/users', json_encode($params), 'input', true);
        $this->log('POST /api/admin/users', $create_response->raw(), 'output', $create_response->status() < 400);

        if ($create_response->status() < 400 && isset($create_response->response()->user)) {
            return $create_response->response()->user;
        }

        if ($create_response->status() != 409) {
            $errors = $create_response->errors();
            $this->Input->setErrors(['Calagopus' => $errors['api'] ?? ['User creation failed.']]);
            return null;
        }

        $matched = $this->searchUser($api, $client->email, 'email');
        if (!$matched) {
            $matched = $this->searchUser($api, $params['username'], 'username');
        }

        if (!$matched) {
            $this->Input->setErrors(
                ['Calagopus' => ['user' => Language::_('Calagopus.!error.user.conflict', true)]]
            );
            return null;
        }

        $api->apiRequest('PATCH', '/api/admin/users/' . $matched->uuid, ['external_id' => $external_id]);

        return $matched;
    }

    /**
     * Searches for a panel user matching the given value on the given field.
     *
     * @param CalagopusApi $api The API client
     * @param string $search The value to search for
     * @param string $field The user field to match (email or username)
     * @return stdClass|null The matched user, or null
     */
    private function searchUser($api, $search, $field)
    {
        $response = $api->apiRequest('GET', '/api/admin/users', [
            'page' => 1,
            'per_page' => 10,
            'search' => $search,
        ]);

        if ($response->status() >= 400) {
            return null;
        }

        foreach (($response->response()->users->data ?? []) as $user) {
            if (strcasecmp($user->{$field} ?? '', $search) === 0) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Fetches the egg variables for the given nest/egg, tolerating failures.
     *
     * @param string $nest_uuid The nest UUID
     * @param string $egg_uuid The egg UUID
     * @return array A list of egg variable objects
     */
    private function getEggVariables($nest_uuid, $egg_uuid)
    {
        if ($nest_uuid === '' || $egg_uuid === '') {
            return [];
        }

        $response = $this->apiRequest(
            'GET',
            '/api/admin/nests/' . $nest_uuid . '/eggs/' . $egg_uuid . '/variables'
        );

        if ($this->Input->errors()) {
            $this->Input->setErrors([]);
            return [];
        }

        return (array)($response->variables ?? []);
    }

    /**
     * Gets the Calagopus server for the given service, or null if not found.
     *
     * @param stdClass $service A stdClass object representing the current service
     * @return stdClass|null An object representing the server, or null
     */
    private function getServer($service)
    {
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $server = null;

        if (!empty($service_fields->server_uuid)) {
            $response = $this->apiRequest('GET', '/api/admin/servers/' . $service_fields->server_uuid);
            if (!$this->Input->errors()) {
                $server = $response->server ?? null;
            }
        }

        if (!$server && !empty($service_fields->external_id)) {
            $this->Input->setErrors([]);
            $response = $this->apiRequest(
                'GET',
                '/api/admin/servers/external/' . rawurlencode($service_fields->external_id)
            );
            if (!$this->Input->errors()) {
                $server = $response->server ?? null;
            }
        }

        if (!$server) {
            $this->Input->setErrors([]);
        }

        return $server;
    }

    /**
     * Builds the service meta fields to store.
     *
     * @param array $vars An array of post/derived fields
     * @param stdClass $service_fields The existing service fields
     * @param stdClass $package The package
     * @param array $egg_variables A list of egg variable objects
     * @param CalagopusService $service_helper The service helper
     * @return array A numerically indexed array of meta fields
     */
    private function getServiceMeta(array $vars, $service_fields, $package, array $egg_variables, $service_helper)
    {
        $get = function ($key, $default = null) use ($vars, $service_fields) {
            if (isset($vars[$key]) && $vars[$key] !== '') {
                return $vars[$key];
            }
            if (isset($service_fields->{$key})) {
                return $service_fields->{$key};
            }
            return $default;
        };

        $return = [
            ['key' => 'server_uuid', 'value' => $get('server_uuid'), 'encrypted' => 0],
            ['key' => 'external_id', 'value' => $get('external_id'), 'encrypted' => 0],
            ['key' => 'server_ip', 'value' => $get('server_ip'), 'encrypted' => 0],
            ['key' => 'server_port', 'value' => $get('server_port'), 'encrypted' => 0],
            [
                'key' => 'server_name',
                'value' => $vars['server_name'] ?? ($service_fields->server_name ?? ''),
                'encrypted' => 0
            ],
        ];

        $environment = $service_helper->getEnvironmentVariables($vars, $package, $egg_variables, $service_fields);
        foreach ($environment as $variable) {
            $key = strtolower($variable['env_variable']);
            foreach ($return as $index => $item) {
                if ($item['key'] === $key) {
                    unset($return[$index]);
                }
            }
            $return[] = ['key' => $key, 'value' => $variable['value'], 'encrypted' => 0];
        }

        return array_values($return);
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the admin interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content
     */
    public function getAdminServiceInfo($service, $package)
    {
        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'calagopus' . DS);

        Loader::loadHelpers($this, ['Form', 'Html']);

        $row = $this->getModuleRow();
        $this->view->set('module_row', $row);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));
        $this->view->set('server', $this->getServer($service));
        $this->view->set('panel_url', $this->getPanelUrl($row));

        return $this->view->fetch();
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the client interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content
     */
    public function getClientServiceInfo($service, $package)
    {
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'calagopus' . DS);

        Loader::loadHelpers($this, ['Form', 'Html']);

        $row = $this->getModuleRow();
        $this->view->set('module_row', $row);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));
        $this->view->set('server', $this->getServer($service));
        $this->view->set('panel_url', $this->getPanelUrl($row));

        return $this->view->fetch();
    }

    /**
     * Builds the base panel URL from a module row.
     *
     * @param stdClass $row The module row
     * @return string The panel URL (without trailing slash)
     */
    private function getPanelUrl($row)
    {
        if (!$row) {
            return '';
        }

        $host = rtrim($row->meta->host_name ?? '', '/');
        if (preg_match('#^(https?)://#i', $host, $matches)) {
            $protocol = strtolower($matches[1]);
            $host = preg_replace('#^https?://#i', '', $host);
        } else {
            $protocol = ($row->meta->use_ssl ?? 'true') == 'true' ? 'https' : 'http';
        }

        return $protocol . '://' . $host;
    }

    /**
     * Returns an array of package fields, overriding them with configurable options.
     *
     * @param array $vars An array of key/value input pairs
     * @param stdClass $package A stdClass object representing the package
     * @return stdClass The modified package object
     */
    private function getConfigurableOptions(array $vars, $package)
    {
        $fields = [
            'nest_uuid', 'egg_uuid', 'node_uuid', 'memory', 'swap', 'disk', 'cpu',
            'memory_overhead', 'io_weight', 'allocations_limit', 'database_limit',
            'backup_limit', 'schedule_limit', 'docker_image', 'startup_command'
        ];

        if (!empty($vars['configoptions'])) {
            foreach ($vars['configoptions'] as $field => $value) {
                if (in_array($field, $fields)) {
                    $package->meta->{$field} = $value;
                }
            }
        }

        return $package;
    }

    /**
     * Runs an API request, logs it, and reports errors via Input.
     *
     * @param string $method The HTTP method
     * @param string $endpoint The API endpoint
     * @param array $params The request parameters (optional)
     * @return mixed The decoded response, or null on error
     */
    private function apiRequest($method, $endpoint, array $params = [])
    {
        $row = $this->getModuleRow();
        if (!$row) {
            $this->Input->setErrors(
                ['module_row' => ['missing' => Language::_('Calagopus.!error.module_row.missing', true)]]
            );
            return;
        }

        $api = $this->getApiFromRow($row);
        $response = $api->apiRequest($method, $endpoint, $params);
        $errors = $response->errors();

        $this->log($method . ' ' . $endpoint, json_encode($params), 'input', true);
        $this->log($method . ' ' . $endpoint, $response->raw(), 'output', empty($errors));

        if (!empty($errors)) {
            $this->Input->setErrors(['Calagopus' => $errors['api'] ?? $errors]);
            return;
        }

        return $response->response();
    }

    /**
     * Validates input data when attempting to add a package, returning the meta data to save.
     *
     * @param array $vars An array of key/value pairs used to add the package (optional)
     * @return array A numerically indexed array of meta fields to be stored for this package
     */
    public function addPackage(array $vars = null)
    {
        $this->loadLib('calagopus_package');
        $package_helper = new CalagopusPackage();

        $package_lists = $this->getPackageLists((object)$vars);

        $meta = $package_helper->add($package_lists, $vars);
        if ($package_helper->errors()) {
            $this->Input->setErrors($package_helper->errors());
        }

        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returning the meta data to save.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of key/value pairs used to edit the package (optional)
     * @return array A numerically indexed array of meta fields to be stored for this package
     */
    public function editPackage($package, array $vars = null)
    {
        return $this->addPackage($vars);
    }

    /**
     * Returns all fields used when adding/editing a package.
     *
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getPackageFields($vars = null)
    {
        $this->loadLib('calagopus_package');
        $package_helper = new CalagopusPackage();

        $package_lists = $this->getPackageLists($vars);

        return $package_helper->getFields($package_lists, $vars);
    }

    /**
     * Gets the package field lists from the API.
     *
     * @param stdClass $vars A stdClass object representing a set of post fields
     * @return array An array of field lists keyed by type
     */
    public function getPackageLists($vars)
    {
        $row = null;
        if (!isset($vars->module_group) || $vars->module_group == '' || $vars->module_group == 'select') {
            if (isset($vars->module_row) && $vars->module_row > 0) {
                $row = $this->getModuleRow($vars->module_row);
            } else {
                $rows = $this->getModuleRows();
                if (isset($rows[0])) {
                    $row = $rows[0];
                }
                unset($rows);
            }
        } else {
            $rows = $this->getModuleRows($vars->module_group);
            if (isset($rows[0])) {
                $row = $rows[0];
            }
            unset($rows);
        }

        if (!$row) {
            Loader::loadModels($this, ['ModuleManager']);
            $module = $this->ModuleManager->getByClass('calagopus', Configure::get('Blesta.company_id'));
            if (!empty($module[0]->id)) {
                $rows = $this->ModuleManager->getRows($module[0]->id);
                if (isset($rows[0])) {
                    $row = $rows[0];
                }
                unset($rows);
            }
        }

        $package_lists = [];
        if (!$row) {
            $this->log(
                'getPackageLists',
                json_encode([
                    'error' => 'no module row resolved',
                    'module_row' => $vars->module_row ?? null,
                    'module_group' => $vars->module_group ?? null
                ]),
                'input',
                false
            );
            return $package_lists;
        }

        $api = $this->getApiFromRow($row);

        // Nests
        $nests_response = $api->apiRequest('GET', '/api/admin/nests', ['page' => 1, 'per_page' => 100]);
        $this->log('GET /api/admin/nests', json_encode([]), 'input', true);
        $this->log('GET /api/admin/nests', $nests_response->raw(), 'output', $nests_response->status() < 400);
        if ($nests_response->status() < 400) {
            $package_lists['nests'] = ['' => Language::_('AppController.select.please', true)];
            foreach (($nests_response->response()->nests->data ?? []) as $nest) {
                $package_lists['nests'][$nest->uuid] = $nest->name;
            }
        }

        // Locations
        $locations_response = $api->apiRequest('GET', '/api/admin/locations', ['page' => 1, 'per_page' => 100]);
        $this->log('GET /api/admin/locations', json_encode([]), 'input', true);
        $this->log('GET /api/admin/locations', $locations_response->raw(), 'output', $locations_response->status() < 400);
        if ($locations_response->status() < 400) {
            $package_lists['locations'] = [];
            foreach (($locations_response->response()->locations->data ?? []) as $location) {
                $package_lists['locations'][$location->uuid] = $location->name;
            }
        }

        // Nodes
        $nodes_response = $api->apiRequest('GET', '/api/admin/nodes', ['page' => 1, 'per_page' => 100]);
        $this->log('GET /api/admin/nodes', json_encode([]), 'input', true);
        $this->log('GET /api/admin/nodes', $nodes_response->raw(), 'output', $nodes_response->status() < 400);
        if ($nodes_response->status() < 400) {
            $package_lists['nodes'] = ['' => Language::_('Calagopus.please_select.node', true)];
            foreach (($nodes_response->response()->nodes->data ?? []) as $node) {
                $location_name = $node->location->name ?? 'N/A';
                $package_lists['nodes'][$node->uuid] = $node->name . ' (' . $location_name . ')';
            }
        }

        // Eggs (once a nest is selected)
        $nest_uuid = $vars->meta['nest_uuid'] ?? null;
        if (!empty($nest_uuid)) {
            $eggs_response = $api->apiRequest(
                'GET',
                '/api/admin/nests/' . $nest_uuid . '/eggs',
                ['page' => 1, 'per_page' => 100]
            );
            if ($eggs_response->status() < 400) {
                $package_lists['eggs'] = ['' => Language::_('AppController.select.please', true)];
                foreach (($eggs_response->response()->eggs->data ?? []) as $egg) {
                    $package_lists['eggs'][$egg->uuid] = $egg->name;
                }
            }

            // Egg variables (once an egg is selected)
            $egg_uuid = $vars->meta['egg_uuid'] ?? null;
            if (!empty($egg_uuid)) {
                $variables_response = $api->apiRequest(
                    'GET',
                    '/api/admin/nests/' . $nest_uuid . '/eggs/' . $egg_uuid . '/variables'
                );
                if ($variables_response->status() < 400) {
                    $package_lists['egg_variables'] = (array)($variables_response->response()->variables ?? []);
                }
            }
        }

        return $package_lists;
    }

    /**
     * Returns all fields to display to an admin attempting to add a service.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getAdminAddFields($package, $vars = null)
    {
        $this->setModuleRowFromPackage($package);

        $this->loadLib('calagopus_service');
        $service_helper = new CalagopusService();

        $egg_variables = $this->getEggVariables($package->meta->nest_uuid ?? '', $package->meta->egg_uuid ?? '');

        return $service_helper->getFields($egg_variables, $package, $vars, true);
    }

    /**
     * Returns all fields to display to a client attempting to add a service.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getClientAddFields($package, $vars = null)
    {
        $this->setModuleRowFromPackage($package);

        $this->loadLib('calagopus_service');
        $service_helper = new CalagopusService();

        $egg_variables = $this->getEggVariables($package->meta->nest_uuid ?? '', $package->meta->egg_uuid ?? '');

        return $service_helper->getFields($egg_variables, $package, $vars, false);
    }

    /**
     * Returns all fields to display to an admin attempting to edit a service.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getAdminEditFields($package, $vars = null)
    {
        return $this->getAdminAddFields($package, $vars);
    }

    /**
     * Returns all fields to display to a client attempting to edit a service.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getClientEditFields($package, $vars = null)
    {
        return $this->getClientAddFields($package, $vars);
    }

    /**
     * Returns the rendered view of the manage module page.
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manage module page
     * @return string HTML content
     */
    public function manageModule($module, array &$vars)
    {
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'calagopus' . DS);

        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->loadLib('calagopus_importer');
        $importer = new CalagopusImporter();

        // Gather Pterodactyl module info for the import section
        $pterodactyl = $importer->getPterodactylModule();
        $pterodactyl_rows = [];
        $import_counts = ['packages' => 0, 'services' => 0];
        if ($pterodactyl) {
            Loader::loadModels($this, ['ModuleManager']);
            $pterodactyl_rows = $this->ModuleManager->getRows($pterodactyl->id);
            $import_counts = $importer->getCounts($pterodactyl->id);
        }

        $import_results = null;
        if ($pterodactyl && ($vars['import_action'] ?? '') == 'pterodactyl') {
            $import_results = $importer->import($module, (array)($vars['row_map'] ?? []));
            $errors = $importer->errors();

            if ($this->isAjaxRequest()) {
                if ($errors) {
                    $this->outputJson(['error' => $this->flattenErrors($errors)]);
                }

                $this->outputJson([
                    'success' => Language::_(
                        'Calagopus.manage.import_success',
                        true,
                        (int)$import_results['packages'],
                        (int)$import_results['services']
                    ),
                    'notes' => array_values((array)($import_results['notes'] ?? [])),
                ]);
            }

            if ($errors) {
                $this->Input->setErrors($errors);
                $import_results = null;
            } else {
                $import_counts = $importer->getCounts($pterodactyl->id);
            }
        }

        $this->view->set('module', $module);
        $this->view->set('pterodactyl', $pterodactyl);
        $this->view->set('pterodactyl_rows', $pterodactyl_rows);
        $this->view->set('import_counts', $import_counts);
        $this->view->set('import_results', $import_results);
        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Determines whether the current request was made over AJAX.
     *
     * @return bool True if this is an AJAX request, false otherwise
     */
    private function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * Outputs the given data as a JSON response and ends the request. Used to
     * answer AJAX requests before the controller continues to other processing.
     *
     * @param array $data The data to encode and output
     */
    private function outputJson(array $data)
    {
        // Discard any buffered page output so only the JSON body is returned
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Flattens a nested Input errors array into a simple list of messages.
     *
     * @param array $errors The errors array as returned by Input::errors()
     * @return array A numerically indexed list of error message strings
     */
    private function flattenErrors(array $errors)
    {
        $messages = [];
        array_walk_recursive($errors, function ($message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    /**
     * Returns the rendered view of the add module row page.
     *
     * @param array $vars An array of post data submitted to or on the add module row page
     * @return string HTML content
     */
    public function manageAddRow(array &$vars)
    {
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'calagopus' . DS);

        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        Loader::loadModels($this, ['ModuleManager']);
        $module = $this->ModuleManager->getByClass(
            'calagopus',
            Configure::get('Blesta.company_id')
        );
        $module = ($module[0] ?? []);
        $this->view->set('module', (object)$module);
        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit module row page
     * @return string HTML content
     */
    public function manageEditRow($module_row, array &$vars)
    {
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'calagopus' . DS);

        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        }

        Loader::loadModels($this, ['ModuleManager']);
        $module = $this->ModuleManager->getByClass(
            'calagopus',
            Configure::get('Blesta.company_id')
        );
        $module = ($module[0] ?? []);
        $this->view->set('module', (object)$module);
        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Adds the module row. Sets Input errors on failure.
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row
     */
    public function addModuleRow(array &$vars)
    {
        $meta_fields = ['server_name', 'host_name', 'api_key', 'default_language'];
        $encrypted_fields = ['api_key'];

        $this->Input->setRules($this->getRowRules($vars));

        if ($this->Input->validates($vars)) {
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Edits the module row. Sets Input errors on failure.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row
     */
    public function editModuleRow($module_row, array &$vars)
    {
        return $this->addModuleRow($vars);
    }

    /**
     * Initializes the CalagopusApi and returns an instance of it.
     *
     * @param string $host The hostname of the Calagopus panel
     * @param string $api_key The admin API key
     * @param bool $use_ssl Whether to connect over SSL
     * @return CalagopusApi The CalagopusApi instance
     */
    private function getApi($host, $api_key, $use_ssl = true)
    {
        $this->loadLib('calagopus_api');

        return new CalagopusApi($host, $api_key, $use_ssl);
    }

    /**
     * Initializes the CalagopusApi from a module row.
     *
     * @param stdClass $row The module row (optional, defaults to the active row)
     * @return CalagopusApi The CalagopusApi instance
     */
    private function getApiFromRow($row = null)
    {
        if (!$row) {
            $row = $this->getModuleRow();
        }

        return $this->getApi(
            $row->meta->host_name,
            $row->meta->api_key,
            ($row->meta->use_ssl ?? 'true') == 'true'
        );
    }

    /**
     * Builds and returns the rules required to add/edit a module row.
     *
     * @param array $vars An array of key/value data pairs
     * @return array An array of Input rules suitable for Input::setRules()
     */
    private function getRowRules(array &$vars)
    {
        return [
            'server_name' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Calagopus.!error.server_name.empty', true)
                ]
            ],
            'host_name' => [
                'valid' => [
                    'rule' => function ($host_name) {
                        $validator = new Server();
                        $host_name = preg_replace('#^https?://#i', '', rtrim((string)$host_name, '/'));
                        $parts = explode(':', $host_name, 2);
                        return ($validator->isDomain($parts[0]) || $validator->isIp($parts[0]))
                            && (!isset($parts[1]) || is_numeric($parts[1]));
                    },
                    'message' => Language::_('Calagopus.!error.host_name.valid', true)
                ]
            ],
            'api_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Calagopus.!error.api_key.empty', true)
                ],
                'valid' => [
                    'rule' => function ($api_key) use ($vars) {
                        try {
                            $api = $this->getApi(
                                $vars['host_name'] ?? '',
                                $api_key
                            );
                            $response = $api->apiRequest('GET', '/api/admin/locations', ['page' => 1, 'per_page' => 1]);
                            $this->log('GET /api/admin/locations', json_encode([]), 'input', true);
                            $this->log('GET /api/admin/locations', $response->raw(), 'output', $response->status() < 400);

                            return $response->status() >= 200 && $response->status() < 300;
                        } catch (\Throwable $e) {
                            $this->logger->error($e->getMessage());
                            return false;
                        }
                    },
                    'message' => Language::_('Calagopus.!error.api_key.valid', true)
                ]
            ]
        ];
    }
}
