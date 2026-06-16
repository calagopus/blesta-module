<?php

/**
 * Calagopus Rule helper
 *
 * Extracts the egg variable rules the module acts on: required, in: (rendered
 * as a select) and boolean (rendered as a checkbox). All other rules are left
 * for the Calagopus panel to validate, which reports errors on save.
 *
 * @package blesta
 * @subpackage blesta.components.modules.calagopus.lib
 * @copyright Copyright (c) 2024, Calagopus
 * @link https://calagopus.com Calagopus
 */
class CalagopusRule
{
    /**
     * Parses an egg variable from Calagopus and returns a Blesta input validation rule array.
     *
     * @param stdClass $eggVariable The egg variable to create rules from
     * @return array A list of Blesta rules parsed from the egg variable
     */
    public function parseEggVariable($eggVariable)
    {
        $fieldName = $eggVariable->name ?? ($eggVariable->env_variable ?? '');

        $rules = [];
        if (self::isRequired($eggVariable)) {
            $rules['required'] = [
                'rule' => function ($value) {
                    return $value !== null && $value !== '';
                },
                'message' => Language::_('CalagopusRule.!error.required', true, $fieldName)
            ];
        }

        if (($values = self::inValues($eggVariable)) !== null) {
            $rules['in'] = [
                'rule' => function ($value) use ($values) {
                    return in_array((string)$value, $values, true);
                },
                'message' => Language::_('CalagopusRule.!error.in', true, $fieldName, implode(', ', $values))
            ];
        }

        if (self::isBoolean($eggVariable)) {
            $rules['boolean'] = [
                'rule' => function ($value) {
                    return in_array(strtolower((string)$value), ['0', '1', 'true', 'false'], true);
                },
                'message' => Language::_('CalagopusRule.!error.boolean', true, $fieldName)
            ];
        }

        // Skip validation of optional fields without a value
        if (!isset($rules['required'])) {
            foreach ($rules as &$rule) {
                $rule['if_set'] = true;
            }
            unset($rule);
        }

        return $rules;
    }

    /**
     * Normalizes the rules of an egg variable to a list of rule strings. The
     * Calagopus API may return rules as a list or a pipe-delimited string.
     *
     * @param stdClass $eggVariable The egg variable
     * @return array A list of rule strings
     */
    public static function ruleStrings($eggVariable)
    {
        $rules = $eggVariable->rules ?? '';
        if (!is_array($rules)) {
            $rules = explode('|', (string)$rules);
        }

        $ruleStrings = [];
        foreach ($rules as $rule) {
            $rule = trim((string)$rule);
            if ($rule !== '') {
                $ruleStrings[] = $rule;
            }
        }

        return $ruleStrings;
    }

    /**
     * Returns whether the given egg variable is required.
     *
     * @param stdClass $eggVariable The egg variable
     * @return bool True if the variable has a required rule
     */
    public static function isRequired($eggVariable)
    {
        foreach (self::ruleStrings($eggVariable) as $rule) {
            if (strtolower($rule) === 'required') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the given egg variable is a boolean.
     *
     * @param stdClass $eggVariable The egg variable
     * @return bool True if the variable has a boolean rule
     */
    public static function isBoolean($eggVariable)
    {
        foreach (self::ruleStrings($eggVariable) as $rule) {
            if (in_array(strtolower($rule), ['boolean', 'bool'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the allowed values of the given egg variable's in: rule, if any.
     *
     * @param stdClass $eggVariable The egg variable
     * @return array|null The list of allowed values, or null when no in: rule exists
     */
    public static function inValues($eggVariable)
    {
        foreach (self::ruleStrings($eggVariable) as $rule) {
            if (stripos($rule, 'in:') === 0) {
                $values = [];
                foreach (explode(',', substr($rule, 3)) as $value) {
                    $value = trim($value);
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }

                return $values;
            }
        }

        return null;
    }
}
