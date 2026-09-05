<?php
/**
 * SC Base Model Class
 *
 * Abstract base class for all database models
 * Provides validation, transactions, and common utilities
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SC_Base_Model {

    /**
     * Table name (without prefix)
     * @var string
     */
    protected static $table_name;

    /**
     * Primary key column
     * @var string
     */
    protected static $primary_key = 'id';

    /**
     * Fillable columns (for mass assignment protection)
     * @var array
     */
    protected static $fillable = array();

    /**
     * Validation rules
     * @var array
     */
    protected static $validation_rules = array();

    /**
     * Last validation errors
     * @var array
     */
    protected static $validation_errors = array();

    /**
     * Transaction nesting level
     * @var int
     */
    private static $transaction_level = 0;

    /**
     * ===========================================
     * TABLE UTILITIES
     * ===========================================
     */

    /**
     * Get full table name with prefix
     *
     * @return string
     */
    public static function get_table() {
        global $wpdb;
        return $wpdb->prefix . static::$table_name;
    }

    /**
     * ===========================================
     * VALIDATION
     * ===========================================
     */

    /**
     * Validate data against rules
     *
     * @param array $data Data to validate
     * @param array $rules Optional custom rules (overrides default)
     * @return bool True if valid
     */
    public static function validate($data, $rules = null) {
        static::$validation_errors = array();

        if ($rules === null) {
            $rules = static::$validation_rules;
        }

        foreach ($rules as $field => $field_rules) {
            $value = isset($data[$field]) ? $data[$field] : null;

            foreach ($field_rules as $rule => $params) {
                $error = self::validate_rule($field, $value, $rule, $params);
                if ($error) {
                    static::$validation_errors[$field][] = $error;
                }
            }
        }

        return empty(static::$validation_errors);
    }

    /**
     * Validate single rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule name
     * @param mixed $params Rule parameters
     * @return string|null Error message or null
     */
    protected static function validate_rule($field, $value, $rule, $params) {
        $label = ucwords(str_replace('_', ' ', $field));

        switch ($rule) {
            case 'required':
                if ($params && (is_null($value) || $value === '')) {
                    return sprintf(__('%s is required.', 'sc_events'), $label);
                }
                break;

            case 'string':
                if (!is_null($value) && !is_string($value)) {
                    return sprintf(__('%s must be a string.', 'sc_events'), $label);
                }
                break;

            case 'integer':
            case 'int':
                if (!is_null($value) && $value !== '' && !is_numeric($value)) {
                    return sprintf(__('%s must be a number.', 'sc_events'), $label);
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return sprintf(__('%s must be a valid email address.', 'sc_events'), $label);
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return sprintf(__('%s must be a valid URL.', 'sc_events'), $label);
                }
                break;

            case 'min_length':
                if (!empty($value) && strlen($value) < $params) {
                    return sprintf(__('%s must be at least %d characters.', 'sc_events'), $label, $params);
                }
                break;

            case 'max_length':
                if (!empty($value) && strlen($value) > $params) {
                    return sprintf(__('%s must not exceed %d characters.', 'sc_events'), $label, $params);
                }
                break;

            case 'min':
                if (!is_null($value) && $value !== '' && floatval($value) < $params) {
                    return sprintf(__('%s must be at least %s.', 'sc_events'), $label, $params);
                }
                break;

            case 'max':
                if (!is_null($value) && $value !== '' && floatval($value) > $params) {
                    return sprintf(__('%s must not exceed %s.', 'sc_events'), $label, $params);
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    return sprintf(__('%s must be one of: %s.', 'sc_events'), $label, implode(', ', $params));
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    return sprintf(__('%s must be a valid date.', 'sc_events'), $label);
                }
                break;

            case 'date_format':
                if (!empty($value)) {
                    $date = DateTime::createFromFormat($params, $value);
                    if (!$date || $date->format($params) !== $value) {
                        return sprintf(__('%s must be in the format %s.', 'sc_events'), $label, $params);
                    }
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($params, $value)) {
                    return sprintf(__('%s has an invalid format.', 'sc_events'), $label);
                }
                break;

            case 'unique':
                if (!empty($value)) {
                    global $wpdb;
                    $table = static::get_table();
                    $exclude_id = isset($params['exclude_id']) ? $params['exclude_id'] : 0;

                    $query = $wpdb->prepare(
                        "SELECT COUNT(*) FROM $table WHERE $field = %s AND " . static::$primary_key . " != %d",
                        $value,
                        $exclude_id
                    );

                    if ($wpdb->get_var($query) > 0) {
                        return sprintf(__('%s already exists.', 'sc_events'), $label);
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public static function get_validation_errors() {
        return static::$validation_errors;
    }

    /**
     * Get first validation error message
     *
     * @return string|null
     */
    public static function get_first_error() {
        foreach (static::$validation_errors as $field => $errors) {
            if (!empty($errors)) {
                return $errors[0];
            }
        }
        return null;
    }

    /**
     * ===========================================
     * TRANSACTIONS
     * ===========================================
     */

    /**
     * Begin database transaction
     *
     * @return bool
     */
    public static function begin_transaction() {
        global $wpdb;

        if (self::$transaction_level === 0) {
            $wpdb->query('START TRANSACTION');
        }

        self::$transaction_level++;

        return true;
    }

    /**
     * Commit database transaction
     *
     * @return bool
     */
    public static function commit() {
        global $wpdb;

        if (self::$transaction_level > 0) {
            self::$transaction_level--;

            if (self::$transaction_level === 0) {
                $wpdb->query('COMMIT');
            }
        }

        return true;
    }

    /**
     * Rollback database transaction
     *
     * @return bool
     */
    public static function rollback() {
        global $wpdb;

        if (self::$transaction_level > 0) {
            self::$transaction_level = 0;
            $wpdb->query('ROLLBACK');
        }

        return true;
    }

    /**
     * Execute callback within a transaction
     *
     * @param callable $callback
     * @return mixed Result of callback
     * @throws Exception
     */
    public static function transaction($callback) {
        self::begin_transaction();

        try {
            $result = call_user_func($callback);
            self::commit();
            return $result;
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * ===========================================
     * SANITIZATION
     * ===========================================
     */

    /**
     * Sanitize data for database insertion
     *
     * @param array $data Raw data
     * @param array $types Optional type hints
     * @return array Sanitized data
     */
    public static function sanitize($data, $types = array()) {
        $sanitized = array();

        foreach ($data as $key => $value) {
            // Skip if not in fillable (mass assignment protection)
            if (!empty(static::$fillable) && !in_array($key, static::$fillable)) {
                continue;
            }

            $type = isset($types[$key]) ? $types[$key] : 'string';

            switch ($type) {
                case 'int':
                case 'integer':
                    $sanitized[$key] = intval($value);
                    break;

                case 'float':
                case 'decimal':
                    $sanitized[$key] = floatval($value);
                    break;

                case 'bool':
                case 'boolean':
                    $sanitized[$key] = $value ? 1 : 0;
                    break;

                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;

                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;

                case 'html':
                    $sanitized[$key] = wp_kses_post($value);
                    break;

                case 'json':
                    if (is_array($value) || is_object($value)) {
                        $sanitized[$key] = wp_json_encode($value);
                    } else {
                        $sanitized[$key] = $value;
                    }
                    break;

                case 'date':
                    if (!empty($value)) {
                        $sanitized[$key] = date('Y-m-d', strtotime($value));
                    } else {
                        $sanitized[$key] = null;
                    }
                    break;

                case 'datetime':
                    if (!empty($value)) {
                        $sanitized[$key] = date('Y-m-d H:i:s', strtotime($value));
                    } else {
                        $sanitized[$key] = null;
                    }
                    break;

                case 'time':
                    if (!empty($value)) {
                        $sanitized[$key] = date('H:i:s', strtotime($value));
                    } else {
                        $sanitized[$key] = null;
                    }
                    break;

                case 'raw':
                    $sanitized[$key] = $value;
                    break;

                case 'string':
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * ===========================================
     * COMMON QUERIES
     * ===========================================
     */

    /**
     * Count records
     *
     * @param array $conditions WHERE conditions
     * @return int
     */
    public static function count($conditions = array()) {
        global $wpdb;
        $table = static::get_table();

        $where = '1=1';
        $values = array();

        foreach ($conditions as $field => $value) {
            if (is_null($value)) {
                $where .= " AND $field IS NULL";
            } else {
                $where .= " AND $field = %s";
                $values[] = $value;
            }
        }

        $query = "SELECT COUNT(*) FROM $table WHERE $where";

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return intval($wpdb->get_var($query));
    }

    /**
     * Check if record exists
     *
     * @param int $id Record ID
     * @return bool
     */
    public static function exists($id) {
        global $wpdb;
        $table = static::get_table();
        $pk = static::$primary_key;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM $table WHERE $pk = %d LIMIT 1",
            $id
        ));
    }

    /**
     * Delete record by ID
     *
     * @param int $id Record ID
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $table = static::get_table();
        $pk = static::$primary_key;

        $result = $wpdb->delete($table, array($pk => $id), array('%d'));

        return $result !== false;
    }

    /**
     * Soft delete (set status to deleted/inactive)
     *
     * @param int $id Record ID
     * @param string $status_column Status column name
     * @param string $deleted_status Status value for deleted
     * @return bool
     */
    public static function soft_delete($id, $status_column = 'status', $deleted_status = 'deleted') {
        global $wpdb;
        $table = static::get_table();
        $pk = static::$primary_key;

        $result = $wpdb->update(
            $table,
            array($status_column => $deleted_status),
            array($pk => $id),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * ===========================================
     * CACHE UTILITIES
     * ===========================================
     */

    /**
     * Get cache key for model
     *
     * @param string $suffix Cache key suffix
     * @return string
     */
    protected static function get_cache_key($suffix = '') {
        return 'sc_' . static::$table_name . ($suffix ? '_' . $suffix : '');
    }

    /**
     * Get from cache
     *
     * @param string $key Cache key
     * @return mixed|false
     */
    protected static function cache_get($key) {
        return wp_cache_get(self::get_cache_key($key), 'sc_events');
    }

    /**
     * Set cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration in seconds
     * @return bool
     */
    protected static function cache_set($key, $data, $expiration = 3600) {
        return wp_cache_set(self::get_cache_key($key), $data, 'sc_events', $expiration);
    }

    /**
     * Delete from cache
     *
     * @param string $key Cache key
     * @return bool
     */
    protected static function cache_delete($key) {
        return wp_cache_delete(self::get_cache_key($key), 'sc_events');
    }

    /**
     * Clear all cache for this model
     *
     * @return bool
     */
    public static function cache_clear() {
        return wp_cache_flush();
    }
}
