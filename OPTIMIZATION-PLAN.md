# SC Events - Code Organization, Security & Performance Plan

## Phase 1: Light Theme Enhancement ✅ COMPLETED
- [x] Rewrite light-theme.css with premium visual depth
- [x] Add colored shadows, gradient backgrounds, visible orbs
- [x] Add alternating section backgrounds, premium footer
- [x] Enhance cards, glass effects, hover states

## Phase 2: Security Hardening ✅ COMPLETED
- [x] Fixed Paymob HMAC validation (reject when secret not configured)
- [x] Applied security headers globally (not just dashboard)
- [x] Fixed ALL 10 SHOW TABLES queries in schema.php with $wpdb->prepare()
- [x] Created sc_table_exists() helper function
- [x] Disabled debug-users.php (renamed to .disabled)
- [ ] Add nonce fields to public frontend forms (future improvement)
- [ ] Add stricter file upload MIME validation (future improvement)

## Phase 3: Performance Optimization ✅ COMPLETED
- [x] Cache categories query with sc_get_cached_event_categories() transient
- [x] Fix N+1 ticket queries in archive-sc_event.php (batch load)
- [x] Add loading="lazy" to all images globally via filter
- [x] Cache repeated get_option() calls with sc_get_platform_settings()
- [x] Add preconnect hints for CDN domains (header + wp_resource_hints filter)
- [x] Added cache invalidation hooks for category changes

## Phase 4: Code Organization ✅ COMPLETED

### CSS Extraction - Public Pages (7 files cleaned)
- [x] page-checkout.php → assets/frontend/css/checkout-page.css (~324 lines)
- [x] page-ticket-view.php → assets/frontend/css/ticket-view-page.css (~323 lines)
- [x] page-payment-success.php → assets/frontend/css/payment-pages.css (shared)
- [x] page-payment-failed.php → assets/frontend/css/payment-pages.css (shared)
- [x] page-contact.php → assets/frontend/css/contact-page.css (~178 lines)
- [x] page-forgot-password.php → assets/frontend/css/forgot-password-page.css (~186 lines)
- [x] 404.php → assets/frontend/css/404-page.css (~126 lines)

### CSS Extraction - Dashboard Templates (5 files cleaned)
- [x] event-create.php → assets/dashboard/css/event-form.css (~408 lines, shared)
- [x] event-edit.php → assets/dashboard/css/event-form.css (~408 lines, DEDUPLICATED)
- [x] chat.php → assets/dashboard/css/chat.css (~332 lines)
- [x] login.php → assets/dashboard/css/login.css (~285 lines)
- [x] scanner.php → assets/dashboard/css/scanner.css (~167 lines)

### JS Extraction - Status
Dashboard JS blocks (event-create: 1,386 lines, event-edit: 1,839 lines, scanner: 690 lines, analytics: 475 lines) are deeply coupled with PHP-generated translations, nonces, and dynamic data. Extracting them requires wp_localize_script() refactoring - marked as future improvement to avoid breaking changes.

## Summary of Changes

### Files Created (11 new CSS files)
- assets/frontend/css/checkout-page.css
- assets/frontend/css/ticket-view-page.css
- assets/frontend/css/payment-pages.css
- assets/frontend/css/contact-page.css
- assets/frontend/css/forgot-password-page.css
- assets/frontend/css/404-page.css
- assets/dashboard/css/event-form.css
- assets/dashboard/css/chat.css
- assets/dashboard/css/login.css
- assets/dashboard/css/scanner.css
- assets/frontend/css/light-theme.css (rewritten)

### Files Modified (Security)
- inc/admin-dashboard/security-utilities.php (global headers)
- inc/payment-gateways/class-sc-gateway-paymob.php (HMAC fix)
- inc/database/schema.php (prepare() + sc_table_exists())
- debug-users.php → debug-users.php.disabled

### Files Modified (Performance)
- functions.php (preconnect, lazy loading, caching functions)
- template-parts/public/header-public.php (preconnect, cached categories)
- archive-sc_event.php (batch ticket loading, cached categories)

### Files Modified (Code Organization)
- page-checkout.php (inline CSS → external)
- page-ticket-view.php (inline CSS → external)
- page-payment-success.php (inline CSS → external)
- page-payment-failed.php (inline CSS → external)
- page-contact.php (inline CSS → external)
- page-forgot-password.php (inline CSS → external)
- 404.php (inline CSS → external)
- template-parts/dashboard/event-create.php (inline CSS → external)
- template-parts/dashboard/event-edit.php (inline CSS → external)
- template-parts/dashboard/chat.php (inline CSS → external)
- template-parts/dashboard/login.php (inline CSS → external)
- template-parts/dashboard/scanner.php (inline CSS → external)

### Approximate CSS Lines Removed from Inline
- Public pages: ~1,311 lines
- Dashboard templates: ~1,600 lines (including 408 lines of deduplication)
- **Total: ~2,911 lines of CSS moved from inline to proper files**

## Future Improvements (Not Yet Done)
- [ ] Extract dashboard JS to separate files with wp_localize_script()
- [ ] Add nonce verification to all public forms
- [ ] Add stricter file upload MIME validation
- [ ] Conditionally load AJAX handlers (only dashboard pages)
- [ ] Define proper image sizes with add_image_size()
- [ ] Migrate remaining ~40 SHOW TABLES queries to use sc_table_exists()
