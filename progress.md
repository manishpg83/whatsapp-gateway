# Progress — WhatsApp Gateway SaaS

> Living checklist. Read this together with `CLAUDE.md` at the start of every session.
> Update it at the end of every milestone (tick the box, add notes, set "Next step").
> Last updated: 2026-09-22

## Where we are

**M0–M5 are code-complete and automated-tested. M5 still needs ONE manual step from the owner: scan a real QR code with your phone to confirm the live Baileys connection actually works end to end (never exercised in CI — see M5 notes).**

## Milestones

- [x] **M0 — Environment & plan** (versions locked in `CLAUDE.md` section 3)
- [x] **M1 — Laravel bootstrap**
  - [x] Git repo at the root, root `.gitignore`
  - [x] Empty MySQL/MariaDB database `whatsapp_gateway`
  - [x] Laravel 13.x in `backend/` (no starter kit, MySQL driver, PHPUnit)
  - [x] Default migrations run (9 tables: users, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, migrations)
- [x] **M2 — Auth** (register, login, logout)
  - [x] Bootstrap 5.3.8 + `@popperjs/core` 2.11.8 installed via npm; Tailwind packages removed
  - [x] `RegisterController` and `LoginController` (`app/Http/Controllers/Auth/`)
  - [x] Password hashing, CSRF, session regeneration on login, session invalidation on logout
  - [x] Login rate limit: 5 failed attempts per email+IP, then 60 s lock
  - [x] Same error message for wrong email and wrong password (no account enumeration)
  - [x] Views: `layouts/app`, `auth/login`, `auth/register`
  - [x] `tests/Feature/AuthTest.php` (18 tests)
- [x] **M3 — Dashboard**
  - [x] `DashboardController` (single-action) + `/dashboard` route
  - [x] `/` redirects to `/dashboard` (guests then go on to `/login`)
  - [x] Real navbar (Dashboard active; Instances and API Docs are greyed-out "soon" placeholders)
  - [x] Dashboard cards: Instances = 0, "No instances yet", Account details; "Getting started" checklist
  - [x] `tests/Feature/DashboardTest.php` (8 tests); `ExampleTest` updated for the redirect
  - [x] Deleted the unused `welcome.blade.php` (it contained inlined Tailwind CSS)
  - Note: `instanceCount` / `connectedCount` in `DashboardController` are hard-coded to 0 until M5.
- [x] **M4 — Worker skeleton**
  - [x] `whatsapp-worker/` created: Node/TS, ESM (`"type": "module"`), Fastify, Zod, Vitest
  - [x] Packages: `fastify`, `zod` (deps); `typescript`, `tsx`, `vitest`, `@types/node`, `pino-pretty` (dev). No `dotenv` — Node's native `--env-file` loads `.env`. Fastify's built-in logger is Pino already, no separate `pino` package needed.
  - [x] `src/config.ts` — Zod-validated env (`HOST`, `PORT`, `INTERNAL_API_SECRET`; refuses to start without a secret ≥16 chars)
  - [x] `src/auth.ts` — `X-Internal-Secret` check, constant-time (SHA-256 the two values, then `crypto.timingSafeEqual` on the fixed-length digests — avoids both the length-mismatch throw and a length side-channel)
  - [x] `src/app.ts` builds the Fastify instance (testable via `.inject()`, no real port needed); `src/index.ts` is the thin entry point that calls `.listen()`
  - [x] Routes: `GET /health` (public), `GET /internal/ping` (protected demo route, proves the auth hook works — will be replaced by real endpoints from M5)
  - [x] `tests/health.test.ts`, `tests/auth.test.ts` — 4 tests, all passing; `npm run typecheck` clean
  - [x] Manually smoke-tested with `npm run dev` + `curl`: `/health` → 200, `/internal/ping` with no/wrong secret → 401, with correct secret → 200
  - [x] `.env` (real random secret, local only, git-ignored) + `.env.example` (documents the vars, no secret)
  - Installed versions: `fastify` ^5.12.5, `zod` ^4.6.5, `typescript` ^7.0.2, `tsx` ^4.23.15, `vitest` ^5.0.1, `@types/node` ^26.6.2, `pino-pretty` ^13.1.3.
