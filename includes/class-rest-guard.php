<?php
defined('ABSPATH') || exit;

class JWT_API_REST_Guard {

    private array $allowed_namespaces = ['jwt-api-auth', 'custom-api'];

    public function __construct() {
        add_filter('rest_authentication_errors', [$this, 'check']);
        add_action('rest_api_init', [$this, 'register_token_endpoint']);
    }

    public function register_token_endpoint() {
        register_rest_route('jwt-api-auth/v1', '/token', [
            'methods' => 'POST',
            'callback' => [$this, 'token'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function token($request) {
        global $wpdb;
        $domain = sanitize_text_field($request['domain']);
        $key = sanitize_text_field($request['api_key']);
        $table = $wpdb->prefix . 'jwt_domains';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE domain = %s AND api_key = %s",
            $domain, $key
        ));

        if (!$row) {
            return new WP_Error('invalid', 'Clé ou domaine invalide', ['status' => 401]);
        }

        $jwt = JWT_API_JWT::generate(['domain' => $domain], $row->api_key);
        return ['token' => $jwt, 'expires_in' => 3600];
    }

    public function check($result) {
        if (!empty($result)) return $result;

        $route = $_SERVER['REQUEST_URI'] ?? '';
        if (!array_filter($this->allowed_namespaces, fn($n) => str_contains($route, $n))) {
            return true;
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return new WP_Error('jwt_missing', 'JWT manquant', ['status' => 401]);
        }

        $token = substr($header, 7);
        global $wpdb;
        $table = $wpdb->prefix . 'jwt_domains';
        $domains = $wpdb->get_results("SELECT * FROM $table");

        foreach ($domains as $d) {
            if (JWT_API_JWT::validate($token, $d->api_key)) {
                return true;
            }
        }

        return new WP_Error('jwt_invalid', 'JWT invalide', ['status' => 401]);
    }
}
