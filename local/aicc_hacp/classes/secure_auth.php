<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class secure_auth {
    
    /**
     * Validate HACP request with proper signature verification
     */
    public static function validate_request(array $post, array $server): bool {
        $shared_secret = get_config('local_aicc_hacp', 'shared_secret');
        if (empty($shared_secret)) {
            local_aicc_hacp_log(102, 'No shared secret configured', '', '', '', 0);
            return false;
        }

        // Get signature from header
        $signature = $server['HTTP_X_AICC_SIGN'] ?? '';
        if (empty($signature)) {
            local_aicc_hacp_log(102, 'Missing signature header', '', '', '', 0);
            return false;
        }

        // Remove signature from POST data for validation
        unset($post['signature']);
        
        // Sort parameters and build query string
        ksort($post);
        $data = http_build_query($post);
        
        // Generate expected signature
        $expected_signature = hash_hmac('sha256', $data, $shared_secret);
        
        // Use timing-safe comparison
        $is_valid = hash_equals($expected_signature, $signature);
        
        if (!$is_valid) {
            local_aicc_hacp_log(102, 'Signature validation failed', '', '', $data, 0);
        }
        
        return $is_valid;
    }
    
    /**
     * Generate secure launch token for course access
     */
    public static function generate_launch_token(int $courseid, int $scormid, string $external_lms_id): string {
        $secret = get_config('local_aicc_hacp', 'launch_token_secret');
        if (empty($secret)) {
            throw new \moodle_exception('error_no_token_secret', 'local_aicc_hacp');
        }
        
        $payload = [
            'courseid' => $courseid,
            'scormid' => $scormid,
            'external_lms' => $external_lms_id,
            'issued_at' => time(),
            'expires_at' => time() + (get_config('local_aicc_hacp', 'launch_token_ttl') ?: 3600)
        ];
        
        // Use URL-safe base64 encoding
        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        
        // Generate signature
        $signature = hash_hmac('sha256', $header . '.' . $payload_encoded, $secret, true);
        $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        
        return $header . '.' . $payload_encoded . '.' . $signature_encoded;
    }
    
    /**
     * Validate launch token
     */
    public static function validate_launch_token(string $token, int $cmid = 0): ?array {
        $secret = get_config('local_aicc_hacp', 'launch_token_secret');
        if (empty($secret)) {
            error_log("No launch_token_secret configured");
            return null;
        }
        
        // Decode URL-encoded token
        $token = urldecode($token);
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            error_log("Invalid token format - not 3 parts");
            return null;
        }
        
        list($header, $payload, $signature_encoded) = $parts;
        
        // Try both URL-safe base64 and regular base64 for backward compatibility
        $signature_decoded = false;
        $expected_signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        
        // Try URL-safe base64 first (new format)
        $signature_decoded = @base64_decode(strtr($signature_encoded, '-_', '+/') . str_repeat('=', 4 - strlen($signature_encoded) % 4));
        if ($signature_decoded && hash_equals($signature_decoded, $expected_signature)) {
            // Valid with URL-safe
        } else {
            // Try regular base64 (old format)
            $signature_decoded = @base64_decode($signature_encoded);
            if (!$signature_decoded || !hash_equals($signature_decoded, $expected_signature)) {
                error_log("Token signature validation failed - both formats tried");
                return null;
            }
        }
        
        // Decode payload - try both formats
        $payload_decoded = @base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', 4 - strlen($payload) % 4));
        if (!$payload_decoded) {
            $payload_decoded = @base64_decode($payload);
        }
        
        if (!$payload_decoded) {
            error_log("Failed to decode token payload - both formats tried");
            return null;
        }
        
        $payload_data = json_decode($payload_decoded, true);
        if (!$payload_data || !is_array($payload_data)) {
            error_log("Failed to decode token payload JSON");
            return null;
        }
        
        // Check expiration
        if (isset($payload_data['expires_at']) && $payload_data['expires_at'] < time()) {
            error_log("Token expired: " . date('Y-m-d H:i:s', $payload_data['expires_at']) . " vs " . date('Y-m-d H:i:s', time()));
            return null;
        }
        
        error_log("Token validated successfully for scormid: " . ($payload_data['scormid'] ?? 'N/A'));
        return $payload_data;
    }
    
    /**
     * Validate external LMS origin
     */
    public static function validate_origin(string $origin): bool {
        $allowed_origins = get_config('local_aicc_hacp', 'allowed_origins');
        if (empty($allowed_origins)) {
            return true; // Allow all if not configured
        }
        
        $origins = array_map('trim', explode(',', $allowed_origins));
        return in_array($origin, $origins);
    }
    
    /**
     * Rate limiting check
     */
    public static function check_rate_limit(string $identifier): bool {
        $max_requests = get_config('local_aicc_hacp', 'max_requests_per_minute');
        if ($max_requests <= 0) {
            return true; // No rate limiting
        }
        
        $cache = \cache::make('local_aicc_hacp', 'ratelimit');
        $key = 'ratelimit_' . md5($identifier);
        
        $count = $cache->get($key);
        if ($count === false) {
            $count = 0;
        }
        
        if ($count >= $max_requests) {
            return false;
        }
        
        $cache->set($key, $count + 1, 60);
        return true;
    }
}
