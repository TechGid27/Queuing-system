# ACLC Mandaue — Queuing System

Department-based queue management with multi-counter serving, realtime updates, OTP verification, and TV display.

## Features

- **Student / Guest queue** — join per department, one active ticket per day, duplicate prevention
- **Multi-counter serving (Phase 2)** — each department has its own front desks
  (e.g. Admission = 4 windows, Cashier = 2). Each counter serves one ticket at a time,
  staff claims the next waiting ticket to a free counter. One staff = one serving at a time.
- **Auto / Manual pause mode (per department)**
  - `Auto` — lunch-break scheduler pauses/resumes, manual Pause/Resume hidden
  - `Manual` — staff controls Pause/Resume, lunch auto disabled for that department
- **Staff / Admin roles** — staff locked to one department, admin oversees all + audit log + reports (PDF)
- **Realtime** — Pusher (`queue.{department_id}` → `queue.updated`) with 5-second polling fallback
- **TV display** — Now Serving grid per counter + Next + Waiting list
- **Auto-skip** — unresponsive `serving` tickets auto-skip after 3 minutes and refill free counters
- **Phone OTP** (Textbee) — verification + resend limits, log fallback for local dev

## Tech Stack

Laravel 9 · PHP 8.2 · MySQL · Pusher Channels · Textbee SMS · Tailwind CSS · Blade

## Quick Start

```bash
cp .env.example .env
# set DB_*, PUSHER_*, TEXTBEE_API_KEY / TEXTBEE_DEVICE_ID (see QUICK_START.md)
php artisan migrate
php artisan db:seed --class=PurposeSeeder   # optional
php artisan serve
```

Scheduler (lunch auto-pause + auto-skip) — every minute:

```bash
php artisan schedule:work
# runs queue:lunch-break + queue:auto-skip
```

See `QUICK_START.md` for Pusher/Textbee keys and test flows,
`IMPLEMENTATION_NOTES.md` for earlier implementation details.

## Counters

- Admin → **Departments & Staff** → set number of windows on create (1–20),
  add more or toggle Active/Off per counter. A busy counter cannot be deactivated.
- Staff dashboard → **Now Serving** cards per counter → `Call Next here`,
  `Skip` / `Complete` per ticket. Realtime sync via `/admin/waiting-list`.
- TV (`/tv?department_id=`) shows the serving grid per window.

## Key Routes

| Method | URI | Name |
|---|---|---|
| GET | `/`, `/tv` | `home`, `tv` |
| POST | `/queue` | `queue.store` |
| GET | `/admin`, `/admin/queue` | `admin.index`, `admin.queue` |
| POST | `/admin/call-next` (`counter_id?`) | `admin.callNext` |
| POST | `/admin/reject/{id}`, `/admin/accept/{id}` | `admin.reject`, `admin.complete` |
| POST | `/admin/toggle-pause` | `admin.togglePause` |
| POST | `/admin/pause-mode` (`auto\|manual`) | `admin.pauseMode` |
| POST | `/admin/counters`, `PATCH /admin/counters/{counter}/status` | `admin.counters.*` |

## Tests

```bash
php artisan test
# includes: multi-counter concurrent serving, pause/mode audit, auto-skip locking path
# known pre-existing failure: GuestQueueFlowTest private-register route (commented out in routes)
```

## License

Internal academic project (ACLC Mandaue). Laravel framework portions under MIT.
