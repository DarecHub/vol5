# CLAUDE.md — Sailing Crew App (vol5)

## What is this project?

A self-hosted PHP web app for managing group sailing trips. Built for a crew of ~10 people across 2 boats. Handles shared expenses, shopping lists, meal planning, car logistics, sailing logbook, and trip itinerary.

**Stack:** Vanilla PHP + MySQL + Apache + Vanilla JS (no frameworks). Docker Compose for local dev.

**Live at:** `http://localhost:8080` (Docker)

---

## Architecture

```
index.php              → Login (member or admin)
pages/*.php            → Authenticated page views (dashboard, wallet, shopping, etc.)
api/*.php              → JSON API endpoints (AJAX, all return {success, data/error})
templates/header.php   → Shared header, nav, sidebar, bottom nav, mobile drawer
templates/footer.php   → Shared footer, modals (confirm, member detail), toast system
functions.php          → DB connection, auth, CSRF, exchange rate, helpers
admin/*.php            → Admin panel (settings, users, trip config)
assets/css/style.css   → Single CSS file, CSS variables design system
assets/js/app.js       → Shared JS (modals, toasts, tabs, menu, theme, countdown)
docker/seed.sql        → Full seed data for development (10 users, 15 expenses, etc.)
tests/                 → E2E test scripts
```

### Key patterns

- **No framework** — all routing via Apache rewrites + direct file access
- **Session-based auth** — `$_SESSION['user_id']`, CSRF token in meta tag + POST/header
- **All API calls** use `apiCall()` from app.js which auto-attaches CSRF
- **CSS design system** uses CSS variables (oklch colors, Apple iOS inspired palette)
- **Dark mode** via `data-theme="dark"` on `<html>`, toggle in sidebar/drawer
- **Mobile-first** — bottom nav (5 items), hamburger drawer, FAB buttons, bottom-sheet modals

### Database

MySQL with tables: `boats`, `users`, `settings`, `itinerary`, `checklist`, `wallet_expenses`, `wallet_expense_splits`, `wallet_audit_log`, `wallet_settled`, `shopping_items`, `logbook`, `menu_plan`, `cars`, `car_passengers`

Schema lives in `docker/schema.sql`, seed data in `docker/seed.sql`.

---

## Wallet / Financial Logic (most complex part)

### How expense splitting works

1. User creates expense with amount, currency (EUR/CZK), and selects who participates
2. If CZK: converted to EUR via CNB exchange rate (`functions.php:getExchangeRate`)
3. Equal split: `floor(amount / count * 100) / 100` per person, remainder goes to first person
4. Splits stored in `wallet_expense_splits` table (one row per participant)
5. Balance = SUM(what user paid) - SUM(what user owes)
6. Settlement uses greedy algorithm: match largest debtor to largest creditor

### Important: Date parsing

`api/wallet.php` accepts dates in formats: `Y-m-d H:i:s`, `Y-m-d H:i`, `Y-m-d\TH:i:s`, `Y-m-d\TH:i`. Uses `?:` (not `??`) because `DateTime::createFromFormat` returns `false`, not `null`.

---

## Running locally

```bash
docker compose up -d
# App: http://localhost:8080
# Member password: crew123
# Admin password: admin123
```

## Running tests

```bash
docker exec vol5_web php /var/www/html/tests/e2e_wallet_test.php
```

The E2E test suite (47 tests) validates:
- All CZK/EUR conversions from seed data
- Balance calculations for all 10 users
- Settlement algorithm correctness (debtors/creditors match)
- CRUD operations (add/edit/delete expense)
- Edge cases (1 cent split, 100k EUR, odd divisions)
- Filter logic (mine, boat1, boat2)
- Audit log integrity

---

## Conventions

- **Commit style:** `feat:`, `fix:`, `refactor:`, `test:` prefixes
- **CSS:** All colors via CSS variables in `:root` / `[data-theme="dark"]`, oklch color space
- **JS:** No build step, vanilla ES6+, `apiCall()` for all API requests
- **PHP:** PDO prepared statements everywhere, `e()` for HTML escaping
- **No external CDN** — Lucide icons loaded locally
- **Mobile touch targets:** min 44px hit area (Apple HIG)