- [x] **M5 — Instance + QR** *(code-complete; live QR scan still to be verified manually by the owner)*
  - **Backend (`backend/`):**
    - [x] `whatsapp_sessions` migration/table: `instance_id` (UUID, unique, public), `user_id` FK cascade-delete, `name`, `status` (`connecting`/`qr_pending`/`connected`/`disconnected`/`logged_out`), `qr_code`, `qr_updated_at`, `phone_number`, `connected_at`, `last_disconnect_reason`
    - [x] `WhatsappSession` model — route-bound by `instance_id` (never the internal id), auto-generates the UUID on creating; `User::whatsappSessions()` relation; `WhatsappSessionFactory` (with a `connected()` state) for tests
    - [x] `config/worker.php` (`WORKER_BASE_URL`, `INTERNAL_API_SECRET`) + `App\Services\WorkerClient` (thin `Http` facade wrapper — the only class allowed to call the worker)
    - [x] `App\Http\Middleware\VerifyInternalSecret` (constant-time `hash_equals`), aliased `internal.secret`, CSRF-exempted for its one route in `bootstrap/app.php`
    - [x] `InstanceController`: `index`, `create`, `store`, `show`, `status` (JSON, polled every 2.5s), `destroy` (disconnect), `reconnect`. Ownership enforced **both** via a query scoped to `$request->user()` and an explicit `abort_unless` check (defence-in-depth per CLAUDE.md §5); wrong-owner or unknown `instance_id` both 404 (never 403 — doesn't confirm another user's instance exists)
    - [x] `WorkerWebhookController` at `POST /internal/worker/events` (`qr.updated`, `connection.updated`), secret-protected, 404s on an unknown `instance_id`, 422s on an unrecognised event type
    - [x] Views: `instances/index`, `instances/create`, `instances/show` (plain `<img>` for the QR — the worker sends a ready-made PNG data URL, no browser QR library needed; vanilla-JS polling reloads the page once status becomes terminal)
    - [x] Navbar "Instances" link enabled; `DashboardController` now uses real `$user->whatsappSessions()` counts; added session-flash `alert-success`/`alert-danger` to the layout
    - [x] `backend/.env`/`.env.example` gained `WORKER_BASE_URL` + `INTERNAL_API_SECRET` (same secret value as the worker's `.env`, by design)
    - [x] `tests/Feature/InstanceTest.php`, `tests/Feature/WorkerWebhookTest.php`, plus a dashboard-counts test — all use `Http::fake()`, never hit a real worker
  - **Worker (`whatsapp-worker/`):**
    - [x] Installed `baileys` pinned **exactly** `7.0.0-rc14` (no `^`/`~` — npm adds `^` by default, had to hand-edit `package.json`), `qrcode` (turns the raw QR string into a PNG data URL), `@types/qrcode` (dev)
    - [x] `src/whatsapp/sessionManager.ts` — in-memory `Map<instance_id, socket>`; `startSession()`/`stopSession()`; auth files via Baileys' `useMultiFileAuthState()` under `SESSION_STORAGE_PATH`; reports QR + connection changes to Laravel
    - [x] `src/whatsapp/callbacks.ts` — `notifyLaravel()`, posts to `LARAVEL_CALLBACK_URL` with `X-Internal-Secret`, logs (doesn't throw) on failure
    - [x] `src/routes/sessions.ts` — `POST /sessions`, `DELETE /sessions/:instanceId`, both secret-protected. **Replaced** the M4 demo `/internal/ping` route (deleted, along with its test)
    - [x] `config.ts` gained `LARAVEL_CALLBACK_URL` (default `http://127.0.0.1:8000/internal/worker/events`) and `SESSION_STORAGE_PATH` (default `C:\whatsapp-secrets`)
    - [x] Created `C:\whatsapp-secrets\` on disk (outside the repo and outside `htdocs`, per CLAUDE.md §4/§9)
    - [x] `tests/sessions.test.ts` — only covers the auth/validation layer (401s, 400 on bad body, DELETE-of-unknown-id no-ops). **Deliberately does not** exercise a real `POST /sessions` success path in CI — that opens a real Baileys socket and reaches WhatsApp's servers, so it's verified manually instead (see below)
  - **Still to do (not code, an action):** with `php artisan serve` (8000) and the worker's `npm run dev` (3001) both running, log in, create an instance, and **scan the QR with your own WhatsApp** to confirm status flips to Connected with your phone number. This is the one part of M5 no automated test can cover.
  - Re-verified before installing: `baileys@7.0.0-rc14` was still `latest` on npm (unchanged since M0), `qrcode@1.5.4` current.
- [ ] **M6 — API credentials** — `api_tokens` table, token shown once, SHA-256 hash stored, revocable
- [ ] **M7 — Send text message** — `POST /api/v1/messages/send`, token auth + ownership check, synchronous send via worker, `messages` table
- [ ] **M8 — Incoming messages + webhooks**

## Test status

`php artisan test` (from `backend/`): **51 passed, 189 assertions** (as of M5). `vendor/bin/pint --test`: clean.

`npm test` (from `whatsapp-worker/`, Vitest): **6 passed** (as of M5). `npm run typecheck`: clean.

## Things to remember (learned the hard way)

**Workflow (from CLAUDE.md, the owner is a beginner):** one milestone per turn; inspect, explain, list files/commands and **wait for approval** before coding; after each milestone give what changed, commands, expected output and how to test, then **STOP**. The owner makes **all git commits** — never commit unless explicitly asked.

**Tests use a separate database.**
- `phpunit.xml` points at MySQL database `whatsapp_gateway_test`. It was created empty (tables come from migrations, not by hand). Tests wipe it on every run.
- Test classes that use `RefreshDatabase` include a `beforeRefreshingDatabase()` guard that aborts unless the DB name is `whatsapp_gateway_test`. **Copy this guard into every new DB test class.**
- Why: the default SQLite in-memory setup can't work here (PHP has no `pdo_sqlite`; we don't touch `php.ini`) and CLAUDE.md says MySQL only.
- Feature tests that render pages call `$this->withoutVite()`.

**The real database `whatsapp_gateway` contains a real user** (Prachi Barot, id 2) from the owner's own testing. Never wipe it. When smoke-testing against the real DB, use an `@example.invalid` email and delete exactly that row afterwards.

**Ports.** The owner usually has `php artisan serve` running on **8000**. For smoke tests, use **8001** and stop only the PID whose command line contains `127.0.0.1:8001` (the `artisan serve` PowerShell process spawns a child `php.exe -S`, so kill the port's owner, not just the launcher). The worker uses `127.0.0.1:3001`.

**Shared secret.** `backend/.env`'s `INTERNAL_API_SECRET` and `whatsapp-worker/.env`'s `INTERNAL_API_SECRET` must be the **exact same string** — that's the whole mechanism. If you ever regenerate one, copy it into the other too. WhatsApp auth files live in `C:\whatsapp-secrets\` (one folder per `instance_id`), outside the repo/`htdocs` on purpose — never move that under `backend/` or `whatsapp-worker/`.

**Front-end.** Bootstrap comes in through `resources/css/app.css` (`@import 'bootstrap/dist/css/bootstrap.min.css'`) and `resources/js/app.js` (`import 'bootstrap'`). No Sass, no Tailwind. Run `npm run build` (or `npm run dev`) so pages get their CSS; `public/build` is git-ignored.

**Environment fixes done on 2026-09-21 (Windows/XAMPP), in case they come back:**
- *Apache + PHP curl:* `php_curl.dll` failed to load under Apache because Apache's older `apache\bin\libssh2.dll` (1.10.0) was found before PHP's (1.11.1). Fixed with one line in `C:\xampp\apache\conf\extra\httpd-xampp.conf`: `LoadFile "C:/xampp/php/libssh2.dll"` (backup: `httpd-xampp.conf.pre-libssh2fix.bak`). Apache must be restarted from the control panel for it to apply.
- *phpMyAdmin 5.2.3:* had no `config.inc.php` (the earlier edit went into `config.sample.inc.php`). Created `C:\xampp\phpMyAdmin\config.inc.php` (cookie auth, `127.0.0.1:3306`, `AllowNoPassword = true`, random `blowfish_secret`).
- *"MySQL shutdown unexpectedly" in the XAMPP panel:* caused by several `xampp-control.exe` windows open at once; a second panel starts a second `mysqld`, which fails on the locked `ibdata1`. Use only ONE control panel. MariaDB itself was fine.
- `apache\bin\libcurl.dll` is still renamed to `libcurl.dll.apache-backup` (it was never the cause; nothing enabled needs it). Optional: rename it back.
- Keep Apache **stopped** unless phpMyAdmin is needed (it serves `C:\xampp\htdocs`, including `.env` files).
- Untouched on purpose: MariaDB 10.4.32 config/port, OSGeo4W, `C:\xampp\php-8.2.12-backup`, `C:\xampp\phpMyAdmin-5.2.1-backup`.

## Next step

**Finish verifying M5 live, then start M6.** At the start of the next session:
1. Run `php artisan serve` (backend, 8000) and `npm run dev` (worker, 3001) together, log in, create an instance, and scan the real QR with your phone — confirm it reaches "Connected" with your phone number, then confirm "Disconnect" and "Reconnect" both work.
2. Once that's confirmed, read `CLAUDE.md` §13 (M6 row), inspect the repo, then present the M6 plan (`api_tokens` table, token generation shown once, SHA-256 hash stored, revocable) and **wait for approval** before creating anything.
