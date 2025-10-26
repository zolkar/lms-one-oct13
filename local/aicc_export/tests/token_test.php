<?php

defined('MOODLE_INTERNAL') || die();

class local_aicc_export_token_testcase extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
        set_config('secret_key', 'test_secret', 'local_aicc_export');
    }

    public function test_sign_and_validate_token() {
        $payload = [
            'user_id' => 123,
            'course_id' => 456,
            'expires_at' => time() + 3600,
        ];

        $token = \local_aicc_export\token::sign($payload);
        $this->assertIsString($token);

        $validated_payload = \local_aicc_export\token::validate($token);
        $this->assertEquals($payload, $validated_payload);
    }

    public function test_validate_expired_token() {
        $payload = [
            'user_id' => 123,
            'course_id' => 456,
            'expires_at' => time() - 3600,
        ];

        $token = \local_aicc_export\token::sign($payload);
        $this->assertIsString($token);

        $validated_payload = \local_aicc_export\token::validate($token);
        $this->assertNull($validated_payload);
    }

    public function test_validate_invalid_token() {
        $invalid_token = 'invalid.token.string';
        $validated_payload = \local_aicc_export\token::validate($invalid_token);
        $this->assertNull($validated_payload);
    }
}
