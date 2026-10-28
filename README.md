# ACLC Mandaue — Queuing System

Department-based queue management with multi-counter serving, realtime updates,
phone OTP verification, and a TV display. Built for ACLC Mandaue front-desk
operations (Admission, Cashier, Registrar, etc.).

**Stack:** Laravel 9 · PHP 8.2.x (XAMPP/LAMPP) · MySQL 8 / MariaDB / PostgreSQL 14+ ·
Pusher Channels · Textbee SMS · Tailwind CSS · Blade

---

## 1. Setup

### Requirements

| Tool | Version |
|---|---|
| PHP | 8.2.x (XAMPP/LAMPP) — **not** 8.5, Laravel 9 emits deprecation warnings |
| Composer | 2.x (run it with the XAMPP PHP binary) |
| Database | MySQL 8 / MariaDB **or** PostgreSQL 14+ |
| Node + npm | 18+ (only for building frontend assets) |

### PHP binary (important)

Always use the PHP that ships with XAMPP/LAMPP so the version matches the project.

**Linux (LAMPP):**
```bash
/opt/lampp/bin/php -v        # PHP 8.2.12
/opt/lampp/bin/php artisan serve
```

**Windows (XAMPP), PowerShell:**
```powershell
& "C:\xampp\php\php.exe" -v
& "C:\xampp\php\php.exe" artisan serve
```

**Windows (XAMPP), Command Prompt:**
```bat
"C:\xampp\php\php.exe" -v
"C:\xampp\php\php.exe" artisan serve
```

> All `php ...` commands below assume this binary. If your default `php` is 8.5,
> replace it with the path for your OS. If Composer also picks the wrong PHP:
> ```bash
> # Linux
> /opt/lampp/bin/php "$(command -v composer)" install
> # Windows (adjust the .phar path if needed)
> "C:\xampp\php\php.exe" "C:\ProgramData\ComposerSetup\bin\composer.phar" install
> ```

### Step-by-step

```bash
# Linux (LAMPP). On Windows replace $PHP with & "C:\xampp\php\php.exe" (PowerShell)
PHP=/opt/lampp/bin/php

# 1. Install PHP dependencies
$PHP "$(command -v composer)" install

# 2. Configure environment
cp .env.example .env
$PHP artisan key:generate
```

Edit `.env` — the minimum you must set:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql            # or pgsql
DB_HOST=127.0.0.1
DB_PORT=3306                   # 5432 for pgsql
DB_DATABASE=qys
DB_USERNAME=root
DB_PASSWORD=

# Realtime (optional — falls back to 5s polling without it)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1         # mt1 also works

# SMS OTP via Textbee (optional — OTP is logged without it)
TEXTBEE_API_KEY=
TEXTBEE_DEVICE_ID=

# reCAPTCHA v2 on login/register (optional — skipped if blank)
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

```bash
# 3. Migrate + seed (creates default admin + purposes)
$PHP artisan migrate
$PHP artisan db:seed

# 4. (Optional) Build frontend assets — otherwise CDN versions are used
npm install && npm run build

# 5. Run the app
$PHP artisan serve              # http://localhost:8000

# 6. Run the scheduler (REQUIRED for lunch auto-pause + 3-min auto-skip)
$PHP artisan schedule:work
```

> For full speed, run with OPcache (XAMPP has no sudo-safe php.ini edit):
> ```bash
> /opt/lampp/bin/php -d zend_extension=/opt/lampp/lib/php/extensions/no-debug-non-zts-20220829/opcache.so \
>   -d opcache.enable=1 -d opcache.enable_cli=1 \
>   -d opcache.memory_consumption=128 -d opcache.max_accelerated_files=10000 \
>   artisan serve
> ```
> On Windows, enable `opcache.enable=1` and `opcache.enable_cli=1` in
> `C:\xampp\php\php.ini`, then restart the terminal/Apache.

### Getting API keys

- **Pusher** (realtime): [pusher.com](https://pusher.com) → Channels app (free:
  200k msgs/day) → App ID, Key, Secret, Cluster → `.env`.
