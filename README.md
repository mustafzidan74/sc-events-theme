# sc_events

A WordPress theme that runs a full conference and event management platform —
registration, ticketing, payments, gate scanning, sessions, certificates and a
70-screen organizer dashboard. Powers [wisdomegy.com](https://wisdomegy.com).

Arabic-first, RTL throughout, with English as a second language.

## Layout

| Path | What lives there |
|---|---|
| `template-parts/dashboard/` | The organizer dashboard — one file per screen |
| `template-parts/public/` | Public-facing event pages |
| `inc/admin-dashboard/` | AJAX handlers behind the dashboard |
| `inc/database/` | Custom table schema and data classes |
| `inc/api/` | REST API — router, auth, endpoints, middleware |
| `inc/payment-gateways/` | Paymob, Kashier, MyFatoorah, Stripe |
| `modules/` | Feature modules that can be toggled independently |
| `assets/admin-dashboard/` | Dashboard CSS, JS and images |
| `assets/frontend/` | Public site assets |

## Architecture notes

Events, tickets, speakers, schedules, sessions and halls live in **custom
tables** (`sc_*`), not in `wp_posts`. `inc/database/` holds the schema and the
data-access classes; `inc/api/` exposes them over REST with JWT auth and rate
limiting.

Features are grouped into modules under `modules/`, each one loadable on its
own so a deployment can enable only what it needs.

## Running it

Drop the folder into `wp-content/themes/` and activate. Custom tables are
created on activation; if any are missing, the dashboard shows a notice with a
button to create them.

```bash
composer install   # dependencies are not committed
```

## Contributing

`vendor/`, `node_modules/`, archives and **any `.csv`** are ignored on purpose —
the CSV rule exists because attendee exports contain personal data and must
never reach the repository.
