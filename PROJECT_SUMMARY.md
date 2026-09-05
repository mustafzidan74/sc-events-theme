# SC Events - Event Manager Dashboard
## Project Summary | ملخص المشروع

---

## Overview | نظرة عامة

A custom WordPress theme providing a comprehensive Event Manager Dashboard for the Eventin plugin. The dashboard allows Event Managers to manage events, speakers, organizers, attendees, and discount coupons through a modern, Arabic-friendly interface.

ثيم ووردبريس مخصص يوفر لوحة تحكم شاملة لإدارة الفعاليات مع إضافة Eventin. تتيح اللوحة للمديرين إدارة الفعاليات والمتحدثين والمنظمين والحضور وكوبونات الخصم.

---

## Architecture | الهيكل

### Directory Structure
```
sc_events/
├── functions.php                 # Main theme functions & includes
├── style.css                     # Theme stylesheet
├── js/
│   └── navigation.js            # Theme navigation
├── assets/
│   └── admin-dashboard/         # Dashboard assets (Iconic Template)
│       ├── bundles/             # Bundled vendor scripts
│       ├── vendor/              # Third-party libraries
│       ├── css/
│       │   ├── main.css                    # Main dashboard styles
│       │   ├── color_skins.css             # Theme color skins
│       │   ├── custom-enhancements.css     # Modern UI enhancements
│       │   └── dashboard-improvements.css  # Additional styling
│       ├── js/
│       │   ├── dashboard-core.js           # Error handling, DOM fixes
│       │   ├── dashboard-alerts.js         # SweetAlert2 wrapper
│       │   └── dashboard-init.js           # Page loader, tooltips, logout
│       └── images/              # Dashboard images
├── inc/
│   ├── custom-header.php
│   ├── template-tags.php
│   ├── template-functions.php
│   ├── customizer.php
│   ├── jetpack.php
│   ├── custom-post-types.php
│   └── admin-dashboard/         # Dashboard core files
│       ├── dashboard-init.php              # Dashboard class & routing
│       ├── ajax-handlers.php               # Core AJAX handlers
│       ├── events-ajax-handlers.php        # Events CRUD operations
│       ├── speakers-ajax-handlers.php      # Speakers & Organizers
│       ├── attendees-ajax-handlers.php     # Attendees management
│       ├── permissions.php                 # Security & access control
│       ├── performance-optimizations.php   # DB indexes & caching
│       ├── reports-functions.php           # Reporting utilities
│       └── reset-role.php                  # Role management
└── template-parts/
    └── dashboard/               # Dashboard templates
        ├── components/          # Reusable components
        │   ├── dashboard-header.php        # HTML head, CSS, nav
        │   ├── dashboard-footer.php        # JS scripts, localization
        │   └── dashboard-sidebar.php       # Sidebar menu
        ├── home.php
        ├── events.php
        ├── speakers.php
        ├── organizers.php
        ├── attendees.php
        ├── coupons.php
        ├── categories.php
        ├── reports.php
        └── settings.php
```

---

## Features | الميزات

### 1. Event Management | إدارة الفعاليات
- Create, edit, delete, duplicate events
- Event categories with color coding
- Date/time management with timezone support
- Physical and virtual event locations
- Speaker and organizer assignment
- Ticket management integration

### 2. Speaker Management | إدارة المتحدثين
- Create speakers as WordPress users (etn-speaker role)
- Profile image upload (required)
- Social media links (15+ platforms)
- Bio and designation
- Event assignment tracking

### 3. Organizer Management | إدارة المنظمين
- Create organizers as WordPress users (etn-organizer role)
- Company logo upload (required)
- Website and description
- Event assignment tracking

### 4. Attendee Management | إدارة الحضور
- View and filter attendees
- CSV import with validation
- Export to Excel/PDF
- Check-in status management
- Payment tracking

### 5. Discount Coupons | كوبونات الخصم
- Create single or bulk coupons (up to 1000)
- Percentage or fixed discount
- Per-event or global coupons
- Usage limits and expiry dates
- Export to Excel/PDF/CSV
- Auto-download on bulk creation

### 6. Reports | التقارير
- Event statistics
- Attendance tracking
- Revenue reports
- Speaker/Organizer analytics

---

## User Roles | أدوار المستخدمين

### Event Manager Role
- Custom WordPress role: `event_manager`
- Can manage own events only (IDOR protection)
- Access to frontend dashboard only
- Hidden admin bar
- Auto-redirect to dashboard after login

### Administrator
- Full access to all events
- Access to WordPress admin + frontend dashboard
- Can manage all users' events

---

## Security Implementation | تنفيذ الأمان

### 1. CSRF Protection
- All AJAX requests require nonce verification
- Nonce: `sc_dashboard_nonce`
- Logout action protected with nonce