- **Textbee** (SMS OTP): [textbee.dev](https://textbee.dev) → install Android app
  → register device → Device ID + API Key → `.env`. Free (uses your SIM plan).

---

## 2. How It Works

### Ticket lifecycle

```
waiting → serving → completed
   │          └────→ no_response (skip / 3-min auto-skip)
   └─────────────── full queue (QUEUE_MAX_CAPACITY, default 50)
```

1. **Join** — Guest/student picks a department + purpose at `/student/index`
   (`POST /queue`). One active ticket per person per day; duplicates rejected.
2. **Serve** — Staff clicks **Call Next** on a free counter. The oldest `waiting`
   ticket becomes `serving`, locked to that counter (one staff = one serving).
3. **Finish** — Staff clicks **Complete** (or **Skip**). Auto-skip runs every
   minute: `serving` tickets untouched for 3 minutes return to the pool and the
   free counter refills automatically.
4. **Pause** — Each department is `Auto` (lunch scheduler pauses/resumes) or
   `Manual` (staff toggles Pause/Resume; lunch automation disabled for it).

### Realtime flow

```
Staff action → broadcastQueueState() → QueueUpdated event
  → Pusher channel queue.{department_id} → queue.updated
  → open pages update instantly
  → 5s polling fallback (/api/queue-status, 3s server cache) if Pusher is down
```

### Phone verification flow

Register → account created with `phone_verified_at = NULL` → 6-digit OTP via
Textbee (10-min expiry, resend rate-limited 3 per 2 min) → verify at
`/verify-otp` → login allowed. Unverified logins are bounced back to OTP.
Password reset uses the same SMS OTP channel.

### Roles

| Role | Access |
|---|---|
| Guest/Student (`student` guard) | Join queue, view own ticket + position, TV |
| Staff (`web` guard, locked to 1 department) | Call Next / Skip / Complete, pause toggle, reports |
| Admin (`web` guard, all departments) | Everything + departments, staff, counters, purposes, audit log, PDF reports |

> After login, **admins land on `/admin/overview`**; staff land on `/admin`.

---

## 3. Architecture

```
routes/web.php
├── Public:        /  /tv  /api/queue-status  /api/purposes
├── Auth:          /login  /verify  /verify-otp  /forgot-password ...
├── Student:       /student/index  POST /queue        (auth:student)
├── Staff:         /admin/** call-next/reject/accept  (auth:web + is_staff)
└── Admin-only:    /admin/overview /departments /staff /counters /purposes
                                                      (auth:web + is_admin)

app/
├── Http/Controllers/
│   ├── QueueController.php      # join, public/TV views, queueState(), getStatus()
│   ├── StaffController.php      # call-next/skip/complete, pause, reports, avg calc
│   ├── DepartmentController.php # departments, staff assignment, counters
│   ├── AdminOverviewController.php  # cross-department overview + audit log
│   ├── PurposeController.php    # purposes CRUD + active list API
│   └── AuthController.php       # login, register, OTP, password reset
├── Events/QueueUpdated.php      # ShouldBroadcastNow → queue.{dept} / queue.updated
├── Services/
│   ├── QueueTransitionService.php  # guarded status transitions + audit rows
│   └── SmsService.php           # Textbee sender with log fallback
├── Models/  Department, Counter, QueueEntry, Guest, User, Purpose,
│            PhoneOtp, QueueAction, SmsNotification
└── Console/Commands/
    ├── AutoSkipQueue.php        # queue:auto-skip  (every minute)
    └── LunchBreakQueue.php      # queue:lunch-break (every minute)
```

**Key design decisions**

- **Per-department counters** (`counters` table): each counter serves one ticket;
  legacy rows without `counter_id` display on the first active counter.
- **Cache strategy**: `avg_serve_mins_{dept}_{date}` (60s) and
  `queue_status_dept_{id}` (3s polling cache, invalidated on every state
  change). Serving numbers are read live from `queue_entries`.
- **Audit trail**: every transition writes a `queue_actions` row (actor,
  department, ticket) shown in Admin → Audit Log.
- **Guards**: two auth guards — `web` (staff/admin `users`) and `student`
  (guest/student `guests`). Do not mix them.
- **Indexes**: composite `(department_id, queue_date, status)` and
  `(guest_id, queue_date, status)` on `queue_entries`.

**Database tables**: `users`, `guests`, `departments`, `counters`,
`queue_entries`, `purposes`, `phone_otps`, `queue_actions`,
`sms_notifications`, `settings` (+ standard Laravel tables).

---

## 4. Daily Operation

| Task | Where |
|---|---|
| Open staff dashboard | `/admin` (staff sees own department only) |
| Serve next student | Counter card → **Call Next here** |
| Finish / skip | **Complete** / **Skip** on the serving ticket |
| Pause a department | **Pause/Resume** (Manual mode) or switch Auto/Manual |
| Add windows | Admin → Departments & Staff → counters (1–20, toggle Active/Off; busy ones can't be turned off) |
| TV display | `/tv?department_id=1` — Now Serving grid per counter + Next + Waiting |
| Reports (PDF) | Admin → Reports → filter by date/department → Download |
| Change staff invite / lunch hours | `settings` table (`staff_invite_code`, `lunch_break_start`, `lunch_break_end`) |

## 5. Testing

Use the XAMPP PHP binary (see [PHP binary](#php-binary-important)):

```bash
# Linux (LAMPP)
/opt/lampp/bin/php artisan test --no-coverage
```

```bat
:: Windows (XAMPP)
"C:\xampp\php\php.exe" artisan test --no-coverage
```

`phpunit.xml` forces `APP_ENV=testing` + in-memory SQLite, and `tests/TestCase.php`
refuses to run against a non-testing database. If you see
`Refusing to run tests against environment ...`, run `/opt/lampp/bin/php artisan config:clear`
(or the Windows XAMPP binary) first — a stale `bootstrap/cache/config.php`
overrides the test env.

Current status: **30 tests passing (132 assertions)** in
`AdminOverviewTest`, `DepartmentQueueAccessTest`, `GuestQueueFlowTest`,
`ReliabilityTest`, `StaffQueueConsoleTest` — multi-counter concurrency,
pause/mode audit, auto-skip locking. CI (`.github/workflows/laravel.yml`)
runs the same suite on PHP 8.2.

## 6. Troubleshooting

| Symptom | Fix |
|---|---|
| `Deprecated ... nullable` warnings | You're on PHP 8.5 — switch to 8.2: `/opt/lampp/bin/php` (Linux) or `C:\xampp\php\php.exe` (Windows) |
| `php` points to the wrong version | Call the XAMPP binary explicitly (see [PHP binary](#php-binary-important)); Composer may also need `.../php .../composer` |
| Pages load slowly (500–900ms polls) | Run serve with the OPcache flags above; keep project on SSD, not external HDD; `artisan serve` is single-threaded — use Apache for multi-tab demos |
| OTP not arriving | Check Textbee key/device; else read OTP in `storage/logs/laravel.log` |
| Realtime not instant | Check Pusher creds — 5s polling fallback still works |
| Auto-skip / lunch pause dead | `artisan schedule:work` must be running (use the XAMPP `php` binary) |
| Staff can't register | First admin comes from `AdminSeeder`, not `/private/register` (disabled once an admin exists) |
| `419` on forms | Session expired — refresh and retry |

## License

Internal academic project (ACLC Mandaue). Laravel framework portions under MIT.
