# SC Events REST API Documentation

## Overview

SC Events REST API provides a complete interface for mobile applications to interact with the events management system.

**Base URL:** `https://your-domain.com/wp-content/themes/sc_events/api.php?route=`

**API Version:** 2.0.0

---

## Authentication

The API uses JWT (JSON Web Token) authentication for protected routes.

### Login

```http
POST /auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "your-password"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "abc123...",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "email": "user@example.com",
            "name": "John Doe"
        }
    }
}
```

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
    "error": {
        "code": "ERROR_CODE",
        "message": "Error description"
    }
}
```

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
| RATE_LIMIT_EXCEEDED | 429 | Too many requests |
| SERVER_ERROR | 500 | Internal server error |

---

## Rate Limiting

| Type | Limit | Window |
|------|-------|--------|
| Default | 100 requests | 1 minute |
| Auth | 10 requests | 1 minute |

Rate limit headers are included in responses:
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
