<?php

/**
 * Calagopus Service helper
 *
 * @package blesta
 * @subpackage blesta.components.modules.calagopus.lib
 * @copyright Copyright (c) 2024, Calagopus
 * @link https://calagopus.com Calagopus
 */
class CalagopusService
{
    /**
     * Initialize
     */
    public function __construct()
    {
        Loader::loadComponents($this, ['Input']);
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
     * Gets the parameters to submit to Calagopus for user creation.
     *
     * @param stdClass $client The Blesta client
     * @param string $language The default language for the user
     * @return array A list containing the parameters
     */
    public function addUserParameters($client, $language = 'en')
    {
        return [
            'external_id' => 'bl-' . $client->id,
            'username' => $this->generateUsername($client),
            'email' => $client->email,
            'name_first' => $client->first_name,
            'name_last' => $client->last_name,
            'admin' => false,
            'send_email' => true,
            'language' => $language ?: 'en',
        ];
    }

    /**
     * Generates a panel-safe username from the client's details.
     *
     * @param stdClass $client The Blesta client
     * @return string The generated username
     */
    public function generateUsername($client)
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', ($client->first_name ?? '') . ($client->last_name ?? ''));
        if (strlen($base) < 3) {
            $base = 'user';
        }

        $userId = (string)$client->id;
        $maxBaseLength = max(0, 14 - strlen($userId));
        $truncatedBase = substr($base, 0, $maxBaseLength);

        return strtolower($truncatedBase) . '_' . $userId;
    }

    /**
     * Gets the parameters to submit to Calagopus for server creation.
     *
     * @param array $vars An array of post fields
     * @param stdClass $package The package to pull server info from
     * @param stdClass $panelUser An object representing the Calagopus user
     * @param stdClass $egg An object representing the Calagopus egg
     * @param array $eggVariables A list of egg variable objects
     * @param bool $admin Whether the post fields were submitted by an admin (optional)
     * @return array The list of parameters
     */
    public function addServerParameters(
        array $vars,
        $package,
        $panelUser,
        $egg,
        array $eggVariables = [],
        $admin = false
    ) {
        $meta = $package->meta;

        $prefix = $meta->server_name_prefix ?? '';
        $serverName = !empty($vars['server_name'])
            ? $vars['server_name']
            : ($prefix ?: 'Server-') . ($vars['client_id'] ?? '');

        $payload = [
            'owner_uuid' => $panelUser->uuid,
            'egg_uuid' => $meta->egg_uuid,
            'external_id' => (string)($vars['external_id'] ?? ''),
            'name' => $serverName,
            'start_on_completion' => ($meta->start_on_completion ?? '0') == '1',
            'skip_installer' => ($meta->skip_installer ?? '0') == '1',
            'limits' => $this->getLimits($meta),
            'pinned_cpus' => $this->parsePinnedCpus($meta->pinned_cpus ?? ''),
            'startup' => $this->getStartup($meta, $egg),
            'image' => $this->getDockerImage($meta, $egg),
            'hugepages_passthrough_enabled' => ($meta->hugepages_passthrough ?? '0') == '1',
            'kvm_passthrough_enabled' => ($meta->kvm_passthrough ?? '0') == '1',
            'feature_limits' => $this->getFeatureLimits($meta),
            'variables' => $this->getEnvironmentVariables($vars, $package, $eggVariables, null, $admin),
        ];

        $backupConfigUuid = trim($meta->backup_configuration_uuid ?? '');
        if ($backupConfigUuid !== '') {
            $payload['backup_configuration_uuid'] = $backupConfigUuid;
        }

        return $payload;
    }

    /**
     * Gets the parameters to submit to Calagopus when updating a server's build/limits.
     *
     * @param stdClass $package The package to pull server info from
     * @return array The list of parameters
     */
    public function updateServerParameters($package)
    {
        $meta = $package->meta;

        $payload = [
            'limits' => $this->getLimits($meta),
            'feature_limits' => $this->getFeatureLimits($meta),
            'pinned_cpus' => $this->parsePinnedCpus($meta->pinned_cpus ?? ''),
            'hugepages_passthrough_enabled' => ($meta->hugepages_passthrough ?? '0') == '1',
            'kvm_passthrough_enabled' => ($meta->kvm_passthrough ?? '0') == '1',
        ];

        $dockerImage = trim($meta->docker_image ?? '');
        if ($dockerImage !== '') {
            $payload['image'] = $dockerImage;
        }

        return $payload;
    }

