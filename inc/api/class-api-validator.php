<?php
/**
 * SC Events API Validator
 *
 * Validates request data with various rules
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_API_Validator {

    /**
     * Validation errors
     * @var array
     */
    private $errors = [];

    /**
     * Data to validate
     * @var array
     */
    private $data = [];

    /**
     * Validation rules
     * @var array
     */
    private $rules = [];

    /**
     * Custom error messages
     * @var array
     */
    private $messages = [];

    /**
     * Set data to validate
     *
     * @param array $data Data array
     * @return self
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Set validation rules
     *
     * @param array $rules Rules array
     * @return self
     */
    public function setRules($rules) {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Set custom messages
     *
     * @param array $messages Messages array
     * @return self
     */
    public function setMessages($messages) {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Validate data against rules
     *
     * @param array|null $data  Data to validate
     * @param array|null $rules Validation rules
     * @return bool
     */
    public function validate($data = null, $rules = null) {
        if ($data !== null) {
            $this->data = $data;
        }
        if ($rules !== null) {
            $this->rules = $rules;
        }

        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $value = isset($this->data[$field]) ? $this->data[$field] : null;
            $rules_array = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($rules_array as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a single validation rule
     *
     * @param string $field Field name
     * @param mixed  $value Field value
     * @param string $rule  Rule to apply
     */
    private function applyRule($field, $value, $rule) {
        // Parse rule parameters
        $params = [];
        if (strpos($rule, ':') !== false) {
            list($rule, $param_str) = explode(':', $rule, 2);
            $params = explode(',', $param_str);
        }

        $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

        if (method_exists($this, $method)) {
            $result = call_user_func([$this, $method], $field, $value, $params);
            if ($result !== true) {
                $this->addError($field, $result);
            }
        }
    }

    /**
     * Add validation error
     *
     * @param string $field   Field name
     * @param string $message Error message
     */
    private function addError($field, $message) {
        // Check for custom message
        $key = "$field.$message";
        if (isset($this->messages[$key])) {
            $message = $this->messages[$key];
        }

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get first error for each field
     *
     * @return array
     */
    public function getFirstErrors() {
        $first_errors = [];
        foreach ($this->errors as $field => $errors) {
            $first_errors[$field] = $errors[0];
        }
        return $first_errors;
    }

    /**
     * Check if validation failed
     *
     * @return bool
     */
    public function fails() {
        return !empty($this->errors);
    }

    // ===========================================
    // Validation Rules
    // ===========================================

    /**
     * Validate required field
     */
    private function validateRequired($field, $value, $params) {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return 'required';
        }
        return true;
    }

    /**
     * Validate email format
     */
    private function validateEmail($field, $value, $params) {
        if (empty($value)) return true;
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        return true;
    }

    /**
     * Validate string type
     */
    private function validateString($field, $value, $params) {
        if ($value !== null && !is_string($value)) {
            return 'string';
        }
        return true;
    }

    /**
     * Validate integer type
     */
    private function validateInteger($field, $value, $params) {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            return 'integer';
        }
        return true;
    }

    /**
     * Validate numeric type
     */
    private function validateNumeric($field, $value, $params) {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return 'numeric';
        }
        return true;
    }

    /**
     * Validate boolean type
     */
    private function validateBoolean($field, $value, $params) {
        if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return 'boolean';
        }
        return true;
    }

    /**
     * Validate array type
     */
    private function validateArray($field, $value, $params) {
        if ($value !== null && !is_array($value)) {
            return 'array';
        }
        return true;
    }

    /**
     * Validate minimum length/value
     */
    private function validateMin($field, $value, $params) {
        if (empty($value)) return true;

        $min = isset($params[0]) ? (int) $params[0] : 0;

        if (is_string($value) && strlen($value) < $min) {
            return "min:$min";
        }
        if (is_numeric($value) && $value < $min) {
            return "min:$min";
        }
        if (is_array($value) && count($value) < $min) {
            return "min:$min";
        }

        return true;
    }

    /**
     * Validate maximum length/value
     */
    private function validateMax($field, $value, $params) {
        if (empty($value)) return true;

        $max = isset($params[0]) ? (int) $params[0] : PHP_INT_MAX;

        if (is_string($value) && strlen($value) > $max) {
            return "max:$max";
        }
        if (is_numeric($value) && $value > $max) {
            return "max:$max";
        }
        if (is_array($value) && count($value) > $max) {
            return "max:$max";
        }

        return true;
    }

    /**
     * Validate exact length
     */
    private function validateSize($field, $value, $params) {
        if (empty($value)) return true;

        $size = isset($params[0]) ? (int) $params[0] : 0;

        if (is_string($value) && strlen($value) !== $size) {
            return "size:$size";
        }
        if (is_array($value) && count($value) !== $size) {
            return "size:$size";
        }

        return true;
    }

    /**
     * Validate value is between range
     */
    private function validateBetween($field, $value, $params) {
        if (empty($value)) return true;

        $min = isset($params[0]) ? (int) $params[0] : 0;
        $max = isset($params[1]) ? (int) $params[1] : PHP_INT_MAX;

        if (is_string($value)) {
            $len = strlen($value);
            if ($len < $min || $len > $max) {
                return "between:$min,$max";
            }
        }
        if (is_numeric($value)) {
            if ($value < $min || $value > $max) {
                return "between:$min,$max";
            }
        }

        return true;
    }

    /**
     * Validate value is in list
     */
    private function validateIn($field, $value, $params) {
        if (empty($value)) return true;

        if (!in_array($value, $params)) {
            return 'in:' . implode(',', $params);
        }
        return true;
    }

    /**
     * Validate value is not in list
     */
    private function validateNotIn($field, $value, $params) {
        if (empty($value)) return true;

        if (in_array($value, $params)) {
            return 'not_in:' . implode(',', $params);
        }
        return true;
    }

    /**
     * Validate date format
     */
    private function validateDate($field, $value, $params) {
        if (empty($value)) return true;

        $format = isset($params[0]) ? $params[0] : 'Y-m-d';
        $d = DateTime::createFromFormat($format, $value);

        if (!$d || $d->format($format) !== $value) {
            return 'date';
        }
        return true;
    }

    /**
     * Validate datetime format
     */
    private function validateDatetime($field, $value, $params) {
        if (empty($value)) return true;

        $format = isset($params[0]) ? $params[0] : 'Y-m-d H:i:s';
        $d = DateTime::createFromFormat($format, $value);

        if (!$d || $d->format($format) !== $value) {
            return 'datetime';
        }
        return true;
    }

    /**
     * Validate date is after another date
     */
    private function validateAfter($field, $value, $params) {
        if (empty($value)) return true;

        $after_date = isset($params[0]) ? $params[0] : 'today';
        $date = strtotime($value);
        $after = strtotime($after_date);

        if ($date === false || $after === false || $date <= $after) {
            return "after:$after_date";
        }
        return true;
    }

    /**
     * Validate date is before another date
     */
    private function validateBefore($field, $value, $params) {
        if (empty($value)) return true;

        $before_date = isset($params[0]) ? $params[0] : 'today';
        $date = strtotime($value);
        $before = strtotime($before_date);

        if ($date === false || $before === false || $date >= $before) {
            return "before:$before_date";
        }
        return true;
    }

    /**
     * Validate URL format
     */
    private function validateUrl($field, $value, $params) {
        if (empty($value)) return true;

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        return true;
    }

    /**
     * Validate phone number
     */
    private function validatePhone($field, $value, $params) {
        if (empty($value)) return true;

        // Remove common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\+]/', '', $value);

        // Check if remaining characters are digits
        if (!ctype_digit($cleaned) || strlen($cleaned) < 7 || strlen($cleaned) > 15) {
            return 'phone';
        }
        return true;
    }

    /**
     * Validate regex pattern
     */
    private function validateRegex($field, $value, $params) {
        if (empty($value)) return true;

        $pattern = isset($params[0]) ? $params[0] : '/.*/';

        if (!preg_match($pattern, $value)) {
            return 'regex';
        }
        return true;
    }

    /**
     * Validate unique in database
     */
    private function validateUnique($field, $value, $params) {
        if (empty($value)) return true;

        global $wpdb;

        $table = isset($params[0]) ? $wpdb->prefix . $params[0] : '';
        $column = isset($params[1]) ? $params[1] : $field;
        $except_id = isset($params[2]) ? (int) $params[2] : null;

        if (empty($table)) return true;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s",
            $value
        );

        if ($except_id) {
            $query .= $wpdb->prepare(" AND id != %d", $except_id);
        }

        $count = (int) $wpdb->get_var($query);

        if ($count > 0) {
            return 'unique';
        }
        return true;
    }

    /**
     * Validate exists in database
     */
    private function validateExists($field, $value, $params) {
        if (empty($value)) return true;

        global $wpdb;

        $table = isset($params[0]) ? $wpdb->prefix . $params[0] : '';
        $column = isset($params[1]) ? $params[1] : 'id';

        if (empty($table)) return true;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s",
            $value
        ));

        if ($count === 0) {
            return 'exists';
        }
        return true;
    }

    /**
     * Validate confirmed field (field_confirmation)
     */
    private function validateConfirmed($field, $value, $params) {
        if (empty($value)) return true;

        $confirmation_field = $field . '_confirmation';
        $confirmation_value = isset($this->data[$confirmation_field]) ? $this->data[$confirmation_field] : null;

        if ($value !== $confirmation_value) {
            return 'confirmed';
        }
        return true;
    }

    /**
     * Validate same as another field
     */
    private function validateSame($field, $value, $params) {
        if (empty($value)) return true;

        $other_field = isset($params[0]) ? $params[0] : '';
        $other_value = isset($this->data[$other_field]) ? $this->data[$other_field] : null;

        if ($value !== $other_value) {
            return "same:$other_field";
        }
        return true;
    }

    /**
     * Validate different from another field
     */
    private function validateDifferent($field, $value, $params) {
        if (empty($value)) return true;

        $other_field = isset($params[0]) ? $params[0] : '';
        $other_value = isset($this->data[$other_field]) ? $this->data[$other_field] : null;

        if ($value === $other_value) {
            return "different:$other_field";
        }
        return true;
    }

    /**
     * Validate nullable field (allows null)
     */
    private function validateNullable($field, $value, $params) {
        // This is handled by skipping validation if value is null
        return true;
    }

    /**
     * Validate sometimes (only validate if present)
     */
    private function validateSometimes($field, $value, $params) {
        // This is handled by checking if field exists
        return true;
    }

    // ===========================================
    // Static Helper Methods
    // ===========================================

    /**
     * Quick validation helper
     *
     * @param array $data  Data to validate
     * @param array $rules Validation rules
     * @return SC_API_Validator
     */
    public static function make($data, $rules) {
        $validator = new self();
        $validator->setData($data);
        $validator->setRules($rules);
        $validator->validate();
        return $validator;
    }

    /**
     * Get human-readable error messages
     *
     * @return array
     */
    public function getReadableErrors() {
        $messages = [
            'required' => 'The %s field is required.',
            'email' => 'The %s must be a valid email address.',
            'string' => 'The %s must be a string.',
            'integer' => 'The %s must be an integer.',
            'numeric' => 'The %s must be a number.',
            'boolean' => 'The %s must be true or false.',
            'array' => 'The %s must be an array.',
            'date' => 'The %s must be a valid date.',
            'datetime' => 'The %s must be a valid datetime.',
            'url' => 'The %s must be a valid URL.',
            'phone' => 'The %s must be a valid phone number.',
            'unique' => 'The %s has already been taken.',
            'exists' => 'The selected %s is invalid.',
            'confirmed' => 'The %s confirmation does not match.',
            'regex' => 'The %s format is invalid.',
        ];

        $readable = [];
        foreach ($this->errors as $field => $errors) {
            $readable[$field] = [];
            foreach ($errors as $error) {
                // Parse error type and params
                $parts = explode(':', $error);
                $type = $parts[0];
                $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

                // Get message template
                $template = isset($messages[$type]) ? $messages[$type] : "The %s is invalid.";

                // Handle parameterized messages
                if ($type === 'min') {
                    $template = 'The %s must be at least ' . $params[0] . ' characters.';
                } elseif ($type === 'max') {
                    $template = 'The %s may not be greater than ' . $params[0] . ' characters.';
                } elseif ($type === 'between') {
                    $template = 'The %s must be between ' . $params[0] . ' and ' . $params[1] . '.';
                } elseif ($type === 'in') {
                    $template = 'The selected %s is invalid.';
                }

                $readable[$field][] = sprintf($template, str_replace('_', ' ', $field));
            }
        }

        return $readable;
    }
}