### 2. IDOR Prevention (Insecure Direct Object Reference)
- Event ownership verification before any operation
- Attendee access through event ownership chain
- Helper functions in `permissions.php`:
  - `sc_user_can_access_event($event_id)`
  - `sc_user_can_access_attendee($attendee_id)`
  - `sc_verify_event_ownership($event_id)`
  - `sc_verify_attendee_ownership($attendee_id)`

### 3. Input Validation
- All inputs sanitized with WordPress functions
- CSV upload validation:
  - File extension check (.csv only)
  - MIME type validation
  - File size limit (5MB max)
  - Upload verification

### 4. Permission Checks
- `SC_Event_Manager_Dashboard::is_event_manager()` check on all endpoints
- Role-based capability filtering
- Eventin capability grants for event_manager role

---

## Performance Optimizations | تحسينات الأداء

### Database Indexes
File: `performance-optimizations.php`

Recommended indexes for 50,000+ records:
```sql
-- Index for event_id lookups (attendees by event)
ALTER TABLE wp_postmeta ADD INDEX idx_etn_event_id (meta_key(20), meta_value(40));

-- Index for post_author (event ownership checks)
ALTER TABLE wp_posts ADD INDEX idx_post_author_type (post_author, post_type(20), post_status(20));

-- Index for date-based queries
ALTER TABLE wp_postmeta ADD INDEX idx_etn_start_date (meta_key(20), meta_value(20));
```

To add indexes, run:
```bash
wp eval "sc_add_performance_indexes();"
```
Or visit: `/wp-admin/admin.php?action=sc_add_indexes`

### Optimized Count Queries
Instead of `posts_per_page => -1`, use:
- `sc_count_speaker_events($speaker_id)`
- `sc_count_organizer_events($organizer_id)`
- `sc_count_event_attendees($event_id)`

### Caching
- Transient-based caching for counts
- Auto-invalidation on post save/delete
- Cache TTL: 5 minutes (300 seconds)

### Batch Processing
- `sc_batch_process()` for large dataset operations
- Memory-efficient processing with `wp_cache_flush()`

---

## API Endpoints | نقاط الAPI

### Events
| Action | Handler |
|--------|---------|
| `sc_get_events_paginated` | Get events with pagination |
| `sc_create_or_update_event` | Create/update event |
| `sc_get_event_for_edit` | Get event data for editing |
| `sc_delete_event` | Delete event |
| `sc_duplicate_event` | Duplicate event |
| `sc_get_event_details` | Get event details |

### Speakers
| Action | Handler |
|--------|---------|
| `sc_get_speakers` | Get all speakers |
| `sc_get_speakers_paginated` | Get speakers with pagination |
| `sc_get_speaker` | Get single speaker |
| `sc_save_speaker` | Create/update speaker |
| `sc_delete_speaker` | Delete speaker |

### Organizers
| Action | Handler |
|--------|---------|
| `sc_get_organizers` | Get all organizers |
| `sc_get_organizers_paginated` | Get organizers with pagination |
| `sc_get_organizer` | Get single organizer |
| `sc_save_organizer` | Create/update organizer |
| `sc_delete_organizer` | Delete organizer |

### Attendees
| Action | Handler |
|--------|---------|
| `sc_get_attendees_paginated` | Get attendees with pagination |
| `sc_get_attendee` | Get single attendee |
| `sc_update_attendee_status` | Update check-in status |
| `sc_import_attendees_csv` | Import from CSV |
| `sc_export_attendees` | Export to Excel/PDF |

### Coupons
| Action | Handler |
|--------|---------|
| `sc_get_coupons_paginated` | Get coupons with pagination |
| `create_discount_coupon` | Create coupon(s) |
| `delete_coupon` | Delete single coupon |
| `delete_all_coupons` | Delete all coupons |
| `export_coupons_excel` | Export to Excel |
| `export_coupons_pdf` | Export to PDF |

### Categories
| Action | Handler |
|--------|---------|
| `sc_get_categories` | Get all categories |
| `sc_get_categories_paginated` | Get with pagination |
| `sc_get_category` | Get single category |
| `sc_save_category` | Create/update category |
| `sc_delete_category` | Delete category |

### Utility
| Action | Handler |
|--------|---------|
| `event_manager_logout` | Logout with CSRF protection |
| `sc_fix_eventin_meta` | Fix Eventin meta keys |

---

## Dashboard URLs | روابط اللوحة

Base URL: `/event-manager-dashboard/`

| Page | URL |
|------|-----|
| Home | `/event-manager-dashboard/home` |
| Events | `/event-manager-dashboard/events` |
| Speakers | `/event-manager-dashboard/speakers` |
| Organizers | `/event-manager-dashboard/organizers` |
| Attendees | `/event-manager-dashboard/attendees` |
| Coupons | `/event-manager-dashboard/coupons` |
| Categories | `/event-manager-dashboard/categories` |
| Reports | `/event-manager-dashboard/reports` |
| Settings | `/event-manager-dashboard/settings` |

