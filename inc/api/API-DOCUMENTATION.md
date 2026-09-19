# SC Events REST API Documentation

## Overview

SC Events REST API provides a complete interface for mobile applications to interact with the events management system.

**Base URL:** `https://your-domain.com/wp-content/themes/sc_events/api.php?route=`

**API Version:** 2.0.0

---

## Authentication

The API uses JWT (JSON Web Token) authentication for protected routes.

### Login

Members (attendees) and staff both sign in here with a password. Members can also sign in with a
WhatsApp code instead (see "WhatsApp codes" below).

```http
POST /auth/login
Content-Type: application/json

{
    "username": "user@example.com",
    "password": "your-password"
}
```
`username` takes the email or the username.

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "username": "user@example.com",
            "email": "user@example.com",
            "display_name": "John Doe",
            "role": "user"
        },
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "abc123...",
        "expires_in": 86400,
        "token_type": "Bearer"
    }
}
```

`role` is `user` (member), `scanner`, `manager` or `admin`. A `user` token reaches the member's own
routes (`/auth/*`, `/attendees/my-tickets`, `/certificates/my`, `/coupons/apply`); routes for staff
answer `403`.

Five wrong passwords in 5 minutes lock **that account** for 15 minutes (`429 TOO_MANY_ATTEMPTS`),
on the app and the website alike. Other people are not affected, even on the same Wi-Fi.

### Using the Token

Include the token in the Authorization header:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

---

## Response Format

### Success Response

```json
{
    "success": true,
    "data": { ... },
    "message": "Success message"
}
```

### Paginated Response

```json
{
    "success": true,
    "data": [ ... ],
    "meta": {
        "total": 100,
        "page": 1,
        "per_page": 10,
        "total_pages": 10
    }
}
```

### Error Response

```json
{
    "success": false,
    "message": "Error description",
    "error_code": "ERROR_CODE",
    "errors": { "field": ["What is wrong with it"] },
    "timestamp": "2026-09-19T07:22:53+00:00"
}
```
`errors` is `null` unless there is more to say (validation messages, `resend_in`).

---

## Endpoints

### Auth Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /auth/login | No | Login and get token |
| POST | /auth/register | No | Register new user |
| POST | /auth/forgot-password | No | Request password reset |
| POST | /auth/reset-password | No | Reset password |
| POST | /auth/logout | Yes | Logout |
| POST | /auth/refresh | Yes | Refresh token |
| GET | /auth/me | Yes | Get current user |
| PUT | /auth/profile | Yes | Update profile |
| PUT | /auth/password | Yes | Change password |
| GET | /auth/otp/status | No | Can WhatsApp codes be sent right now? |
| POST | /auth/otp/send | No | Send a sign-in code on WhatsApp |
| POST | /auth/otp/verify | No | Check the code → tokens |
| POST | /auth/otp/choose | No | Pick the account when a number has several |
| POST | /auth/password/otp/send | No | Forgot password: send a code on WhatsApp |
| POST | /auth/password/otp/verify | No | Forgot password: check the code → proof |
| POST | /auth/password/otp/reset | No | Forgot password: set the new password → tokens |

Changing or resetting a password signs the account out on every device: tokens issued before it
are refused (401), and the refresh token stops working.

---

### WhatsApp codes (sign in, forgot password)

The same codes as the website. They are for member accounts; staff (managers, scanners, admins)
keep signing in with `/auth/login`. A code is 6 digits, valid 5 minutes, allows 5 wrong tries, and
a new one can be asked for after 60 seconds (at most 3 per 15 minutes per number). There is no
limit per IP. "Send" answers look the same whether or not an account uses the number, and a code
is only sent to a number that has an account. When several WhatsApp lines are connected, codes go
out from them in turn.

Phone numbers are sent as typed: `phone` (`"01012345678"`, `"1012345678"` or `"+20 10 1234 5678"`)
and optional `country_code` (default `"+20"`).

**0. Show the option only when it works**

```http
GET /auth/otp/status
```
```json
{ "success": true, "data": { "available": true, "resend_after": 60, "expires_in": 300, "code_length": 6 } }
```
When `available` is false (no WhatsApp line connected), hide "Sign in with WhatsApp" and
"Forgot password"; the send routes answer `503 OTP_UNAVAILABLE`.

**Sign in**

```http
POST /auth/otp/send
{ "phone": "01012345678", "country_code": "+20" }
```
```json
{ "success": true, "message": "If an account uses this number, a 6-digit code is on its way on WhatsApp.",
  "data": { "resend_in": 60, "expires_in": 300, "phone": "+20 10••••••78" } }
```

```http
POST /auth/otp/verify
{ "phone": "01012345678", "country_code": "+20", "code": "123456" }
```
One account on the number → the same body as `/auth/login`:
```json
{ "success": true, "data": { "user": { "id": 42, "username": "...", "email": "...", "display_name": "...", "role": "user" },
  "access_token": "...", "refresh_token": "...", "expires_in": 86400, "token_type": "Bearer" } }
```
Several accounts on the number → ask which one, then call `/auth/otp/choose` within 15 minutes:
```json
{ "success": true, "data": { "choose": [ { "id": 42, "name": "Sara", "email": "s•••@gmail.com" }, ... ], "proof": "Xy3..." } }
```
```http
POST /auth/otp/choose
{ "proof": "Xy3...", "user_id": 42 }
```
The proof works once; a wrong `user_id` uses it up (start again from send).

**Forgot password**

```http
POST /auth/password/otp/send
{ "email": "sara@gmail.com" }            // or { "phone": "01012345678", "country_code": "+20" }
```
The code goes to the WhatsApp number on the account.

```http
POST /auth/password/otp/verify
{ "email": "sara@gmail.com", "code": "123456" }   // same email or phone as the send step
```
```json
{ "success": true, "data": { "proof": "Ab9...", "choose": [] } }
```
`choose` lists the accounts when a phone number has several; send the chosen `user_id` in the next step.

```http
POST /auth/password/otp/reset
{ "proof": "Ab9...", "user_id": 42, "password": "NewPass#2026", "password_confirmation": "NewPass#2026" }
```
The password needs at least 8 characters and can't be the phone number (`422` with
`errors.password`; the proof stays valid so the user can try again). Success returns fresh tokens
like `/auth/login`, and every other device is signed out.

**Errors**

| HTTP | error_code | Meaning |
|------|------------|---------|
| 422 | VALIDATION_ERROR | Missing phone, email, code or password (see `errors`) |
| 400 | OTP_WRONG | Wrong code; the message says how many tries are left |
| 400 | OTP_EXPIRED | Code or proof expired or used up; ask for a new code |
| 404 | NO_ACCOUNT | The code was right but no member account uses the number |
| 429 | OTP_WAIT | Too soon for a new code; `errors.resend_in` = seconds to wait |
| 429 | OTP_LIMIT | 3 codes already sent to this number in the last 15 minutes |
| 503 | OTP_UNAVAILABLE | No WhatsApp line connected |

---

### Events Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /events | No | List published events |
| GET | /events/{id} | No | Get event details |

**List Events Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 10, max: 50)
- `category_id` - Filter by category
- `search` - Search by title/description
- `sort` - Sort by: upcoming (default), past, newest

**Event Response includes:**
- Event details (title, description, dates, location)
- Categories
- Tickets (with availability)
- Speakers
- Organizers
- Extra fields (custom form fields)
- FAQ and Schedule

---

### Categories Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /categories | No | List all categories |
| GET | /categories/{id} | No | Get category details |

---

### Speakers Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /speakers | No | List speakers |
| GET | /speakers/{id} | No | Get speaker details |

**Query Parameters:**
- `event_id` - Filter by event

---

### Organizers Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /organizers | No | List organizers |
| GET | /organizers/{id} | No | Get organizer details |

**Query Parameters:**
- `event_id` - Filter by event

---

### Attendees Endpoints (Registration)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /attendees/register | No | Register for an event |
| GET | /attendees/{ticket_code} | No | Get attendee by ticket code |
| GET | /attendees/my-tickets | No* | Get user's tickets |

*Requires either login or email query parameter

**Register Request:**
```json
{
    "event_id": 1,
    "ticket_id": 2,
    "name": "Ahmed Mohamed",
    "email": "ahmed@example.com",
    "phone": "+966501234567",
    "extra_fields": {
        "company": "Tech Corp",
        "job_title": "Developer"
    },
    "coupon_code": "DISCOUNT20"
}
```

**Register Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "ticket_code": "TKT-A1B2C3D4",
        "name": "Ahmed Mohamed",
        "email": "ahmed@example.com",
        "event_id": 1,
        "ticket_name": "VIP Ticket",
        "ticket_price": 100.00,
        "payment_status": "pending",
        "requires_payment": true
    },
    "message": "Registration successful"
}
```

---

### Companies Endpoints (Company Registration)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /companies/register | No | Register as company |
| GET | /companies/{company_code} | No | Get company by code |
| GET | /companies | No | List companies for event |
| PUT | /companies/{company_code} | No | Update company |

**Register Company Request:**
```json
{
    "event_id": 1,
    "company_name": "Tech Solutions",
    "company_name_ar": "حلول تقنية",
    "industry": "Technology",
    "company_size": "51-200",
    "website": "https://techsolutions.com",
    "contact_name": "Ahmed Mohamed",
    "contact_title": "CEO",
    "contact_email": "ahmed@techsolutions.com",
    "contact_phone": "+966501234567",
    "country": "Saudi Arabia",
    "city": "Riyadh"
}
```

---

### Booths Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /booths | No | List booths for event |
| GET | /booths/{id} | No | Get booth details |
| GET | /booth-types | No | List booth types |
| GET | /booth-types/{id} | No | Get booth type |
| GET | /booth-bookings | No | List bookings |
| GET | /booth-bookings/{id} | No | Get booking details |
| POST | /booth-bookings | No | Create booth booking |

**Query Parameters (for /booths):**
- `event_id` (required) - Event ID
- `status` - Filter by status (available, reserved, booked)

**Create Booking Request:**
```json
{
    "event_id": 1,
    "booth_id": 5,
    "company_code": "COMP-A1B2C3D4-E5F6G7H8",
    "special_requests": "Need extra power outlets"
}
```

---

### Certificates Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /certificates/verify/{code} | No | Verify certificate (public) |
| GET | /certificates/check | No | Check eligibility |
| POST | /certificates/request | No | Request certificate issuance |
| GET | /certificates/my | No* | Get user's certificates |

*Requires either login or email query parameter

**Check Eligibility:**
```
GET /certificates/check?ticket_code=TKT-A1B2C3D4
```
or
```
GET /certificates/check?email=user@example.com&event_id=1
```

**Request Certificate:**
```json
{
    "ticket_code": "TKT-A1B2C3D4"
}
```

**Certificate Response:**
```json
{
    "success": true,
    "data": {
        "certificate_number": "CERT-2026-A1B2C3D4",
        "verification_code": "A1B2C3D4E5F6G7H8",
        "attendee_name": "Ahmed Mohamed",
        "event_title": "Tech Conference 2026",
        "issued_at": "2026-02-06 10:30:00",
        "download_url": "https://example.com/certificate/A1B2C3D4E5F6G7H8/download",
        "view_url": "https://example.com/certificate/A1B2C3D4E5F6G7H8"
    }
}
```

---

### Coupons Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /coupons | No | List coupons for event |
| POST | /coupons/validate | No | Validate coupon code |
| POST | /coupons/apply | No | Apply coupon |
| GET | /coupons/check/{code} | No | Quick check coupon |

**Validate Coupon Request:**
```json
{
    "code": "DISCOUNT20",
    "event_id": 1,
    "ticket_id": 2,
    "amount": 100.00
}
```

**Validate Coupon Response:**
```json
{
    "success": true,
    "data": {
        "valid": true,
        "code": "DISCOUNT20",
        "discount_type": "percentage",
        "discount_value": 20,
        "discount_amount": 20.00,
        "original_amount": 100.00,
        "final_amount": 80.00,
        "message": "Discount of 20.00 applied"
    }
}
```

---

### Utility Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /health | No | API health check |
| GET | /version | No | API version info |

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| VALIDATION_ERROR | 400 | Invalid input data |
| UNAUTHORIZED | 401 | Authentication required |
| FORBIDDEN | 403 | Insufficient permissions |
| NOT_FOUND | 404 | Resource not found |
| RATE_LIMIT_EXCEEDED | 429 | Too many requests from one signed-in account |
| TOO_MANY_ATTEMPTS | 429 | Too many wrong passwords for this account; try in 15 minutes |
| SERVER_ERROR | 500 | Internal server error |

---

## Rate Limiting

There are **no limits per IP address**: at the venue the whole audience shares one Wi-Fi address.
Limits are per account and per phone number instead:

| What | Limit |
|------|-------|
| Signed-in requests | 100 a minute per account (auth routes 10 a minute per account) |
| Wrong passwords | 5 in 5 minutes lock that account for 15 minutes |
| WhatsApp codes | 1 a minute and 3 per 15 minutes per phone number |
| Wrong codes | 5 per code, then ask for a new one |

Requests without a token (browsing events, speakers, sending a code) are not counted.

Rate limit headers are included in signed-in responses:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

---

## Mobile App Integration Example

### 1. Login

```javascript
const response = await fetch('https://your-domain.com/wp-content/themes/sc_events/api.php?route=/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        email: 'user@example.com',
        password: 'password123'
    })
});

const data = await response.json();
const token = data.data.token;
```

### 2. Get Events

```javascript
const events = await fetch('https://your-domain.com/wp-content/themes/sc_events/api.php?route=/events&sort=upcoming');
```

### 3. Register for Event

```javascript
const result = await fetch('https://your-domain.com/wp-content/themes/sc_events/api.php?route=/attendees/register', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        event_id: 1,
        ticket_id: 2,
        name: 'Ahmed Mohamed',
        email: 'ahmed@example.com',
        phone: '+966501234567'
    })
});
```

### 4. Validate Coupon

```javascript
const coupon = await fetch('https://your-domain.com/wp-content/themes/sc_events/api.php?route=/coupons/validate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        code: 'DISCOUNT20',
        event_id: 1,
        amount: 100.00
    })
});
```

### 5. Request Certificate

```javascript
const cert = await fetch('https://your-domain.com/wp-content/themes/sc_events/api.php?route=/certificates/request', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        ticket_code: 'TKT-A1B2C3D4'
    })
});
```

---

## Support

For API support and questions, contact the development team.
