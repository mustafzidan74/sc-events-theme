<?php
/**
 * SC Events API Router
 *
 * Main router that handles all API requests and routing
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_API_Router {

    /**
     * Registered routes
     * @var array
     */
    private $routes = [];

    /**
     * Middleware stack
     * @var array
     */
    private $middleware = [];

    /**
     * Current request data
     * @var array
     */
    private $request = [];

    /**
     * Authenticated user data
     * @var array|null
     */
    private $user = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->registerMiddleware();
        $this->registerRoutes();
    }

    /**
     * Register global middleware
     */
    private function registerMiddleware() {
        $this->middleware = [
            new SC_CORS_Middleware(),
            new SC_Log_Middleware()
        ];
    }

    /**
     * Register all routes
     */
    private function registerRoutes() {
        // ==========================================
        // Auth Routes
        // ==========================================
        $this->addRoute('POST', '/auth/login', 'SC_Auth_Endpoint@login', ['rate_limit' => 'auth']);
        $this->addRoute('POST', '/auth/register', 'SC_Auth_Endpoint@register', ['rate_limit' => 'auth']);
        $this->addRoute('POST', '/auth/forgot-password', 'SC_Auth_Endpoint@forgotPassword', ['rate_limit' => 'auth']);
        $this->addRoute('POST', '/auth/reset-password', 'SC_Auth_Endpoint@resetPassword', ['rate_limit' => 'auth']);
        $this->addRoute('POST', '/auth/logout', 'SC_Auth_Endpoint@logout', ['auth' => true]);
        $this->addRoute('POST', '/auth/refresh', 'SC_Auth_Endpoint@refresh', ['auth' => true]);
        $this->addRoute('GET', '/auth/me', 'SC_Auth_Endpoint@me', ['auth' => true]);
        $this->addRoute('PUT', '/auth/profile', 'SC_Auth_Endpoint@updateProfile', ['auth' => true]);
        $this->addRoute('PUT', '/auth/password', 'SC_Auth_Endpoint@changePassword', ['auth' => true]);

        // ==========================================
        // Events Routes (Public - Published only)
        // ==========================================
        $this->addRoute('GET', '/events', 'SC_Events_Endpoint@index');
        $this->addRoute('GET', '/events/{id}', 'SC_Events_Endpoint@show');

        // ==========================================
        // Categories Routes (Public)
        // ==========================================
        $this->addRoute('GET', '/categories', 'SC_Categories_Endpoint@index');
        $this->addRoute('GET', '/categories/{id}', 'SC_Categories_Endpoint@show');

        // ==========================================
        // Speakers Routes (Public)
        // ==========================================
        $this->addRoute('GET', '/speakers', 'SC_Speakers_Endpoint@index');
        $this->addRoute('GET', '/speakers/{id}', 'SC_Speakers_Endpoint@show');

        // ==========================================
        // Organizers Routes (Public)
        // ==========================================
        $this->addRoute('GET', '/organizers', 'SC_Organizers_Endpoint@index');
        $this->addRoute('GET', '/organizers/{id}', 'SC_Organizers_Endpoint@show');

        // ==========================================
        // Attendees Routes (Registration)
        // ==========================================
        $this->addRoute('POST', '/attendees/register', 'SC_Attendees_Endpoint@register'); // Public registration
        $this->addRoute('GET', '/attendees/{ticket_code}', 'SC_Attendees_Endpoint@show'); // Get by ticket code
        $this->addRoute('GET', '/attendees/my-tickets', 'SC_Attendees_Endpoint@myTickets'); // User's tickets

        // ==========================================
        // Companies Routes (Company Registration)
        // ==========================================
        $this->addRoute('POST', '/companies/register', 'SC_Companies_Endpoint@register'); // Public registration
        $this->addRoute('GET', '/companies/{company_code}', 'SC_Companies_Endpoint@show'); // Get by company code
        // Lists contact emails, so managers only.
        $this->addRoute('GET', '/companies', 'SC_Companies_Endpoint@index', ['auth' => true, 'role' => 'manager']); // List companies for event
        // Was open to anyone who knew or guessed a code.
        $this->addRoute('PUT', '/companies/{company_code}', 'SC_Companies_Endpoint@update', ['auth' => true, 'role' => 'manager']); // Update company

        // ==========================================
        // Booths Routes
        // ==========================================
        $this->addRoute('GET', '/booths', 'SC_Booths_Endpoint@index'); // List booths for event
        $this->addRoute('GET', '/booths/{id}', 'SC_Booths_Endpoint@show'); // Get booth details

        // Booth Types
        $this->addRoute('GET', '/booth-types', 'SC_Booths_Endpoint@types'); // List booth types
        $this->addRoute('GET', '/booth-types/{id}', 'SC_Booths_Endpoint@showType'); // Get booth type

        // Booth Bookings
        $this->addRoute('GET', '/booth-bookings', 'SC_Booths_Endpoint@bookings'); // List bookings
        $this->addRoute('GET', '/booth-bookings/{id}', 'SC_Booths_Endpoint@showBooking'); // Get booking
        $this->addRoute('POST', '/booth-bookings', 'SC_Booths_Endpoint@createBooking'); // Create booking

        // ==========================================
        // Certificates Routes
        // ==========================================
        $this->addRoute('GET', '/certificates/verify/{code}', 'SC_Certificates_Endpoint@verify'); // Public verification
        $this->addRoute('GET', '/certificates/check', 'SC_Certificates_Endpoint@check'); // Check eligibility
        $this->addRoute('POST', '/certificates/request', 'SC_Certificates_Endpoint@request'); // Request certificate
        $this->addRoute('GET', '/certificates/my', 'SC_Certificates_Endpoint@myCertificates'); // User's certificates

        // ==========================================
        // Coupons Routes
        // ==========================================
        $this->addRoute('GET', '/coupons', 'SC_Coupons_Endpoint@index'); // List coupons for event
        $this->addRoute('POST', '/coupons/validate', 'SC_Coupons_Endpoint@validateCoupon'); // Validate coupon
        $this->addRoute('POST', '/coupons/apply', 'SC_Coupons_Endpoint@apply', ['auth' => true]); // Apply coupon (uses up a code)
        $this->addRoute('GET', '/coupons/check/{code}', 'SC_Coupons_Endpoint@check'); // Quick check

        // ==========================================
        // Utility Routes
        // ==========================================
        $this->addRoute('GET', '/health', 'SC_Base_Endpoint@health'); // Public health check
        $this->addRoute('GET', '/version', 'SC_Base_Endpoint@version'); // Public version info
    }

    /**
     * Add a route
     *
     * @param string $method   HTTP method
     * @param string $path     Route path
     * @param string $handler  Handler (Controller@method)
     * @param array  $options  Route options
     */
    public function addRoute($method, $path, $handler, $options = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'options' => $options
        ];
    }

    /**
     * Handle the incoming request
     */
    public function handle_request() {
        // Get request method
        $method = $_SERVER['REQUEST_METHOD'];

        // Handle preflight requests
        if ($method === 'OPTIONS') {
            $this->handlePreflight();
            return;
        }

        // Get route from query parameter or parse from URL
        $route = $this->getRoute();

        // Parse request
        $this->parseRequest();

        // Run global middleware
        foreach ($this->middleware as $middleware) {
            $middleware->handle($this->request);
        }

        // Find matching route
        $matched = $this->matchRoute($method, $route);

        if (!$matched) {
            SC_API_Response::notFound('Endpoint not found');
        }

        // Extract route info
        $route_info = $matched['route'];
        $params = $matched['params'];

        // Check rate limiting
        if (isset($route_info['options']['rate_limit'])) {
            $this->checkRateLimit($route_info['options']['rate_limit']);
        } else {
            $this->checkRateLimit('default');
        }

        // Check authentication
        if (isset($route_info['options']['auth']) && $route_info['options']['auth']) {
            $this->authenticate();
        } else {
            // Try to authenticate but don't require it (for optional auth)
            $this->tryAuthenticate();
        }

        // Check role
        if (isset($route_info['options']['role'])) {
            $this->checkRole($route_info['options']['role']);
        }

        // Execute handler
        $this->executeHandler($route_info['handler'], $params);
    }

    /**
     * Get the requested route
     *
     * @return string
     */
    private function getRoute() {
        // Check query parameter first
        if (isset($_GET['route'])) {
            return '/' . ltrim(sanitize_text_field($_GET['route']), '/');
        }

        // Parse from request URI
        $uri = $_SERVER['REQUEST_URI'];
        $base_path = '/wp-content/themes/sc_events/api.php';

        // Remove base path and query string
        if (strpos($uri, $base_path) !== false) {
            $uri = substr($uri, strpos($uri, $base_path) + strlen($base_path));
        }

        // Remove query string
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        return '/' . ltrim($uri, '/') ?: '/';
    }

    /**
     * Parse the request
     */
    private function parseRequest() {
        $this->request = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'query' => $_GET,
            'body' => $this->getRequestBody(),
            'headers' => $this->getHeaders(),
            'ip' => SC_API_Rate_Limiter::getClientIp()
        ];
    }

    /**
     * Get request body
     *
     * @return array
     */
    private function getRequestBody() {
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

        if (strpos($content_type, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $_POST;
        }

        // For PUT/PATCH, parse input
        if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
            parse_str(file_get_contents('php://input'), $data);
            return $data;
        }

        return [];
    }

    /**
     * Get request headers
     *
     * @return array
     */
    private function getHeaders() {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Match route with path
     *
     * @param string $method HTTP method
     * @param string $path   Request path
     * @return array|null
     */
    private function matchRoute($method, $path) {
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->routeToRegex($route['path']);

            if (preg_match($pattern, $path, $matches)) {
                // Extract named parameters
                $params = [];
                preg_match_all('/\{([^}]+)\}/', $route['path'], $param_names);

                foreach ($param_names[1] as $i => $name) {
                    $params[$name] = isset($matches[$i + 1]) ? $matches[$i + 1] : null;
                }

                return [
                    'route' => $route,
                    'params' => $params
                ];
            }
        }

        return null;
    }

    /**
     * Convert route path to regex
     *
     * @param string $path Route path
     * @return string Regex pattern
     */
    private function routeToRegex($path) {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Check rate limiting
     *
     * @param string $type Rate limit type
     */
    private function checkRateLimit($type) {
        $limiter = SC_API_Rate_Limiter::create($type);
        $identifier = $this->request['ip'];

        // Add user ID to identifier if authenticated
        if ($this->user) {
            $identifier .= '_' . $this->user['sub'];
        }

        if (!$limiter->check($identifier)) {
            $retry_after = $limiter->getRetryAfter($identifier);
            SC_API_Response::rateLimitExceeded($retry_after);
        }

        $limiter->setHeaders($identifier);
    }

    /**
     * Authenticate the request (required)
     */
    private function authenticate() {
        $auth = new SC_API_Auth();
        $token = $auth->getBearerToken();

        if (!$token) {
            SC_API_Response::unauthorized('No token provided');
        }

        $payload = $auth->verifyToken($token);

        if (!$payload) {
            SC_API_Response::unauthorized('Invalid or expired token');
        }

        $this->user = $payload;
    }

    /**
     * Try to authenticate (optional - doesn't fail if no token)
     */
    private function tryAuthenticate() {
        $auth = new SC_API_Auth();
        $token = $auth->getBearerToken();

        if ($token) {
            $payload = $auth->verifyToken($token);
            if ($payload) {
                $this->user = $payload;
            }
        }
    }

    /**
     * Check user role
     *
     * @param string $required_role Required role
     */
    private function checkRole($required_role) {
        if (!$this->user) {
            SC_API_Response::unauthorized('Authentication required');
        }

        $user_role = isset($this->user['role']) ? $this->user['role'] : 'user';
        $role_hierarchy = ['user' => 1, 'scanner' => 2, 'manager' => 3, 'admin' => 4];

        $user_level = isset($role_hierarchy[$user_role]) ? $role_hierarchy[$user_role] : 0;
        $required_level = isset($role_hierarchy[$required_role]) ? $role_hierarchy[$required_role] : 999;

        if ($user_level < $required_level) {
            SC_API_Response::forbidden('Insufficient permissions');
        }
    }

    /**
     * Execute the route handler
     *
     * @param string $handler Handler string (Controller@method)
     * @param array  $params  Route parameters
     */
    private function executeHandler($handler, $params) {
        list($class, $method) = explode('@', $handler);

        if (!class_exists($class)) {
            SC_API_Response::serverError('Handler class not found: ' . $class);
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            SC_API_Response::serverError('Handler method not found: ' . $method);
        }

        // Inject request data and user
        $controller->setRequest($this->request);
        $controller->setParams($params);
        $controller->setUser($this->user);

        // Call the method
        call_user_func([$controller, $method]);
    }

    /**
     * Handle preflight (OPTIONS) requests
     */
    private function handlePreflight() {
        $cors = new SC_CORS_Middleware();
        $cors->handle([]);
        http_response_code(204);
        exit;
    }
}
