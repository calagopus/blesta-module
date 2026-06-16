<?php

/**
 * Calagopus Package helper
 *
 * @package blesta
 * @subpackage blesta.components.modules.calagopus.lib
 * @copyright Copyright (c) 2024, Calagopus
 * @link https://calagopus.com Calagopus
 */
class CalagopusPackage
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
     * The list of resource/limit fields stored on the package.
     *
     * @return array A list of field names
     */
    public static function getMetaFields()
    {
        return [
            'nest_uuid', 'egg_uuid', 'node_uuid', 'location_uuids',
            'memory', 'swap', 'disk', 'cpu', 'memory_overhead', 'io_weight',
            'allocations_limit', 'database_limit', 'backup_limit', 'schedule_limit',
            'custom_feature_limits', 'docker_image', 'startup_command',
            'server_name_prefix', 'pinned_cpus', 'backup_configuration_uuid',
            'skip_installer', 'start_on_completion', 'hugepages_passthrough', 'kvm_passthrough'
        ];
    }

    /**
     * Validates input data when attempting to add/edit a package, returning the
     * meta data to save. Sets Input errors on failure.
     *
     * @param array $packageLists An array of field lists from the API
     * @param array $vars An array of key/value pairs used to add the package (optional)
     * @return array A numerically indexed array of meta fields to be stored
     */
    public function add(array $packageLists, array $vars = null)
    {
        $vars = (array)$vars;

        // Set missing checkboxes
        $checkboxes = ['skip_installer', 'start_on_completion', 'hugepages_passthrough', 'kvm_passthrough'];
        foreach ($checkboxes as $checkbox) {
            if (empty($vars['meta'][$checkbox])) {
                $vars['meta'][$checkbox] = '0';
            }
        }

        // Get egg variable rules
        $rules = $this->getRules($packageLists, $vars);
        if (!empty($packageLists['egg_variables'])) {
            Loader::load(dirname(__FILE__) . DS . 'calagopus_rule.php');
            $rule_helper = new CalagopusRule();

            foreach ($packageLists['egg_variables'] as $envVariable) {
                $fieldName = strtolower($envVariable->env_variable ?? '');
                if ($fieldName === '') {
                    continue;
                }

                $rules['meta[' . $fieldName . ']'] = $rule_helper->parseEggVariable($envVariable);

                foreach ($rules['meta[' . $fieldName . ']'] as $rule) {
                    if (
                        array_key_exists('if_set', $rule)
                        && $rule['if_set'] == true
                        && empty($vars['meta'][$fieldName])
                    ) {
                        unset($rules['meta[' . $fieldName . ']']);
                    }
                }
            }
        }

        $this->Input->setRules($rules);

        $meta = [];
        if ($this->Input->validates($vars)) {
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Returns all fields used when adding/editing a package.
     *
     * @param array $packageLists An array of field lists from the API
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getFields(array $packageLists, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Re-fetch options when the nest or egg is changed
        $fields->setHtml("
            <script type=\"text/javascript\">
                $(document).off('change.calagopus').on('change.calagopus', '#Calagopus_nest_uuid, #Calagopus_egg_uuid', function() {
                    fetchModuleOptions();
                });
            </script>
        ");

        // Select fields
        $selectFields = [
            'nest_uuid' => $packageLists['nests'] ?? [],
            'egg_uuid' => $packageLists['eggs'] ?? [],
            'node_uuid' => $packageLists['nodes'] ?? [],
        ];
        foreach ($selectFields as $selectField => $list) {
            $field = $fields->label(
                Language::_('CalagopusPackage.package_fields.' . $selectField, true),
                'Calagopus_' . $selectField
            );
            $field->attach(
                $fields->fieldSelect(
                    'meta[' . $selectField . ']',
                    $list,
                    ($vars->meta[$selectField] ?? null),
                    ['id' => 'Calagopus_' . $selectField]
                )
            );
            $field->attach(
                $fields->tooltip(Language::_('CalagopusPackage.package_fields.tooltip.' . $selectField, true))
            );
            $fields->setField($field);
        }

        // Location(s) multi-select (deploy mode)
        $locationField = $fields->label(
            Language::_('CalagopusPackage.package_fields.location_uuids', true),
            'Calagopus_location_uuids'
        );
        $locationValue = $vars->meta['location_uuids'] ?? [];
        if (is_string($locationValue)) {
            $locationValue = array_filter(array_map('trim', explode(',', $locationValue)));
        }
        $locationField->attach(
            $fields->fieldMultiSelect(
                'meta[location_uuids][]',
                $packageLists['locations'] ?? [],
                $locationValue,
                ['id' => 'Calagopus_location_uuids']
            )
        );
        $locationField->attach(
            $fields->tooltip(Language::_('CalagopusPackage.package_fields.tooltip.location_uuids', true))
        );
        $fields->setField($locationField);

        // Numeric/text fields
        $textFields = [
            'memory', 'swap', 'disk', 'cpu', 'memory_overhead', 'io_weight',
            'allocations_limit', 'database_limit', 'backup_limit', 'schedule_limit',
            'custom_feature_limits', 'docker_image', 'startup_command',
            'server_name_prefix', 'pinned_cpus', 'backup_configuration_uuid'
        ];
        $defaults = [
            'memory' => '1024', 'swap' => '0', 'disk' => '10240', 'cpu' => '100',
            'memory_overhead' => '0', 'allocations_limit' => '1', 'database_limit' => '0',
            'backup_limit' => '0', 'schedule_limit' => '0'
        ];
        foreach ($textFields as $textField) {
            $field = $fields->label(
                Language::_('CalagopusPackage.package_fields.' . $textField, true),
                'Calagopus_' . $textField
            );
            $field->attach(
                $fields->fieldText(
                    'meta[' . $textField . ']',
                    ($vars->meta[$textField] ?? ($defaults[$textField] ?? null)),
                    ['id' => 'Calagopus_' . $textField]
                )
            );
            $field->attach(
                $fields->tooltip(Language::_('CalagopusPackage.package_fields.tooltip.' . $textField, true))
            );
            $fields->setField($field);
        }

        // Checkbox fields
        $checkboxFields = ['skip_installer', 'start_on_completion', 'hugepages_passthrough', 'kvm_passthrough'];
        foreach ($checkboxFields as $checkboxField) {
            $field = $fields->label(
                Language::_('CalagopusPackage.package_fields.' . $checkboxField, true),
                'Calagopus_' . $checkboxField,
                ['class' => 'inline']
            );
            $field->attach(
                $fields->fieldCheckbox(
                    'meta[' . $checkboxField . ']',
                    '1',
                    ($vars->meta[$checkboxField] ?? null) == '1',
                    ['id' => 'Calagopus_' . $checkboxField, 'class' => 'inline']
                )
            );
            $field->attach(
                $fields->tooltip(Language::_('CalagopusPackage.package_fields.tooltip.' . $checkboxField, true))
            );
            $fields->setField($field);
        }

        // Attach egg variable fields
        if (!empty($packageLists['egg_variables'])) {
            $this->attachEggFields($packageLists['egg_variables'], $fields, $vars);
        }

        return $fields;
    }

    /**
     * Attaches package fields for each environment variable from the egg. The
     * input type depends on the variable's rules: a select for in: rules, a
     * checkbox for booleans, and a text field otherwise.
     *
     * @param array $eggVariables The list of egg variable objects
     * @param ModuleFields $fields The fields object to attach the new fields to
     * @param stdClass $vars A stdClass object representing a set of post fields (optional)
     * @return ModuleFields The fields object with all environment variable fields attached
     */
    private function attachEggFields(array $eggVariables, $fields, $vars = null)
    {
        Loader::load(dirname(__FILE__) . DS . 'calagopus_rule.php');

        foreach ($eggVariables as $envVariable) {
            $envKey = $envVariable->env_variable ?? '';
            if ($envKey === '') {
                continue;
            }

            $key = strtolower($envKey);
            $label = CalagopusRule::isRequired($envVariable)
                ? ($envVariable->name ?? $envKey)
                : Language::_('CalagopusPackage.package_fields.optional', true, ($envVariable->name ?? $envKey));
            $value = $vars->meta[$key] ?? ($envVariable->default_value ?? '');

            $field = $fields->label($label, $key);
            if (($inValues = CalagopusRule::inValues($envVariable)) !== null) {
                $field->attach(
                    $fields->fieldSelect(
                        'meta[' . $key . ']',
                        array_combine($inValues, $inValues),
                        $value,
                        ['id' => $key]
                    )
                );
            } elseif (CalagopusRule::isBoolean($envVariable)) {
                $field->attach(
                    $fields->fieldHidden('meta[' . $key . ']', '0')
                );
                $field->attach(
                    $fields->fieldCheckbox(
                        'meta[' . $key . ']',
                        '1',
                        in_array(strtolower((string)$value), ['1', 'true'], true),
                        ['id' => $key, 'class' => 'inline']
                    )
                );
            } else {
                $field->attach(
                    $fields->fieldText(
                        'meta[' . $key . ']',
                        $value,
                        ['id' => $key]
                    )
                );
            }
            $field->attach(
                $fields->tooltip(
                    ($envVariable->description ?? '')
                    . ' '
                    . Language::_('CalagopusPackage.package_fields.tooltip.display', true)
                )
            );

            // Whether to display this variable to the client
            $checkboxKey = $key . '_display';
            $field->attach(
                $fields->fieldCheckbox(
                    'meta[' . $checkboxKey . ']',
                    '1',
                    ($vars->meta[$checkboxKey] ?? '0') == '1',
                    ['id' => $checkboxKey, 'class' => 'inline']
                )
            );
            $fields->setField($field);
        }

        return $fields;
    }

    /**
     * Builds the rules required to add/edit a package.
     *
     * @param array $packageLists An array of field lists from the API
     * @param array $vars An array of key/value data pairs
     * @return array An array of Input rules suitable for Input::setRules()
     */
    public function getRules(array $packageLists, array $vars)
    {
        $rules = [
            'meta[nest_uuid]' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CalagopusPackage.!error.meta[nest_uuid].empty', true)
                ]
            ],
            'meta[egg_uuid]' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CalagopusPackage.!error.meta[egg_uuid].empty', true)
                ]
            ],
            'meta[deploy]' => [
                'valid' => [
                    'rule' => function ($value) use ($vars) {
                        $node = trim($vars['meta']['node_uuid'] ?? '');
                        $locations = $vars['meta']['location_uuids'] ?? [];
                        if (is_string($locations)) {
                            $locations = array_filter(array_map('trim', explode(',', $locations)));
                        }
                        return $node !== '' || !empty($locations);
                    },
                    'message' => Language::_('CalagopusPackage.!error.meta[deploy].valid', true)
                ]
            ],
            'meta[memory]' => [
                'format' => [
                    'pre_format' => 'strval',
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('CalagopusPackage.!error.meta[memory].format', true)
                ]
            ],
            'meta[swap]' => [
                'format' => [
                    'pre_format' => 'strval',
                    'rule' => ['matches', '/^(?:\-1|[0-9]+)$/'],
                    'message' => Language::_('CalagopusPackage.!error.meta[swap].format', true)
                ]
            ],
            'meta[disk]' => [
                'format' => [
                    'pre_format' => 'strval',
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('CalagopusPackage.!error.meta[disk].format', true)
                ]
            ],
            'meta[cpu]' => [
                'format' => [
                    'pre_format' => 'strval',
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('CalagopusPackage.!error.meta[cpu].format', true)
                ]
            ],
            'meta[memory_overhead]' => [
                'format' => [
                    'rule' => function ($value) {
                        return $value === '' || preg_match('/^[0-9]+$/', $value);
                    },
                    'message' => Language::_('CalagopusPackage.!error.meta[memory_overhead].format', true)
                ]
            ],
            'meta[io_weight]' => [
                'format' => [
                    'rule' => function ($value) {
                        return $value === '' || (preg_match('/^[0-9]+$/', $value) && $value >= 10 && $value <= 1000);
                    },
                    'message' => Language::_('CalagopusPackage.!error.meta[io_weight].format', true)
                ]
            ],
        ];

        // Limits must be non-negative integers when provided
        foreach (['allocations_limit', 'database_limit', 'backup_limit', 'schedule_limit'] as $limitField) {
            $rules['meta[' . $limitField . ']'] = [
                'format' => [
                    'rule' => function ($value) {
                        return $value === '' || preg_match('/^[0-9]+$/', $value);
                    },
                    'message' => Language::_('CalagopusPackage.!error.meta[' . $limitField . '].format', true)
                ]
            ];
        }

        $rules['meta[pinned_cpus]'] = [
            'format' => [
                'rule' => function ($value) {
                    return $value === '' || preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $value);
                },
                'message' => Language::_('CalagopusPackage.!error.meta[pinned_cpus].format', true)
            ]
        ];

        $rules['meta[docker_image]'] = [
            'length' => [
                'rule' => ['maxLength', 255],
                'message' => Language::_('CalagopusPackage.!error.meta[docker_image].length', true)
            ]
        ];

        return $rules;
    }
}
