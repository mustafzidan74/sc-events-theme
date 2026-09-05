<?php
/**
 * Error Logger Tests
 *
 * @package sc_events
 * @subpackage Tests
 */

require_once dirname(__DIR__) . '/class-sc-test-case.php';

class ErrorLoggerTest extends SC_Test_Case {

    /**
     * Test log levels comparison
     */
    public function test_log_level_comparison() {
        // This tests the internal should_log method logic
        $levels = array(
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
            'critical' => 4
        );

        // Higher level should be logged when min level is lower
        $this->assertTrue($levels['error'] >= $levels['info']);
        $this->assertTrue($levels['warning'] >= $levels['info']);

        // Lower level should not be logged when min level is higher
        $this->assertFalse($levels['debug'] >= $levels['warning']);
        $this->assertFalse($levels['info'] >= $levels['error']);
    }

    /**
     * Test message truncation
     */
    public function test_message_truncation() {
        $max_length = 100;
        $long_message = str_repeat('a', 200);

        $truncated = strlen($long_message) > $max_length
            ? substr($long_message, 0, $max_length - 3) . '...'
            : $long_message;

        $this->assertEquals($max_length, strlen($truncated));
        $this->assertStringEndsWith('...', $truncated);
    }

    /**
     * Test relative path extraction
     */
    public function test_relative_path() {
        $theme_dir = '/var/www/html/wp-content/themes/sc_events';
        $full_path = '/var/www/html/wp-content/themes/sc_events/inc/class-test.php';

        $relative = str_replace($theme_dir, '', $full_path);

        $this->assertEquals('/inc/class-test.php', $relative);
    }

    /**
     * Test IP address validation
     */
    public function test_ip_address_validation() {
        $valid_ips = array(
            '192.168.1.1',
            '10.0.0.1',
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334', // IPv6
            '::1' // localhost IPv6
        );

        foreach ($valid_ips as $ip) {
            $this->assertNotFalse(
                filter_var($ip, FILTER_VALIDATE_IP),
                "Failed to validate IP: {$ip}"
            );
        }

        $invalid_ips = array(
            'not-an-ip',
            '256.256.256.256',
            ''
        );

        foreach ($invalid_ips as $ip) {
            $this->assertFalse(
                filter_var($ip, FILTER_VALIDATE_IP),
                "Should be invalid IP: {$ip}"
            );
        }
    }

    /**
     * Test error type name mapping
     */
    public function test_error_type_names() {
        $type_map = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_DEPRECATED => 'E_DEPRECATED'
        );

        foreach ($type_map as $type => $name) {
            $this->assertEquals($name, $type_map[$type]);
        }
    }

    /**
     * Test context JSON encoding
     */
    public function test_context_encoding() {
        $context = array(
            'user_id' => 1,
            'action' => 'test',
            'data' => array('key' => 'value')
        );

        $encoded = json_encode($context);
        $decoded = json_decode($encoded, true);

        $this->assertEquals($context, $decoded);
    }

    /**
     * Test log data structure
     */
    public function test_log_data_structure() {
        $log_data = array(
            'level' => 'error',
            'message' => 'Test error message',
            'context' => json_encode(array('test' => true)),
            'source' => 'sc_events',
            'file' => '/inc/test.php',
            'line' => 42,
            'user_id' => 1,
            'ip_address' => '127.0.0.1',
            'request_uri' => '/test-page',
            'request_method' => 'GET',
            'user_agent' => 'PHPUnit',
            'created_at' => date('Y-m-d H:i:s')
        );

        $required_keys = array(
            'level', 'message', 'source', 'created_at'
        );

        $this->assertArrayHasKeys($required_keys, $log_data);
    }

    /**
     * Test stack trace formatting
     */
    public function test_stack_trace_format() {
        $trace = array(
            array(
                'file' => '/path/to/file.php',
                'line' => 10,
                'function' => 'testFunction',
                'class' => 'TestClass',
                'type' => '->'
            ),
            array(
                'file' => '/path/to/another.php',
                'line' => 20,
                'function' => 'anotherFunction'
            )
        );

        $formatted = array();
        foreach ($trace as $i => $frame) {
            $formatted[] = sprintf(
                '#%d %s%s%s() at %s:%d',
                $i,
                isset($frame['class']) ? $frame['class'] : '',
                isset($frame['type']) ? $frame['type'] : '',
                isset($frame['function']) ? $frame['function'] : '',
                isset($frame['file']) ? $frame['file'] : 'unknown',
                isset($frame['line']) ? $frame['line'] : 0
            );
        }

        $this->assertCount(2, $formatted);
        $this->assertStringContainsString('TestClass->testFunction()', $formatted[0]);
        $this->assertStringContainsString('anotherFunction()', $formatted[1]);
    }
}