---

## Integration with Eventin Plugin | التكامل مع إضافة Eventin

### Post Types
- `etn` - Events
- `etn-attendee` - Attendees
- `etn_speaker` - Speaker (post type, not used)
- `etn_schedule` - Schedules

### User Roles
- `etn-speaker` - Speaker users
- `etn-organizer` - Organizer users

### Taxonomies
- `etn_category` - Event categories

### Meta Keys (Events)
- `etn_start_date` - Event start date
- `etn_end_date` - Event end date
- `etn_start_time` - Start time
- `etn_end_time` - End time
- `etn_event_speaker` - Assigned speakers (array)
- `etn_event_organizer` - Assigned organizers (array)
- `etn_event_location` - Venue name
- `etn_event_location_type` - physical/online
- `etn_event_address` - Address
- `etn_event_city` - City
- `etn_event_country` - Country
- `etn_zoom_meeting_link` - Virtual meeting URL
- `etn_registration_deadline` - Registration deadline
- `etn_total_attendee` - Capacity
- `etn_avaiable_tickets` - Available tickets
- `etn_sold_tickets` - Sold tickets

### Meta Keys (Attendees)
- `etn_event_id` - Associated event
- `etn_name` - Attendee name
- `etn_email` - Attendee email
- `etn_phone` - Phone number
- `etn_ticket_type` - Ticket type
- `etn_ticket_price` - Price paid
- `etn_status` - Check-in status
- `etn_payment_type` - Payment method

### Meta Keys (Speakers/Organizers)
- `etn_speaker_title` - Display name
- `etn_speaker_designation` - Job title
- `etn_speaker_summery` - Bio
- `etn_speaker_socials` - Social media array
- `image` - Profile image URL
- `etn_speaker_company_logo` - Company logo (organizers)

---

## Custom Post Type: Coupons | الكوبونات

Post Type: `sc_coupon`

### Meta Keys
- `_coupon_code` - Unique coupon code
- `_discount_type` - percentage/fixed
- `_discount_value` - Discount amount
- `_event_id` - Associated event (0 = all events)
- `_usage_limit` - Max uses (0 = unlimited)
- `_usage_count` - Current usage count
- `_expiry_date` - Expiration date
- `_created_by` - Creator user ID

---

## Changelog | سجل التغييرات

### Security & Performance Improvements (v2.1.0)
1. **SQL Injection Prevention** - All queries use prepared statements with $wpdb->prepare()
2. **Template Whitelist** - Added whitelist for allowed dashboard pages to prevent path traversal
3. **XSS Prevention** - Consistent use of esc_url(), esc_js(), esc_attr() for output escaping
4. **Modular JS Architecture** - Split inline scripts into separate files:
   - `dashboard-core.js` - Error handling, DOM fixes
   - `dashboard-alerts.js` - SweetAlert2 wrapper functions
   - `dashboard-init.js` - Page loader, tooltips, logout handler
5. **Cache Busting** - Added version strings to custom CSS/JS files
6. **File Organization** - Dashboard assets organized in `/assets/admin-dashboard/`

### Security Fixes (v1.1.0)
1. **CSRF on Logout** - Added nonce verification
2. **IDOR Prevention** - Event ownership checks on all operations
3. **CSV Validation** - File type, MIME, size validation
4. **Debug Logging** - Removed from production code
5. **Hardcoded Values** - Fixed timezone and email domain

### Performance Fixes (v1.1.0)
1. **Database Indexes** - Added index recommendations
2. **Optimized Counts** - SQL COUNT instead of fetching all posts
3. **Caching Layer** - Transient-based caching
4. **Batch Processing** - Memory-efficient large operations

---

## Requirements | المتطلبات

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+
- Eventin Plugin (Pro recommended)

---

## Configuration | الإعدادات

### Timezone
Uses WordPress timezone setting (`Settings > General > Timezone`)
Falls back to UTC if not set.

### Email Generation
For speakers/organizers without email, generates using site domain.
Example: `user123_4567@yourdomain.com`

---

## Notes | ملاحظات

1. **Frontend Only**: This dashboard is frontend-only. Event Managers are redirected away from wp-admin.

2. **Assets Location**: All dashboard assets are in `template-parts/dashboard/` and loaded via `dashboard-header.php` and `dashboard-footer.php`.

3. **Rewrite Rules**: Dashboard uses custom rewrite rules. If URLs don't work, flush permalinks in `Settings > Permalinks`.

4. **Multi-tenant**: Each Event Manager sees only their own events. Admins see all.

---

## Support | الدعم

For issues or feature requests, contact the development team.

---

*Last Updated: November 2025*
*Version: 2.1.0*