    /**
     * Builds the resource limits array from the package meta.
     *
     * @param stdClass $meta The package meta
     * @return array The limits array
     */
    private function getLimits($meta)
    {
        $limits = [
            'cpu' => (int)($meta->cpu ?? 100),
            'memory' => (int)($meta->memory ?? 1024),
            'memory_overhead' => (int)($meta->memory_overhead ?? 0),
            'swap' => (int)($meta->swap ?? 0),
            'disk' => (int)($meta->disk ?? 10240),
        ];

        $ioWeight = $meta->io_weight ?? '';
        if ($ioWeight !== '') {
            $limits['io_weight'] = (int)$ioWeight;
        }

        return $limits;
    }

    /**
     * Builds the feature limits array from the package meta, merging in custom limits.
     *
     * @param stdClass $meta The package meta
     * @return array The feature limits array
     */
    private function getFeatureLimits($meta)
    {
        $featureLimits = [
            'allocations' => (int)($meta->allocations_limit ?? 1),
            'databases' => (int)($meta->database_limit ?? 0),
            'backups' => (int)($meta->backup_limit ?? 0),
            'schedules' => (int)($meta->schedule_limit ?? 0),
        ];

        return array_merge($featureLimits, $this->parseCustomFeatureLimits($meta->custom_feature_limits ?? ''));
    }

    /**
     * Determines the docker image to use, falling back to the egg default.
     *
     * @param stdClass $meta The package meta
     * @param stdClass $egg The egg object
     * @return string The docker image
     */
    private function getDockerImage($meta, $egg)
    {
        $dockerImage = trim($meta->docker_image ?? '');
        if ($dockerImage !== '') {
            return $dockerImage;
        }

        $images = (array)($egg->docker_images ?? []);
        return reset($images) ?: '';
    }

    /**
     * Determines the startup command to use, falling back to the egg default.
     *
     * @param stdClass $meta The package meta
     * @param stdClass $egg The egg object
     * @return string The startup command
     */
    private function getStartup($meta, $egg)
    {
        $startup = trim($meta->startup_command ?? '');
        if ($startup !== '') {
            return $startup;
        }

        if (isset($egg->startup) && is_string($egg->startup)) {
            return $egg->startup;
        }

        $startupCommands = (array)($egg->startup_commands ?? []);
        if (isset($startupCommands['Default'])) {
            return $startupCommands['Default'];
        }

        return reset($startupCommands) ?: '';
    }

    /**
     * Parses the custom feature limits string into an array.
     * Format: "key:value,key:value" (e.g. "plugins:5,worlds:3").
     *
     * @param string $raw The raw custom feature limits string
     * @return array The parsed limits
     */
    public function parseCustomFeatureLimits($raw)
    {
        $limits = [];
        $raw = trim((string)$raw);
        if ($raw === '') {
            return $limits;
        }

        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if (strpos($pair, ':') === false) {
                continue;
            }

            list($key, $value) = explode(':', $pair, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (is_numeric($value)) {
                $limits[$key] = (int)$value;
            } elseif (strtolower($value) === 'true' || strtolower($value) === 'false') {
                $limits[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $limits[$key] = $value;
            }
        }

        return $limits;
    }

    /**
     * Parses a comma-separated list of pinned CPU IDs into an array of integers.
     *
     * @param string $raw The raw pinned CPUs string
     * @return array The parsed CPU IDs
     */
    public function parsePinnedCpus($raw)
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        return array_values(array_map('intval', array_filter(array_map('trim', explode(',', $raw)), 'is_numeric')));
    }

    /**
     * Gets the list of environment variables to submit to Calagopus.
     *
     * Values are resolved in the following priority order:
     * config option, service field, package field, egg default.
     * Post fields are only used for variables displayed to clients, unless submitted by an admin.
     *
     * @param array $vars An array of post fields
     * @param stdClass $package The package to pull defaults from
     * @param array $eggVariables A list of egg variable objects
     * @param stdClass $serviceFields An object representing the current service fields (optional)
     * @param bool $admin Whether the post fields were submitted by an admin (optional)
     * @return array A list of [env_variable, value] pairs
     */
    public function getEnvironmentVariables(
        array $vars,
        $package,
        array $eggVariables,
        $serviceFields = null,
        $admin = false
    ) {
        $variables = [];
        foreach ($eggVariables as $envVariable) {
            $variableName = $envVariable->env_variable ?? '';
            if ($variableName === '') {
                continue;
            }

            $blestaVariableName = strtolower($variableName);
            $clientEditable = isset($package->meta->{$blestaVariableName . '_display'})
                && $package->meta->{$blestaVariableName . '_display'} == '1';
            if (isset($vars['configoptions'][$blestaVariableName])) {
                $value = $vars['configoptions'][$blestaVariableName];
            } elseif (isset($vars[$blestaVariableName]) && ($admin || $clientEditable)) {
                $value = $vars[$blestaVariableName];
            } elseif (isset($serviceFields->{$blestaVariableName})) {
                $value = $serviceFields->{$blestaVariableName};
            } elseif (isset($package->meta->{$blestaVariableName})) {
                $value = $package->meta->{$blestaVariableName};
            } else {
                $value = $envVariable->default_value ?? '';
            }

            $variables[] = [
                'env_variable' => $variableName,
                'value' => (string)$value,
            ];
        }

        return $variables;
    }

