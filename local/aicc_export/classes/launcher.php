<?php

namespace local_aicc_export;

defined('MOODLE_INTERNAL') || die();

class launcher {
    public static function sign_token(array $payload): string {
        $secret = get_config('local_aicc_export', 'launch_token_secret');
        if (empty($secret)) {
            throw new \moodle_exception('error_missing_token_secret', 'local_aicc_export');
        }
        $json = json_encode($payload);
        $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $b64, $secret, true);
        $sig_b64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return $b64 . '.' . $sig_b64;
    }

    public static function validate_token(string $token) {
        $secret = get_config('local_aicc_export', 'launch_token_secret');
        if (empty($secret)) {
            return false;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        list($b64, $sig) = $parts;
        $calc = rtrim(strtr(base64_encode(hash_hmac('sha256', $b64, $secret, true)), '+/', '-_'), '=');

        if (!hash_equals($calc, $sig)) {
            return false;
        }

        $payload = json_decode(base64_decode(strtr($b64, '-_', '+/')), true);
        if (empty($payload) || !is_array($payload) || ($payload['expires_at'] ?? 0) < time()) {
            return false;
        }

        return $payload;
    }
}
