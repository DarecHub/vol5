# SailCrew — Sailing Trip Management App

Self-hosted web app for managing group sailing trips. Designed for crews of 5–15 people across multiple boats.

Handles shared finances, shopping coordination, meal planning, car logistics, sailing logbook, and trip itinerary — all from a mobile-first responsive interface.

## Features

| Module | Description |
|--------|-------------|
| **Pokladna (Wallet)** | Shared expense tracking with automatic equal splits, CZK/EUR conversion via Czech National Bank rates, per-person balance calculation, and greedy settlement algorithm |
| **Nákupy (Shopping)** | Per-boat shopping lists with categories, assignment, price tracking, and bought/pending status |
| **Jídelníček (Menu)** | Daily meal planning per boat — assign cooks, plan breakfast/lunch/dinner |
| **Logbook** | Sailing log with nautical miles, routes, departure/arrival times, skipper tracking, and per-boat statistics |
| **Itinerář (Itinerary)** | Day-by-day trip timeline with location routing (car/sailing/port segments) |
| **Posádky (Crews)** | Crew roster with contact info, boat assignment, and member profiles |
| **Auta (Cars)** | Vehicle management for land transport — driver assignment, passenger capacity, seat tracking |
| **Co s sebou (Checklist)** | Packing checklist with categories (required, clothing, gear, recommended) |
| **Admin panel** | Trip settings, user management, password management |

## Tech Stack

- **Backend:** PHP 8.1+ (vanilla, no framework)
- **Database:** MySQL 8.0 / MariaDB
- **Frontend:** Vanilla JavaScript (ES6+), CSS custom properties design system
- **Server:** Apache with `.htaccess` rewrites
- **Icons:** Lucide (loaded locally, no CDN)
- **Containerization:** Docker Compose

## Quick Start

```bash
# Clone and start
git clone git@github.com:Srbino/vol5.git
cd vol5
docker compose up -d

# App runs at http://localhost:8080
```

### Login credentials (dev seed data)

| Role | Login |
|------|-------|
| **Crew member** | Select name from dropdown + password: `crew123` |
| **Admin** | Password: `admin123` |

The seed data creates 10 users across 2 boats (Stella, Modrá laguna) with 15 expenses, shopping lists, logbook entries, menu plans, and a full 12-day itinerary.

## Project Structure

```
vol5/
├── index.php                 # Login page (member + admin auth)
├── functions.php             # Core: DB, auth, CSRF, exchange rate, helpers
├── config.php                # DB credentials (not in git, see .env)
├── logout.php
│
├── pages/                    # Authenticated views
│   ├── dashboard.php         # Home — hero card, nav grid, activity feed
│   ├── wallet.php            # Expense management + settlements
│   ├── shopping.php          # Shopping lists (per boat, categorized)
│   ├── logbook.php           # Sailing log (per boat, with stats)
│   ├── menu.php              # Meal planner (per boat, per day)
│   ├── itinerary.php         # Trip timeline
│   ├── crews.php             # Crew directory
│   ├── checklist.php         # Packing list
│   └── cars.php              # Vehicle/passenger management
│
├── api/                      # JSON API (all return {success, data/error})
│   ├── wallet.php            # CRUD expenses, balances, settlements, audit
│   ├── shopping.php          # CRUD shopping items, toggle bought
│   ├── logbook.php           # CRUD log entries + stats
│   ├── menu.php              # CRUD meal plans
│   ├── cars.php              # CRUD cars + passengers
│   ├── checklist.php         # CRUD checklist items
│   ├── exchange.php          # Current EUR/CZK rate
│   ├── export.php            # Wallet export to printable HTML
│   ├── avatar.php            # User avatar upload/delete
│   ├── expense_photo.php     # Expense receipt photo upload
│   └── user_detail.php       # User profile data
│
├── admin/                    # Admin panel
│   ├── index.php             # Dashboard
│   ├── settings.php          # Trip settings (name, dates, passwords)
│   └── users.php             # User CRUD
│
├── templates/
│   ├── header.php            # Top bar, sidebar, bottom nav, mobile drawer
│   └── footer.php            # Confirm modal, member modal, toast system
│
├── assets/
│   ├── css/style.css         # Design system (CSS variables, Apple iOS palette)
│   ├── js/app.js             # Shared JS (modals, toasts, tabs, theme, AJAX)
│   ├── avatars/              # User avatar uploads
│   └── expense_photos/       # Expense receipt photos
│
├── docker/
│   ├── schema.sql            # Database schema
│   └── seed.sql              # Development seed data
│
├── tests/
│   └── e2e_wallet_test.php   # E2E financial logic tests (47 tests)
│
├── docker-compose.yml
├── Dockerfile
├── .htaccess                 # Apache rewrites + security headers
├── CLAUDE.md                 # AI assistant context file
└── README.md
```

