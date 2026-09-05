# Security Changelog - SC Events Theme

This document tracks all security improvements, fixes, and hardening measures implemented in the SC Events theme.

## Version 2.1.0 - Security Audit (December 2025)

### Input Sanitization Improvements

#### events-ajax-handlers.php
- **Fixed**: Changed `stripslashes()` to `wp_unslash()` for all JSON data inputs
- **Added**: Proper sanitization for `faq_data` using `sanitize_text_field()` and `wp_kses_post()`
- **Added**: Proper sanitization for `extra_fields_data` using `sanitize_text_field()` and `sanitize_key()`
- **Added**: Proper sanitization for `additional_sections_data` with nested array handling
- **Added**: Proper sanitization for `schedules_data` using `sanitize_text_field()` and `wp_kses_post()`
- **Added**: Proper sanitization for `social_links_data` and `tickets_data`

### File Upload Security

#### class-sc-chat.php
- **Existing**: Comprehensive file upload validation already in place:
  - Magic bytes verification
  - MIME type validation against whitelist
  - Double extension detection
  - File size limits (5MB)
  - Extension whitelist
  - Image validation with `getimagesize()`

#### events-ajax-handlers.php
- **Existing**: Uses WordPress `media_handle_upload()` which includes built-in security
- **Existing**: PDF validation for schedules file uploads

### Security Infrastructure

#### security-utilities.php (Existing Comprehensive Security Layer)
- **CSRF Protection**: Enhanced tokens with extra entropy
- **Brute Force Protection**:
  - Failed login tracking
  - Progressive lockouts (15 min initial, doubles each time)
  - Admin notifications after 3+ lockouts
  - IP whitelisting support
- **Input Sanitization Helpers**:
  - `sc_sanitize_int()` - Integer with range validation
  - `sc_sanitize_float()` - Float with range validation
  - `sc_sanitize_email()` - Email with validation
  - `sc_sanitize_phone()` - Phone number normalization
  - `sc_sanitize_date()` - Date format validation
  - `sc_sanitize_id_array()` - Array of IDs
  - `sc_sanitize_whitelist()` - Value against allowed list
  - `sc_sanitize_html()` - HTML with context-aware filtering
- **XSS Protection**:
  - `sc_esc_html()`, `sc_esc_attr()`, `sc_esc_url()`, `sc_esc_js()`
  - `sc_json_encode_safe()` - Safe JSON for HTML embedding
- **SQL Injection Prevention**:
  - `sc_prepare_like()` - Safe LIKE clause preparation
  - `sc_prepare_in_clause()` - Safe IN clause preparation
  - `sc_sanitize_orderby()` - ORDER BY column whitelist
  - `sc_sanitize_order()` - ORDER direction validation
- **Security Headers** (Dashboard pages):
  - X-Frame-Options: SAMEORIGIN
  - X-Content-Type-Options: nosniff
  - X-XSS-Protection: 1; mode=block
  - Referrer-Policy: strict-origin-when-cross-origin
  - Permissions-Policy: geolocation=(), microphone=(), camera=()
  - Content-Security-Policy (optional, filter-enabled)
- **File Upload Validation**:
  - `sc_validate_file_upload()` - Comprehensive validation
  - `sc_sanitize_filename()` - Dangerous extension blocking
  - PHP content detection in uploads
- **Security Logging**:
  - `sc_log_security_event()` - Error log + optional DB storage
  - Database audit trail option

### Performance Optimizations (Security-Related)

#### performance-optimizations.php
- **Rate Limiting System**:
  - Configurable limits per action type (default, write, sensitive, read)
  - Per-user and per-IP tracking
  - Proper HTTP headers (X-RateLimit-*, Retry-After)
  - Automatic application to all `sc_*` AJAX actions
- **Batch Processing**: Memory-efficient processing to prevent DoS through resource exhaustion
- **Caching**: Transient-based caching to reduce database load

### SQL Query Security Review

All SQL queries were reviewed and found to use appropriate security measures:

1. **Table Names**: Use `$wpdb->prefix` + hardcoded strings (not user input)
2. **User Input**: All user input uses `$wpdb->prepare()` with placeholders
3. **DDL Statements**: ALTER TABLE and CREATE TABLE use internal values only
4. **LIKE Queries**: Use `$wpdb->esc_like()` for search patterns

### Pagination Review

`posts_per_page: -1` usage reviewed - all instances are appropriate:
- **Export functions**: Require all records for CSV generation
- **Delete all operations**: Use `'fields' => 'ids'` for memory efficiency
- **Migration scripts**: One-time operations requiring all records
- **User-facing pages**: Already use server-side pagination

---

## Security Best Practices Implemented

### Authentication & Authorization
- WordPress nonce verification on all AJAX handlers
- Capability checks (`current_user_can()`, `is_event_manager()`)
- Event ownership verification for edit/delete operations

### Data Handling
- All user input sanitized before use
- All output escaped before display
- JSON data properly decoded and sanitized

### File Handling
- Strict MIME type validation
- Magic bytes verification
- Dangerous extension blocking
- PHP content detection

### Network Security
- HTTPS enforcement recommended
- CORS validation
- Origin verification

---

## Recommendations for Production

1. **Enable CSP Header**: Add to wp-config.php:
   ```php
   add_filter('sc_enable_csp_header', '__return_true');
   ```

2. **Enable Security Logging**: Add to wp-config.php:
   ```php
   add_filter('sc_store_security_logs', '__return_true');
   ```

3. **Configure Rate Limits**: Adjust via filter:
   ```php
   add_filter('sc_rate_limit_config', function($config) {
       $config['sensitive']['requests'] = 5; // Stricter for delete operations
       return $config;
   });
   ```

4. **Whitelist Admin IPs**: For brute force protection:
   ```php
   add_filter('sc_brute_force_config', function($config) {
       $config['whitelist_ips'] = array('your.ip.here');
       return $config;
   });
   ```

5. **Regular Security Audits**: Schedule periodic code reviews

---

## Performance Improvements

### CSS Extraction
- **archive-sc_event.php**: Extracted ~230 lines of inline CSS to external file
- **New file**: `assets/frontend/css/archive-events.css`
- **Benefits**:
  - Browser caching enabled
  - Reduced HTML size
  - Better separation of concerns
  - Version-based cache busting

---

## Files Modified in This Audit

| File | Changes |
|------|---------|
| `inc/admin-dashboard/events-ajax-handlers.php` | Input sanitization improvements |
| `archive-sc_event.php` | Inline CSS extracted to external file |
| `assets/frontend/css/archive-events.css` | **NEW** - Archive page styles |

## Files Reviewed (No Changes Needed)

| File | Status |
|------|--------|
| `inc/admin-dashboard/security-utilities.php` | Comprehensive security already implemented |
| `inc/admin-dashboard/performance-optimizations.php` | Rate limiting and caching in place |
| `inc/database/class-sc-chat.php` | File upload security comprehensive |
| `inc/admin-dashboard/coupons-ajax-handlers.php` | Pagination implemented for display |
| `modules/venues/class-gate.php` | SQL queries use internal table names |
| `inc/admin-dashboard/reports-functions.php` | Cache clearing uses static patterns |

---

*Last Updated: December 2025*
*Audited By: Claude AI Security Review*
