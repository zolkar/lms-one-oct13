<?php

namespace local_aicc_export;

defined('MOODLE_INTERNAL') || die();

class token {

    /**
     * @var string $secret The secret key used for signing tokens.
     */
    private static $secret;

    /**
     * Initializes the secret key.
     */
    private static function init_secret() {
        if (empty(self::$secret)) {
            self::$secret = get_config('local_aicc_export', 'secret_key');
            if (empty(self::$secret)) {
                // Fallback to a default secret if not configured, though a configured secret is recommended.
                self::$secret = 'default_secret_key';
            }
        }
    }

    /**
     * Signs a payload and returns a token.
     *
     * @param array $payload The data to be encoded in the token.
     * @return string The generated token.
     */
    public static function sign(array $payload): string {
        self::init_secret();
        $encoded_header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $encoded_payload = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$encoded_header}.{$encoded_payload}", self::$secret, true);
        $encoded_signature = base64_encode($signature);
        return "{$encoded_header}.{$encoded_payload}.{$encoded_signature}";
    }

    /**
     * Validates a token and returns the payload if valid.
     *
     * @param string $token The token to validate.
     * @return array|null The payload if the token is valid, otherwise null.
     */
    public static function validate(string $token): ?array {
        self::init_secret();
        list($encoded_header, $encoded_payload, $encoded_signature) = explode('.', $token);
        $signature = base64_decode($encoded_signature);
        $expected_signature = hash_hmac('sha256', "{$encoded_header}.{$encoded_payload}", self::$secret, true);

        if (!hash_equals($expected_signature, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($encoded_payload), true);
        if (empty($payload['expires_at']) || $payload['expires_at'] < time()) {
            return null;
        }

        return $payload;
    }
}
