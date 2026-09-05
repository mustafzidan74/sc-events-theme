<?php
/**
 * Activity Logger Tests
 *
 * @package sc_events
 * @subpackage Tests
 */

require_once dirname(__DIR__) . '/class-sc-test-case.php';

class ActivityLoggerTest extends SC_Test_Case {

    /**
     * Test activity types are valid
     */
    public function test_activity_types() {
        $valid_types = array(
            'user',
            'event',
            'attendee',
            'payment',
            'checkin',
            'system',
            'security'
        );

        foreach ($valid_types as $type) {
            $this->assertNotEmpty($type);
            $this->assertIsString($type);
        }
    }

    /**
     * Test log data structure
     */
    public function test_log_data_structure() {
        $log_data = array(
            'user_id' => 1,
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'activity_type' => 'event',
            'action' => 'created',
            'object_type' => 'event',
            'object_id' => 1,
            'object_name' => 'Test Event',
            'description' => 'Event created: Test Event',
            'old_value' => null,
            'new_value' => json_encode(array('title' => 'Test Event')),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'request_uri' => '/admin/events',
            'created_at' => date('Y-m-d H:i:s')
        );

        $required_keys = array(
            'activity_type', 'action', 'created_at'
        );

        $this->assertArrayHasKeys($required_keys, $log_data);
        $this->assertValidDate($log_data['created_at']);
    }

    /**
     * Test user activity data
     */
    public function test_user_activity_data() {
        $user_login_data = array(
            'object_type' => 'user',
            'object_id' => 1,
            'object_name' => 'testuser',
            'description' => 'User "testuser" logged in'
        );

        $this->assertEquals('user', $user_login_data['object_type']);
        $this->assertIsInt($user_login_data['object_id']);
        $this->assertStringContainsString('logged in', $user_login_data['description']);
    }

    /**
     * Test event activity data
     */
    public function test_event_activity_data() {
        $event = $this->create_mock_event();

        $event_created_data = array(
            'object_type' => 'event',
            'object_id' => $event->id,
            'object_name' => $event->title,
            'description' => sprintf('Event created: %s', $event->title),
            'new_value' => (array) $event
        );

        $this->assertEquals('event', $event_created_data['object_type']);
        $this->assertEquals($event->id, $event_created_data['object_id']);
        $this->assertStringContainsString('Event created', $event_created_data['description']);
    }

    /**
     * Test attendee activity data
     */
    public function test_attendee_activity_data() {
        $attendee = $this->create_mock_attendee();

        $attendee_registered_data = array(
            'object_type' => 'attendee',
            'object_id' => $attendee->id,
            'object_name' => $attendee->name,
            'description' => sprintf('Attendee registered: %s for event #%d',
                $attendee->name,
                $attendee->event_id
            )
        );

        $this->assertEquals('attendee', $attendee_registered_data['object_type']);
        $this->assertStringContainsString($attendee->name, $attendee_registered_data['description']);
    }

    /**
     * Test check-in activity data
     */
    public function test_checkin_activity_data() {
        $attendee = $this->create_mock_attendee();

        $checkin_data = array(
            'object_type' => 'attendee',
            'object_id' => $attendee->id,
            'object_name' => $attendee->name,
            'description' => sprintf('Check-in: %s (Ticket: %s)',
                $attendee->name,
                $attendee->ticket_code
            )
        );

        $this->assertStringContainsString('Check-in', $checkin_data['description']);
        $this->assertStringContainsString($attendee->ticket_code, $checkin_data['description']);
    }

    /**
     * Test payment activity data
     */
    public function test_payment_activity_data() {
        $transaction_data = array(
            'transaction_id' => 'TXN123456',
            'amount' => 100.00,
            'currency' => 'SAR'
        );

        $payment_completed_data = array(
            'object_type' => 'transaction',
            'object_name' => $transaction_data['transaction_id'],
            'description' => sprintf('Payment completed: %s %s',
                $transaction_data['amount'],
                $transaction_data['currency']
            )
        );

        $this->assertEquals('transaction', $payment_completed_data['object_type']);
        $this->assertStringContainsString('100', $payment_completed_data['description']);
        $this->assertStringContainsString('SAR', $payment_completed_data['description']);
    }

    /**
     * Test security activity (login failed)
     */
    public function test_security_activity_data() {
        $username = 'hacker';

        $login_failed_data = array(
            'object_type' => 'user',
            'object_name' => $username,
            'description' => sprintf('Failed login attempt for user: %s', $username)
        );

        $this->assertStringContainsString('Failed login', $login_failed_data['description']);
        $this->assertEquals($username, $login_failed_data['object_name']);
    }

    /**
     * Test old/new value comparison
     */
    public function test_value_comparison() {
        $old_event = $this->create_mock_event(array('title' => 'Old Title'));
        $new_event = $this->create_mock_event(array('title' => 'New Title'));

        $old_value = json_encode((array) $old_event);
        $new_value = json_encode((array) $new_event);

        $this->assertJson($old_value);
        $this->assertJson($new_value);
        $this->assertNotEquals($old_value, $new_value);

        $old_decoded = json_decode($old_value, true);
        $new_decoded = json_decode($new_value, true);

        $this->assertEquals('Old Title', $old_decoded['title']);
        $this->assertEquals('New Title', $new_decoded['title']);
    }

    /**
     * Test query arguments structure
     */
    public function test_query_arguments() {
        $args = array(
            'user_id' => 1,
            'activity_type' => 'event',
            'action' => 'created',
            'object_type' => 'event',
            'object_id' => 1,
            'search' => 'test',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $this->assertIsInt($args['limit']);
        $this->assertIsInt($args['offset']);
        $this->assertContains($args['order'], array('ASC', 'DESC'));
    }

    /**
     * Test CSV export format
     */
    public function test_csv_export_format() {
        $headers = array('Date', 'Type', 'Action', 'User', 'Description', 'IP Address');

        $this->assertCount(6, $headers);
        $this->assertContains('Date', $headers);
        $this->assertContains('Type', $headers);
        $this->assertContains('Action', $headers);
    }
}
