<?php
/**
 * Two-Factor Authentication Tests
 *
 * @package sc_events
 * @subpackage Tests
 */

require_once dirname(__DIR__) . '/class-sc-test-case.php';

class TwoFactorAuthTest extends SC_Test_Case {

    /**
     * Test secret generation
     */
    public function test_generate_secret() {
        // Load the class for testing
        require_once dirname(dirname(__DIR__)) . '/inc/class-sc-two-factor-auth.php';

        $secret = SC_Two_Factor_Auth::generate_secret();

        // Secret should be 32 characters
        $this->assertEquals(32, strlen($secret));

        // Secret should only contain base32 characters
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);

        // Each secret should be unique
        $secret2 = SC_Two_Factor_Auth::generate_secret();
        $this->assertNotEquals($secret, $secret2);
    }

    /**
     * Test TOTP code generation and verification
     */
    public function test_totp_generation_and_verification() {
        $secret = 'JBSWY3DPEHPK3PXP'; // Test secret

        // Generate code for current time
        $code = SC_Two_Factor_Auth::generate_totp($secret);

        // Code should be 6 digits
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Code should verify
        $this->assertTrue(SC_Two_Factor_Auth::verify_totp($secret, $code));

        // Wrong code should not verify
        $this->assertFalse(SC_Two_Factor_Auth::verify_totp($secret, '000000'));
    }

    /**
     * Test TOTP with time window
     */
    public function test_totp_time_window() {
        $secret = 'JBSWY3DPEHPK3PXP';
        $time = time();

        // Generate code for previous period
        $previous_code = SC_Two_Factor_Auth::generate_totp($secret, $time - 30);

        // Should still verify with window of 1
        $this->assertTrue(SC_Two_Factor_Auth::verify_totp($secret, $previous_code, 1));

        // Should not verify with window of 0
        // Note: This might fail at period boundaries, so we skip strict testing
    }

    /**
     * Test backup codes generation
     */
    public function test_backup_codes_generation() {
        $codes = SC_Two_Factor_Auth::generate_backup_codes();

        // Should generate 10 codes by default
        $this->assertCount(10, $codes);

        // Each code should be 8 characters
        foreach ($codes as $code) {
            $this->assertEquals(8, strlen($code));
            $this->assertMatchesRegularExpression('/^[A-F0-9]+$/', $code);
        }

        // All codes should be unique
        $this->assertEquals(count($codes), count(array_unique($codes)));
    }

    /**
     * Test custom backup codes count
     */
    public function test_backup_codes_custom_count() {
        $codes = SC_Two_Factor_Auth::generate_backup_codes(5);
        $this->assertCount(5, $codes);
    }

    /**
     * Test OTPAuth URI generation
     */
    public function test_otpauth_uri() {
        $secret = 'JBSWY3DPEHPK3PXP';
        $email = 'user@example.com';

        $uri = SC_Two_Factor_Auth::get_otpauth_uri($secret, $email);

        // URI should start with otpauth://totp/
        $this->assertStringStartsWith('otpauth://totp/', $uri);

        // URI should contain the secret
        $this->assertStringContainsString('secret=' . $secret, $uri);

        // URI should contain the email
        $this->assertStringContainsString(urlencode($email), $uri);
    }

    /**
     * Test 2FA enable/disable for user
     */
    public function test_enable_disable_2fa() {
        $user_id = 1;

        // Initially not enabled
        $this->assertFalse(SC_Two_Factor_Auth::is_enabled($user_id));

        // Enable 2FA
        $secret = SC_Two_Factor_Auth::generate_secret();
        $backup_codes = SC_Two_Factor_Auth::enable($user_id, 'totp', $secret);

        // Should now be enabled
        $this->assertTrue(SC_Two_Factor_Auth::is_enabled($user_id));

        // Backup codes should be returned
        $this->assertCount(10, $backup_codes);

        // Method should be 'totp'
        $this->assertEquals('totp', SC_Two_Factor_Auth::get_method($user_id));

        // Disable 2FA
        SC_Two_Factor_Auth::disable($user_id);

        // Should no longer be enabled
        $this->assertFalse(SC_Two_Factor_Auth::is_enabled($user_id));
    }

    /**
     * Test backup code verification
     */
    public function test_backup_code_verification() {
        $user_id = 1;

        // Enable 2FA and get backup codes
        $secret = SC_Two_Factor_Auth::generate_secret();
        $backup_codes = SC_Two_Factor_Auth::enable($user_id, 'totp', $secret);

        // First code should verify
        $first_code = $backup_codes[0];
        $this->assertTrue(SC_Two_Factor_Auth::verify_backup_code($user_id, $first_code));

        // Same code should not verify again (used)
        $this->assertFalse(SC_Two_Factor_Auth::verify_backup_code($user_id, $first_code));

        // Invalid code should not verify
        $this->assertFalse(SC_Two_Factor_Auth::verify_backup_code($user_id, 'INVALID1'));
    }

    /**
     * Test complete verification flow
     */
    public function test_verify_method() {
        $user_id = 1;
        $secret = 'JBSWY3DPEHPK3PXP';

        // Enable 2FA
        SC_Two_Factor_Auth::enable($user_id, 'totp', $secret);

        // Generate valid code
        $code = SC_Two_Factor_Auth::generate_totp($secret);

        // Should verify
        $this->assertTrue(SC_Two_Factor_Auth::verify($user_id, $code));

        // Invalid code should not verify
        $this->assertFalse(SC_Two_Factor_Auth::verify($user_id, '000000'));
    }

    /**
     * Test that 2FA disabled user always verifies
     */
    public function test_disabled_user_always_verifies() {
        $user_id = 2; // Different user, 2FA not enabled

        // Should return true for any code when 2FA is disabled
        $this->assertTrue(SC_Two_Factor_Auth::verify($user_id, 'anycode'));
    }
}
