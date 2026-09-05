# SC Events Module System

## Overview

The SC Events platform uses a modular architecture that allows administrators to enable or disable features based on their needs. This document describes the module system and how to use it.

## Architecture

### Directory Structure

```
modules/
├── core/
│   ├── init.php              # Module system initialization
│   ├── class-base-module.php # Base class for all modules
│   └── class-module-loader.php # Module loader singleton
├── sessions/
│   └── module.php            # Sessions/CME tracking module
├── venues/
│   └── module.php            # Venue & zone management
├── payments/
│   └── module.php            # Payment gateway module
├── saudi-integration/
│   └── module.php            # Saudi market features (ZATCA, SMS)
└── [other-modules]/
    └── module.php
```

### Database Table

Module settings are stored in `wp_sc_module_settings`:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| module_id | varchar(100) | Unique module identifier |
| is_enabled | tinyint(1) | 1 = enabled, 0 = disabled |
| settings | longtext | JSON module-specific settings |
| enabled_by | bigint | User who last changed status |
| enabled_at | datetime | When status was last changed |
| created_at | datetime | Record creation time |
| updated_at | datetime | Last update time |

## Available Modules

### Core Modules (Always Enabled)
- **Events** - Core event management (cannot be disabled)
- **Attendees** - Attendee management (cannot be disabled)
- **Tickets** - Ticket types and sales (cannot be disabled)

### Optional Modules

| Module ID | Name (EN) | Name (AR) | Description |
|-----------|-----------|-----------|-------------|
| sessions | Sessions Management | إدارة الجلسات | Multi-hall system with CME/CPD tracking |
| venues | Venue Management | إدارة الأماكن | Zone management, gates, control room |
| certificates | Certificates | الشهادات | Attendance certificate issuance |
| payments | Payment Gateways | بوابات الدفع | Electronic payment integration |
| chat | Chat System | نظام الدردشة | Direct communication with attendees |
| referrals | Referral System | نظام الإحالات | Points and rewards for referrals |
| staff | Staff Management | إدارة الموظفين | Staff and shift management |
| emergency | Emergency Management | إدارة الطوارئ | Emergency and evacuation system |
| crowd | Crowd Management | إدارة الحشود | Crowd flow monitoring |
| booths | Booth Management | إدارة الأجنحة | Exhibition booth management |
| invoices | E-Invoicing | الفوترة الإلكترونية | ZATCA-compliant invoices |
| sms | SMS Notifications | رسائل SMS | Text message notifications |

## API Reference

### PHP Functions

```php
// Check if a module is enabled
sc_is_module_enabled('sessions'); // Returns: true/false

// Enable a module
sc_enable_module('sessions'); // Returns: true on success

// Disable a module
sc_disable_module('sessions'); // Returns: true on success

// Get module loader instance
$loader = sc_modules();

// Get a specific module instance
$sessions_module = sc_module('sessions');

// Get all modules with status
$all_modules = sc_modules()->get_all_modules_status();
```

### Graceful Degradation Pattern

When writing code that depends on optional modules, use this pattern:

```php
// Check if module is enabled before using its features
$sessions_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sessions');

if ($sessions_enabled) {
    // Use sessions module features
    $session = SC_Session::get($session_id);
}
```

### JavaScript Integration

Pass module availability to JavaScript:

```php
// In PHP
$sessions_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sessions');
?>
<script>
const sessionsEnabled = <?php echo $sessions_enabled ? 'true' : 'false'; ?>;

// Only make AJAX calls if module is enabled
if (sessionsEnabled) {
    loadSessionsForEvent(eventId);
}
</script>
```

## Module Manager Dashboard

Administrators can enable/disable modules from:
**Dashboard > Settings > Module Manager**

URL: `/event-manager-dashboard/module-manager`

Features:
- Visual toggle for each module
- Module descriptions in Arabic and English
- Status badges (Enabled/Disabled)
- Bulk save functionality
- Statistics (total, enabled, disabled counts)

## Creating a New Module

### 1. Create Module Directory

```
modules/my-module/
├── module.php
├── class-my-feature.php
└── assets/
    ├── js/
    └── css/
```

### 2. Create Module Class

```php
<?php
// modules/my-module/module.php

if (!defined('ABSPATH')) {
    exit;
}

class SC_My_Module extends SC_Base_Module {

    protected $id = 'my-module';
    protected $name = 'My Module';
    protected $description = 'Description of my module';
    protected $version = '1.0.0';

    public function init() {
        // Initialize module
        add_action('init', array($this, 'register_hooks'));
    }

    public function register_hooks() {
        // Add your action/filter hooks here
    }

    public function get_info() {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'icon' => 'fa-puzzle-piece',
            'category' => 'optional',
        );
    }
}

// Register module
sc_modules()->register_module(new SC_My_Module());
```

### 3. Add to Sidebar (Optional)

Update `template-parts/dashboard/components/dashboard-sidebar.php`:

```php
<?php
$my_module_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('my-module');

if ($my_module_enabled):
?>
<li>
    <a href="<?php echo $dashboard_url; ?>my-module">
        <i class="fa fa-puzzle-piece"></i>
        <span><?php echo $is_rtl ? 'وحدتي' : 'My Module'; ?></span>
    </a>
</li>
<?php endif; ?>
```

## Best Practices

### 1. Always Check Module Availability
```php
// Good
if (sc_is_module_enabled('sessions')) {
    // Use sessions feature
}

// Bad - Will error if module disabled
$session = SC_Session::get($id);
```

### 2. Use Graceful Degradation
Hide UI elements for disabled modules instead of showing errors.

### 3. Default to Enabled
When function doesn't exist, assume enabled for backward compatibility:
```php
$enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('my-module');
```

### 4. Log Module State Changes
The system automatically logs who enabled/disabled modules and when.

### 5. Cache Module Status
For performance, module status is cached. Changes take effect on next page load.

## AJAX Endpoints

### Update Module Status
```javascript
// Action: sc_update_modules
// Nonce: sc_module_manager
// Data: { changes: JSON array }

$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'sc_update_modules',
        nonce: 'sc_module_manager_nonce',
        changes: JSON.stringify([
            { module_id: 'sessions', is_enabled: true },
            { module_id: 'venues', is_enabled: false }
        ])
    }
});
```

### Get Module Status
```javascript
// Action: sc_get_module_status
// Nonce: sc_module_manager

$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'sc_get_module_status',
        nonce: 'sc_module_manager_nonce',
        module_id: 'sessions' // Optional, omit for all modules
    }
});
```

## Troubleshooting

### Module Not Loading
1. Check if module file exists in `modules/[module-id]/module.php`
2. Verify module class extends `SC_Base_Module`
3. Check `sc_modules()->register_module()` is called
4. Enable WP_DEBUG to see errors

### Module Status Not Updating
1. Clear browser cache
2. Check `wp_sc_module_settings` table exists
3. Verify user has administrator role
4. Check AJAX nonce is valid

### Sidebar Links Not Hiding
1. Verify `sc_is_module_enabled` check is correct
2. Check for PHP syntax errors in conditional
3. Clear any page caches

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0.0 | 2024-12 | Initial module system |
| 2.1.0 | 2024-12 | Added sessions, venues modules |
| 2.2.0 | 2024-12 | Added module manager dashboard |
