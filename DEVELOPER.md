# SC Events Developer Documentation

## Architecture Overview

SC Events is a comprehensive event management system built as a WordPress theme. It uses custom database tables for better performance and flexibility.

### Directory Structure

```
sc_events/
├── inc/
│   ├── api/                    # REST API endpoints
│   │   └── class-sc-rest-api.php
│   ├── admin-dashboard/        # Dashboard functionality
│   │   ├── dashboard-init.php
│   │   ├── *-ajax-handlers.php # AJAX handlers by module
│   │   └── pages/              # Dashboard page templates
│   ├── database/               # Database layer
│   │   ├── loader.php          # Loads all models
│   │   ├── schema.php          # Table definitions
│   │   ├── class-sc-base-model.php
│   │   └── class-sc-*.php      # Model classes
│   ├── payment-gateways/       # Payment integrations
│   └── public-frontend/        # Frontend functionality
├── assets/
│   ├── admin-dashboard/        # Dashboard assets
│   └── frontend/               # Frontend assets
├── templates/                  # Reusable templates
└── tests/                      # PHPUnit tests
```

## Database Models

### Base Model (SC_Base_Model)

Abstract base class providing:
- Validation with multiple rule types
- Transaction support (begin, commit, rollback)
- Data sanitization
- Cache utilities
- Soft delete support

### Core Models

| Model | Table | Description |
|-------|-------|-------------|
| SC_Event | sc_events | Event data and settings |
| SC_Ticket | sc_tickets | Ticket types per event |
| SC_Attendee | sc_attendees | Registered attendees |
| SC_Transaction | sc_transactions | Payment records |
| SC_Checkin | sc_checkins | Check-in/out logs |
| SC_Coupon | sc_coupons | Discount codes |
| SC_Speaker | sc_speakers | Event speakers |
| SC_Certificate | sc_certificates | Issued certificates |

### Usage Examples

```php
// Get single event
$event = SC_Event::get($event_id);

// Get all events with filters
$events = SC_Event::get_all([
    'status' => 'publish',
    'upcoming_only' => true,
    'limit' => 10
]);

// Create event with validation
if (SC_Event::validate($data)) {
    $id = SC_Event::create($data);
} else {
    $errors = SC_Event::get_validation_errors();
}

// Transaction example
SC_Event::transaction(function() use ($data) {
    $event_id = SC_Event::create($data);
    SC_Ticket::create(['event_id' => $event_id, ...]);
    return $event_id;
});
```

## REST API

### Endpoints

Base URL: `/wp-json/sc-events/v1/`

#### Events
- `GET /events` - List events
- `GET /events/{id}` - Get single event
- `POST /events` - Create event
- `PUT /events/{id}` - Update event
- `DELETE /events/{id}` - Delete event
- `GET /events/{id}/stats` - Get event statistics

#### Attendees
- `GET /events/{id}/attendees` - List attendees
- `GET /attendees/{id}` - Get attendee
- `GET /attendees/ticket/{code}` - Get by ticket code
- `POST /events/{id}/attendees` - Register attendee
- `PUT /attendees/{id}` - Update attendee

#### Check-in
- `POST /checkin` - Check in attendee
- `POST /checkout` - Check out attendee

#### Authentication
- `POST /auth/token` - Generate API token
- `GET /auth/verify` - Verify token

### Authentication

#### API Key
```
X-API-Key: your-api-key
```

#### Bearer Token
```
Authorization: Bearer your-token
```

## Security Features

### Two-Factor Authentication

```php
// Check if 2FA enabled
SC_Two_Factor_Auth::is_enabled($user_id);

// Verify TOTP code
SC_Two_Factor_Auth::verify($user_id, $code);

// Generate new secret
$secret = SC_Two_Factor_Auth::generate_secret();
```

### Error Logging

```php
// Log messages at different levels
sc_log_debug('Debug message', ['context' => 'data']);
sc_log_info('Info message');
sc_log_warning('Warning message');
sc_log_error('Error message');
sc_log_critical('Critical error');
```

### Activity Logging

```php
// Log custom activity
sc_log_activity('event', 'custom_action', [
    'object_type' => 'event',
    'object_id' => $event_id,
    'description' => 'Custom action performed'
]);
```

## Frontend Components

### Loading States

```javascript
// Show loading spinner
SCLoading.show('#container');

// Show skeleton loader
SCLoading.skeleton('#container', 3);

// Hide loading
SCLoading.hide('#container');
```

### Toast Notifications

```javascript
SCToast.success('Operation completed');
SCToast.error('An error occurred');
SCToast.warning('Please check your input');
SCToast.info('Processing...');
```

### Form Validation

```javascript
const validator = new SCFormValidator('#myForm', {
    name: { required: true, minLength: 2 },
    email: { required: true, email: true },
    phone: { phone: true }
});

if (validator.validate()) {
    // Submit form
}
```

## Hooks & Filters

### Actions

```php
// Event created
do_action('sc_event_created', $event_id, $event_data);

// Attendee registered
do_action('sc_attendee_registered', $attendee_id, $attendee_data);

// Check-in completed
do_action('sc_attendee_checked_in', $attendee_id, $attendee_data);

// Payment completed
do_action('sc_payment_completed', $transaction_id, $transaction_data);
```

### Filters

```php
// Modify event data before save
add_filter('sc_event_pre_save', function($data) {
    // Modify $data
    return $data;
});

// Modify ticket price
add_filter('sc_ticket_price', function($price, $ticket_id) {
    return $price;
}, 10, 2);
```

## Testing

### Running Tests

```bash
cd tests
./vendor/bin/phpunit
```

### Writing Tests

```php
class MyFeatureTest extends SC_Test_Case {

    public function test_something() {
        $event = $this->create_mock_event();
        $this->assertEquals('Test Event', $event->title);
    }
}
```

## Performance Optimization

### Database Optimizer

```php
// Run all optimizations
SC_Database_Optimizer::run_optimizations();

// Get cached stats
$stats = SC_Database_Optimizer::get_dashboard_stats();
```

### Asset Minification

```php
// Minify all assets
SC_Asset_Manager::minify_all_assets();

// Clear cache
SC_Asset_Manager::clear_cache();
```

## Localization

The theme supports both Arabic and English with RTL:

```php
// Check RTL
if (sc_is_rtl()) {
    // RTL specific code
}

// Get current language
$lang = sc_get_current_language();
```

## Version History

- **2.3.0** - Added REST API, 2FA, Error/Activity Logging
- **2.2.0** - Added asset optimization, UX enhancements
- **2.1.0** - Custom database tables, performance improvements
- **2.0.0** - Modular architecture rewrite
- **1.0.0** - Initial release
