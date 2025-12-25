<?php
defined('ABSPATH') || exit;

class JWT_API_JWT {
    public static function generate(array $payload, string $secret): string {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + 3600;
        $payload_enc = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$payload_enc", $secret, true);
        return "$header.$payload_enc." . base64_encode($signature);
    }

    public static function validate(string $jwt, string $secret): bool {
        [$h, $p, $s] = explode('.', $jwt);
        $valid = base64_encode(hash_hmac('sha256', "$h.$p", $secret, true));
        if (!hash_equals($valid, $s)) return false;
        $payload = json_decode(base64_decode($p), true);
        return isset($payload['exp']) && $payload['exp'] > time();
    }
}