    /**
     * Returns all fields used when adding/editing a service.
     *
     * @param array $eggVariables A list of egg variable objects
     * @param stdClass $package The package to pull defaults from
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @param bool $admin Whether these fields will be displayed to an admin (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getFields(array $eggVariables, $package, $vars = null, $admin = false)
    {
        Loader::loadHelpers($this, ['Html', 'Form']);
        Loader::load(dirname(__FILE__) . DS . 'calagopus_rule.php');

        $fields = new ModuleFields();

        // Server name
        $serverName = $fields->label(
            Language::_('CalagopusService.service_fields.server_name', true),
            'server_name'
        );
        $serverName->attach(
            $fields->fieldText(
                'server_name',
                ($vars->server_name ?? null),
                ['id' => 'server_name']
            )
        );
        $serverName->attach(
            $fields->tooltip(Language::_('CalagopusService.service_fields.tooltip.server_name', true))
        );
        $fields->setField($serverName);

        // Egg variable fields
        foreach ($eggVariables as $envVariable) {
            $envKey = $envVariable->env_variable ?? '';
            if ($envKey === '') {
                continue;
            }

            $key = strtolower($envKey);

            // Hide from clients unless flagged for display on the package
            if (
                !$admin
                && (!isset($package->meta->{$key . '_display'}) || $package->meta->{$key . '_display'} != '1')
            ) {
                continue;
            }

            $label = CalagopusRule::isRequired($envVariable)
                ? ($envVariable->name ?? $envKey)
                : Language::_('CalagopusService.service_fields.optional', true, ($envVariable->name ?? $envKey));
            $value = $vars->{$key} ?? ($package->meta->{$key} ?? ($envVariable->default_value ?? ''));

            $field = $fields->label($label, $key);
            if (($inValues = CalagopusRule::inValues($envVariable)) !== null) {
                $field->attach(
                    $fields->fieldSelect(
                        $key,
                        array_combine($inValues, $inValues),
                        $value,
                        ['id' => $key]
                    )
                );
            } elseif (CalagopusRule::isBoolean($envVariable)) {
                $field->attach(
                    $fields->fieldHidden($key, '0')
                );
                $field->attach(
                    $fields->fieldCheckbox(
                        $key,
                        '1',
                        in_array(strtolower((string)$value), ['1', 'true'], true),
                        ['id' => $key, 'class' => 'inline']
                    )
                );
            } else {
                $field->attach(
                    $fields->fieldText(
                        $key,
                        $value,
                        ['id' => $key]
                    )
                );
            }
            if (!empty($envVariable->description)) {
                $field->attach($fields->tooltip($envVariable->description));
            }
            $fields->setField($field);
        }

        return $fields;
    }

    /**
     * Returns the rule set for adding/editing a service.
     *
     * @param array $vars A list of input vars (optional)
     * @param stdClass $package A stdClass object representing the selected package (optional)
     * @param bool $edit True to get the edit rules, false for the add rules (optional)
     * @param array $eggVariables A list of egg variable objects (optional)
     * @return array Service rules
     */
    public function getServiceRules(array $vars = null, $package = null, $edit = false, array $eggVariables = [])
    {
        $rules = [
            'server_name' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CalagopusService.!error.server_name.empty', true)
                ]
            ]
        ];

        if (!empty($eggVariables)) {
            Loader::load(dirname(__FILE__) . DS . 'calagopus_rule.php');
            $rule_helper = new CalagopusRule();

            foreach ($eggVariables as $envVariable) {
                $fieldName = strtolower($envVariable->env_variable ?? '');
                if ($fieldName === '') {
                    continue;
                }

                $rules[$fieldName] = $rule_helper->parseEggVariable($envVariable);

                foreach ($rules[$fieldName] as $rule) {
                    if (
                        array_key_exists('if_set', $rule)
                        && $rule['if_set'] == true
                        && empty($vars[$fieldName])
                    ) {
                        unset($rules[$fieldName]);
                    }
                }
            }
        }

        return $rules;
    }
}
