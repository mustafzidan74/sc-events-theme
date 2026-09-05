<?php
/**
 * SC Events API Response Handler
 *
 * Standardizes all API responses with consistent format
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_API_Response {

    /**
     * Send a success response
     *
     * @param mixed  $data    Response data
     * @param string $message Success message
     * @param int    $code    HTTP status code
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        self::send($code, [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => gmdate('c')
        ]);
    }

    /**
     * Send a created response (201)
     *
     * @param mixed  $data    Created resource data
     * @param string $message Success message
     */
    public static function created($data = null, $message = 'Resource created successfully') {
        self::success($data, $message, 201);
    }

    /**
     * Send a no content response (204)
     */
    public static function noContent() {
        http_response_code(204);
        exit;
    }

    /**
     * Send an error response
     *
     * @param string $message    Error message
     * @param int    $code       HTTP status code
     * @param array  $errors     Detailed errors
     * @param string $error_code Machine-readable error code
     */
    public static function error($message, $code = 400, $errors = null, $error_code = null) {
        self::send($code, [
            'success' => false,
            'message' => $message,
            'error_code' => $error_code ?? self::getErrorCode($code),
            'errors' => $errors,
            'timestamp' => gmdate('c')
        ]);
    }

    /**
     * Send a validation error response (422)
     *
     * @param array  $errors  Validation errors
     * @param string $message Error message
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Send an unauthorized response (401)
     *
     * @param string $message Error message
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401, null, 'UNAUTHORIZED');
    }

    /**
     * Send a forbidden response (403)
     *
     * @param string $message Error message
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403, null, 'FORBIDDEN');
    }

    /**
     * Send a not found response (404)
     *
     * @param string $message Error message
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404, null, 'NOT_FOUND');
    }

    /**
     * Send a method not allowed response (405)
     *
     * @param array $allowed Allowed methods
     */
    public static function methodNotAllowed($allowed = []) {
        if (!empty($allowed)) {
            header('Allow: ' . implode(', ', $allowed));
        }
        self::error('Method not allowed', 405, null, 'METHOD_NOT_ALLOWED');
    }

    /**
     * Send a rate limit exceeded response (429)
     *
     * @param int $retry_after Seconds until retry is allowed
     */
    public static function rateLimitExceeded($retry_after = 60) {
        header('Retry-After: ' . $retry_after);
        self::error('Rate limit exceeded. Please try again later.', 429, null, 'RATE_LIMIT_EXCEEDED');
    }

    /**
     * Send an internal server error response (500)
     *
     * @param string $message Error message
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500, null, 'INTERNAL_ERROR');
    }

    /**
     * Send a paginated response
     *
     * @param array $items    Items array
     * @param int   $total    Total items count
     * @param int   $page     Current page
     * @param int   $per_page Items per page
     * @param string $message Success message
     */
    public static function paginated($items, $total, $page = 1, $per_page = 10, $message = 'Success') {
        $total_pages = ceil($total / $per_page);

        self::success([
            'items' => $items,
            'pagination' => [
                'total' => (int) $total,
                'page' => (int) $page,
                'per_page' => (int) $per_page,
                'total_pages' => (int) $total_pages,
                'has_more' => $page < $total_pages,
                'has_previous' => $page > 1
            ]
        ], $message);
    }

    /**
     * Send the response
     *
     * @param int   $code HTTP status code
     * @param array $data Response data
     */
    private static function send($code, $data) {
        // Clean any previous output
        if (ob_get_level()) {
            ob_clean();
        }

        // Set headers
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-API-Version: ' . SC_API_VERSION);

        // Remove null values from response
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        // Output JSON
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Get error code from HTTP status
     *
     * @param int $code HTTP status code
     * @return string Error code
     */
    private static function getErrorCode($code) {
        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE'
        ];

        return isset($codes[$code]) ? $codes[$code] : 'ERROR';
    }
}