## Wallet — Financial Logic

The wallet is the most complex module. Here's how it works:

### Expense Flow

1. **Create expense** — user selects payer, amount, currency (EUR or CZK), and participants
2. **CZK conversion** — if CZK, converted to EUR using live Czech National Bank rate (cached daily, fallback 25.0)
3. **Equal split** — `floor(amount / participants * 100) / 100` per person, remainder added to first participant (guarantees exact total)
4. **Storage** — expense in `wallet_expenses`, individual shares in `wallet_expense_splits`

### Balance Calculation

```
User balance = SUM(what they paid) − SUM(their shares)
Positive → others owe them
Negative → they owe others
Zero → square
```

All balances always sum to exactly 0.

### Settlement Algorithm

Greedy matching: sort debtors and creditors by amount descending, then pair largest debtor with largest creditor, transfer `min(debt, credit)`, repeat until all matched. Minimizes number of transfers.

### Exchange Rate

- Fetched daily from `cnb.cz` (Czech National Bank official rates)
- Cached in `settings` table with timestamp
- When editing a CZK expense, the **original rate is preserved** (prevents balance drift)
- Fallback: last cached rate, then hardcoded 25.0

## Design System

Apple iOS-inspired design with CSS custom properties in oklch color space:

- **Colors:** iOS System Blue as primary, clean neutral grays, system semantic colors
- **Dark mode:** Pure black background (OLED-friendly), slightly vibrant colors
- **Typography:** -apple-system / SF Pro font stack, 15px base size
- **Radius:** 8–12px (iOS-style soft rounding)
- **Shadows:** Soft, diffused (not sharp)
- **Mobile:** Bottom nav (5 items), hamburger drawer, FAB buttons, bottom-sheet modals, 44px minimum touch targets

## API Reference

All API endpoints accept GET or POST, return JSON `{success: bool, data: ..., error: string}`. POST requests require CSRF token via `csrf_token` field or `X-CSRF-TOKEN` header.

| Endpoint | Actions |
|----------|---------|
| `/api/wallet.php` | `list`, `add`, `edit`, `delete`, `balances`, `settlements`, `settle`, `audit`, `rate` |
| `/api/shopping.php` | `list`, `add`, `edit`, `delete`, `toggle_bought` |
| `/api/logbook.php` | `list`, `add`, `edit`, `delete` |
| `/api/menu.php` | `list`, `add`, `edit`, `delete` |
| `/api/cars.php` | `list`, `add_car`, `delete_car`, `add_passenger`, `remove_passenger` |
| `/api/checklist.php` | `list`, `add`, `edit`, `delete` |
| `/api/exchange.php` | (GET) current EUR/CZK rate |
| `/api/export.php` | (GET) printable HTML wallet export |

## Tests

### E2E Wallet Tests

```bash
# Run inside Docker container
docker exec vol5_web php /var/www/html/tests/e2e_wallet_test.php
```

**47 tests** covering:

- Seed data integrity (expenses, splits, CZK→EUR conversions)
- Balance correctness for all 10 users (hand-calculated expected values)
- Balance invariant: sum of all balances = 0
- Settlement algorithm: debtors/creditors matched correctly, totals match
- CRUD lifecycle: add → verify → edit → verify → delete → verify
- CZK expense flow: conversion, split, cleanup
- Edge cases: 1 EUR/1 person, 99999.99 EUR/2, 0.01 EUR/3, 7 EUR/3 (remainder)
- Expense filtering: mine, boat1, boat2
- Exchange rate API validity
- Audit log: created + edited entries
- Consistency: balances return to original after add+delete cycle

## Security

- **CSRF protection** on all mutations (token in meta tag, validated server-side)
- **Brute-force protection** — max 10 login attempts per 15 minutes
- **HTTP security headers** — X-Frame-Options, CSP, X-Content-Type-Options, Referrer-Policy
- **Prepared statements** — all SQL uses PDO prepared statements
- **HTML escaping** — `e()` helper for all user-generated output
- **File upload validation** — MIME type check via `finfo`, size limits (3MB avatar, 8MB receipts)
- **Session management** — 24h timeout, optional 7-day remember-me, `session_regenerate_id` on login
- **.htaccess** — blocks direct access to config, functions, templates

## Configuration

Create `config.php` or use environment variables:

```php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sailcrew');
define('DB_USER', 'root');
define('DB_PASS', 'password');
```

Or via `.env` / Docker environment:

```env
DB_HOST=db
DB_NAME=sailing_app
DB_USER=sail
DB_PASS=sail_password
```

## License

Private project. Not licensed for redistribution.
