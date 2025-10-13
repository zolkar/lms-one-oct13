<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class auth {
    public static function validate_request(array $post, array $server): bool {
        $shared_secret = get_config('local_aicc_hacp', 'shared_secret');
        if (empty($shared_secret)) {
            return false;
        }

        $signature = $server['HTTP_X_AICC_SIGN'] ?? '';
        if (empty($signature)) {
            return false;
        }

        // The signature is not part of the POST data, so we don't need to remove it.
        ksort($post);
        $data = http_build_query($post);

        $expected_signature = hash_hmac('sha256', $data, $shared_secret);

        return hash_equals($expected_signature, $signature);
    }
}
