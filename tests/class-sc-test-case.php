<?php
/**
 * SC Events Base Test Case
 *
 * Provides common functionality for all SC Events tests
 *
 * @package sc_events
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Base test case class
 */
class SC_Test_Case extends TestCase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();

        // Reset mock storage
        global $mock_options, $mock_user_meta, $mock_transients;
        $mock_options = array();
        $mock_user_meta = array();
        $mock_transients = array();
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Helper: Create a mock event
     *
     * @param array $overrides Override default values
     * @return object
     */
    protected function create_mock_event($overrides = array()) {
        $defaults = array(
            'id' => 1,
            'title' => 'Test Event',
            'slug' => 'test-event',
            'description' => 'Test event description',
            'start_date' => date('Y-m-d', strtotime('+1 week')),
            'end_date' => date('Y-m-d', strtotime('+1 week')),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'location_type' => 'venue',
            'venue_name' => 'Test Venue',
            'venue_city' => 'Riyadh',
            'venue_country' => 'SA',
            'total_capacity' => 100,
            'total_sold' => 0,
            'total_revenue' => 0,
            'status' => 'publish',
            'author_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Helper: Create a mock attendee
     *
     * @param array $overrides Override default values
     * @return object
     */
    protected function create_mock_attendee($overrides = array()) {
        $defaults = array(
            'id' => 1,
            'event_id' => 1,
            'ticket_id' => 1,
            'user_id' => null,
            'name' => 'Test Attendee',
            'email' => 'attendee@example.com',
            'phone' => '+966500000000',
            'ticket_code' => 'TEST' . strtoupper(bin2hex(random_bytes(4))),
            'ticket_name' => 'General Admission',
            'ticket_price' => 100.00,
            'payment_status' => 'success',
            'amount_paid' => 100.00,
            'checked_in' => 0,
            'checked_in_at' => null,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        );

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Helper: Create a mock ticket
     *
     * @param array $overrides Override default values
     * @return object
     */
    protected function create_mock_ticket($overrides = array()) {
        $defaults = array(
            'id' => 1,
            'event_id' => 1,
            'name' => 'General Admission',
            'description' => 'Standard entry ticket',
            'price' => 100.00,
            'quantity' => 100,
            'sold' => 0,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'sale_start' => date('Y-m-d H:i:s'),
            'sale_end' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        );

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Helper: Assert array has expected keys
     *
     * @param array $expected_keys Keys to check
     * @param array $array Array to check
     */
    protected function assertArrayHasKeys($expected_keys, $array) {
        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $array, "Array missing expected key: {$key}");
        }
    }

    /**
     * Helper: Assert date format
     *
     * @param string $date Date string to check
     * @param string $format Expected format (default: Y-m-d H:i:s)
     */
    protected function assertValidDate($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        $this->assertNotFalse($d, "Invalid date format: {$date}");
        $this->assertEquals($date, $d->format($format));
    }

    /**
     * Helper: Assert email format
     *
     * @param string $email Email to check
     */
    protected function assertValidEmail($email) {
        $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL), "Invalid email: {$email}");
    }
}
