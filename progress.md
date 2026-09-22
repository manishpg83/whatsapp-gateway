# Progress — WhatsApp Gateway SaaS

> Living checklist. Read this together with `CLAUDE.md` at the start of every session.
> Update it at the end of every milestone (tick the box, add notes, set "Next step").
> Last updated: 2026-09-22

## Where we are

**All 8 roadmap milestones (M0-M8) are code-complete, automated-tested, AND verified live: M5 (real QR scan), M7 (real outbound send), M8 (real inbound messages stored — `direction=incoming`, `status=received`, confirmed via direct DB query, not just trusting the UI). M4/M5/M6/M7 committed by the owner (`c985028`/`05be00c`/`109c521`/`41f802f`); M8 not yet committed.**

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
- [x] **M5 — Instance + QR** *(verified live — owner scanned a real QR and reached Connected)*
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
    - [x] `tests/sessions.test.ts` — only covers the auth/validation layer (401s, 400 on bad body, DELETE-of-unknown-id no-ops). **Deliberately does not** exercise a real `POST /sessions` success path in CI — that opens a real Baileys socket and reaches WhatsApp's servers, so it's verified manually instead
  - **Post-live-test bugfix:** the first real QR scan surfaced two bugs, both fixed in `sessionManager.ts`:
    1. Right after a fresh pairing, WhatsApp always closes the connection once with `restartRequired` (515) — expected protocol behaviour, not a real disconnect. The worker was treating it as terminal ("Not connected", needed a manual Reconnect click). Now it reconnects automatically with the saved creds and never bothers Laravel about it.
    2. Phone number was showing the WhatsApp device-id suffix (`919054961320:9`) because `socket.user.phoneNumber` isn't populated, so it fell back to `socket.user.id` unparsed. Fixed to strip both `:` and `@`.
  - Re-verified before installing: `baileys@7.0.0-rc14` was still `latest` on npm (unchanged since M0), `qrcode@1.5.4` current.
  - Committed by the owner as `c985028` (M4) and `05be00c` (M5, includes the bugfix).
- [x] **M6 — API credentials**
  - [x] `api_tokens` migration/table: `whatsapp_session_id` FK cascade-delete, `name`, `token_hash` (unique, SHA-256 — not bcrypt, per CLAUDE.md §6), `token_prefix` (first 8 chars, shown forever in the UI so a token is identifiable without ever re-showing it), `last_used_at`, `revoked_at`
  - [x] `ApiToken` model: `generateFor(WhatsappSession, name)` creates a random 64-char token, returns the **plaintext to the caller only** (never stored/logged); `WhatsappSession::apiTokens()` relation; `ApiTokenFactory` (with a `revoked()` state)
  - [x] `ApiTokenController`: `store` (generate — **gated on `status === 'connected'`**, 422 otherwise, per the owner's explicit choice), `destroy` (revoke — sets `revoked_at`, never hard-deletes). Same ownership pattern as M5: scoped query + explicit `abort_unless`, 404 either way
  - [x] Routes nested under the instance: `POST /instances/{instance}/tokens`, `DELETE /instances/{instance}/tokens/{token}`
  - [x] `instances/show.blade.php` gained an "API credentials" card: existing tokens (name/prefix/created/last-used/Revoke), a one-time plaintext reveal read from `session('new_token')` (gone on the next unrelated page load), a "Generate token" form when connected, a note to connect first otherwise
  - [x] `tests/Feature/ApiTokenTest.php` (9 tests): hash-not-plaintext stored, one-time reveal, connected-gating (422), cross-user (404), cross-instance token/instance mismatch (404)
  - No new packages — `Str::random()` + `hash('sha256', ...)`, both already in Laravel/PHP.
- [x] **M7 — Send text message**
  - **Backend (`backend/`):**
    - [x] `messages` migration/table: `whatsapp_session_id` FK cascade-delete, `direction` (`outgoing` for now), `to_number`, `from_number` (unused until M8), `body`, `status` (`pending`/`sent`/`failed`), `whatsapp_message_id`, `error`
    - [x] `Message` model + `WhatsappSession::messages()` relation + `MessageFactory`
    - [x] `routes/api.php` **created and wired up** (`bootstrap/app.php` had no `api:` routing group before this — added it, prefixes routes with `api/` automatically, matching `CLAUDE.md`'s `/api/v1/...` contract)
    - [x] `AuthenticateApiToken` middleware (alias `api.token`): `Authorization: Bearer` → SHA-256 lookup against `token_hash`, rejects missing/unknown/revoked (401), updates `last_used_at`, attaches the resolved `WhatsappSession` to the request — controllers never trust a caller-supplied user/session id, only what the token itself resolves to
    - [x] `Api\MessageController@send`: validates `instance_id` (uuid) / `to` (7-15 digits) / `message` (≤4096 chars), cross-checks body `instance_id` against the token's own instance (422 on mismatch — defence-in-depth beyond the token alone), 422 if not connected, creates a `pending` message row, calls the worker synchronously, updates to `sent`/`failed`
    - [x] `WorkerClient::sendMessage()` added
    - [x] Rate limiting: `RateLimiter::for('messages', ...)` in `AppServiceProvider` — 30/min, keyed by the bearer token (falls back to IP) — first milestone that can spam a real phone number, so it got one
    - [x] `tests/Feature/MessageTest.php` (9 tests): auth (missing/invalid/revoked), `instance_id` mismatch, not-connected, invalid `to` format, successful send (`Http::fake()`, asserts `sent` + `last_used_at` updated), worker failure (asserts `failed` + `error` stored), rate-limit headers present
  - **Worker (`whatsapp-worker/`):**
    - [x] `sessionManager.ts` gained `sendMessage(instanceId, to, text)` — looks up the live socket, throws `SessionNotActiveError` if the instance isn't actively connected in this worker process (individual chats only — `<to>@s.whatsapp.net`, no group JIDs, per `CLAUDE.md` §0 scope)
    - [x] `src/routes/sessions.ts` gained `POST /sessions/:instanceId/messages` (secret-protected, Zod-validated) — 409 when not active, 502 on any other Baileys failure
    - [x] `tests/sessions.test.ts` — unlike `/sessions`, this route's failure path (no active session) never touches Baileys/network, so it's fully tested here: auth, bad `to`/empty `message` → 400, no active session → 409 (10 tests total in the file)
  - No new packages either side — `Str`/`hash()`/`RateLimiter` (Laravel), Baileys' existing `sendMessage()` (worker).
  - **Verified live:** owner sent a real message via `Invoke-RestMethod`; it arrived (in "Message Yourself", since the test happened to use the connected instance's own number as `to` — a good reminder for next time: pick a genuinely different number to prove third-party delivery); `messages` row confirmed `status = sent` with the real WhatsApp `whatsapp_message_id`.
  - **Rough edge hit during live testing (not a code bug, a dev-workflow gotcha — see "Things to remember"):** the DB can say `connected` while the worker process actually has no live socket, if the worker restarted since the last real connect. First attempt 502'd because of exactly this.
- [x] **M8 — Incoming messages + webhooks**
  - **Worker (`whatsapp-worker/`):**
    - [x] `src/whatsapp/incomingMessage.ts` — new pure function `parseIncomingMessage()`: decides whether a Baileys `messages.upsert` entry is forwarded (skips messages we sent ourselves, groups (`@g.us`), the status/broadcast feed, and anything without plain text) and extracts sender/text/id/timestamp. Pulled out as a pure function (no socket, no network) specifically so this filtering logic — the part most likely to have a bug, e.g. group messages leaking through — can be unit tested directly
    - [x] `sessionManager.ts`: `connect()` now also listens for `messages.upsert`, uses `parseIncomingMessage()`, reports matches via a new `message.received` worker event. Also logs every `messages.upsert` (type + count) and every skip/forward decision — added mid-live-test because success was previously silent, making a real bug indistinguishable from "hasn't arrived yet". Worth keeping permanently, not just a debug throwaway.
    - [x] `callbacks.ts`: `WorkerEvent` union gained the `message.received` variant
    - [x] `tests/incomingMessage.test.ts` (8 tests) — the one piece of M8 that COULD be fully unit tested without a live WhatsApp connection, so it is; everything else stays manual-verification-only, same reasoning as M5/M7
  - **Backend (`backend/`):**
    - [x] `whatsapp_sessions` gained `webhook_url` + `webhook_secret` (nullable; secret auto-generated the first time a URL is set, kept — not regenerated — on later URL changes) — columns on the existing table, not a new one, since it's strictly 1:1 per instance
    - [x] `WorkerWebhookController` gained `message.received` handling: validates the payload, creates a `messages` row (`direction = 'incoming'`, `status = 'received'`), then calls the new `WebhookDispatcher`
    - [x] `App\Services\WebhookDispatcher`: best-effort, synchronous (no queue yet, same MVP stance as M7) POST to the owner's `webhook_url` with the raw JSON body HMAC-SHA256-signed (`X-Webhook-Signature: sha256=...`) using `webhook_secret`; every failure is caught and logged, never thrown — a dead webhook receiver must never break message storage. **Documented, not solved:** this makes a server-side request to a URL the owner typed in (SSRF surface) — acceptable for this MVP's threat model, flagged in the class docblock for later hardening
    - [x] `InstanceController::updateWebhook()` (`POST /instances/{instance}/webhook`) — set/update/clear, same ownership pattern as everything else
    - [x] `instances/show.blade.php` gained: a Webhook card (URL form + visible signing secret + the signature header format spelled out for the owner to implement on their end), and a Recent messages card (last 20, direction badge, number, truncated body, status, relative time) — the UI-scope addition the owner opted into
    - [x] `InstanceController::show()` now also passes `$instance->messages()->latest()->take(20)->get()`
    - [x] Tests: `WorkerWebhookTest.php` (+4: stores incoming messages, dispatches with a verified-correct signature, no dispatch when no webhook configured, dispatch failure doesn't fail the original request), `InstanceWebhookTest.php` (5 tests: set/update-keeps-secret/clear/invalid-URL/cross-user 404), `InstanceTest.php` (+2: show page lists messages / says "No messages yet")
  - No new packages either side.
  - **Verified live** (took several tries — see "Things to remember" for the three separate gotchas hit along the way): two genuine incoming messages from a different phone number, sent as a direct 1-on-1 chat, are stored with `direction = incoming`, `status = received`, confirmed by querying the `messages` table directly rather than trusting the UI alone.

## Test status

`php artisan test` (from `backend/`): **78 passed, 294 assertions** (as of M8). `vendor/bin/pint --test`: clean.

`npm test` (from `whatsapp-worker/`, Vitest): **18 passed** (as of M8). `npm run typecheck`: clean.

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

**Webhook delivery (M8) is synchronous and best-effort, like the worker calls in M5-M7** — no queue, no retries. A slow or dead receiving endpoint adds latency to (but can't fail) the request that stores the incoming message, since `WebhookDispatcher` catches everything. `webhook_url` is also a straightforward SSRF surface (server-side request to an owner-supplied URL) — flagged in the class docblock, not solved; fine for this MVP's single-beginner-instance threat model, revisit before this is ever exposed more broadly.

**The worker's "connected" state is in-memory only — it does NOT survive the worker process restarting** (including `tsx watch` auto-reloading on every file save during active development). The database can keep saying `status = connected` long after the actual worker process has forgotten it, because nothing currently re-checks that in the background. Symptom: any worker action (most obviously sending a message) fails with a 409/502 even though the instance page says "Connected". Fix: click **Disconnect** then **Reconnect** on the instance page — this re-establishes a real socket in whichever worker process is currently running (no QR needed, it reuses the saved credentials in `C:\whatsapp-secrets\`). Hit this for real during M7 live-testing right after a run of worker code edits, and again during M8 (I was still editing worker files, so it kept recurring).

**Testing "incoming message" (M8) live has three separate, easy-to-hit false negatives — all correct behaviour, not bugs, but confusing until you know them:**
1. **Messaging the connected number FROM the connected number itself** (or from any of its own linked devices — including "Message Yourself" on the same phone) always looks like `fromMe: true` to Baileys, account-wide, not device-specific. It will never be treated as incoming. Must send from a genuinely different WhatsApp account.
2. **Sending into a group** that includes the number, instead of a direct 1-on-1 chat, arrives with `remoteJid` ending `@g.us` and is intentionally ignored (individual chats only, matches the send-side scope). Must open a private/direct chat with the exact number.
3. **Messages sent while the worker was offline/reconnecting** get delivered on reconnect as a backlog sync (`messages.upsert` `type: "append"`), not a live event (`type: "notify"`) — intentionally not forwarded, so as not to replay old history as if it just arrived. Wait until the instance page settles on "Connected" (a few seconds) before sending the test message.

`sessionManager.ts` now logs every `messages.upsert` (with `type` and count) and every forward/skip decision — added specifically because success was previously silent, which made "it's genuinely broken" indistinguishable from "it hasn't arrived yet" during live debugging. Check the worker terminal directly when this needs diagnosing again.

**Front-end.** Bootstrap comes in through `resources/css/app.css` (`@import 'bootstrap/dist/css/bootstrap.min.css'`) and `resources/js/app.js` (`import 'bootstrap'`). No Sass, no Tailwind. Run `npm run build` (or `npm run dev`) so pages get their CSS; `public/build` is git-ignored.

**Environment fixes done on 2026-09-21 (Windows/XAMPP), in case they come back:**
- *Apache + PHP curl:* `php_curl.dll` failed to load under Apache because Apache's older `apache\bin\libssh2.dll` (1.10.0) was found before PHP's (1.11.1). Fixed with one line in `C:\xampp\apache\conf\extra\httpd-xampp.conf`: `LoadFile "C:/xampp/php/libssh2.dll"` (backup: `httpd-xampp.conf.pre-libssh2fix.bak`). Apache must be restarted from the control panel for it to apply.
- *phpMyAdmin 5.2.3:* had no `config.inc.php` (the earlier edit went into `config.sample.inc.php`). Created `C:\xampp\phpMyAdmin\config.inc.php` (cookie auth, `127.0.0.1:3306`, `AllowNoPassword = true`, random `blowfish_secret`).
- *"MySQL shutdown unexpectedly" in the XAMPP panel:* caused by several `xampp-control.exe` windows open at once; a second panel starts a second `mysqld`, which fails on the locked `ibdata1`. Use only ONE control panel. MariaDB itself was fine.
- `apache\bin\libcurl.dll` is still renamed to `libcurl.dll.apache-backup` (it was never the cause; nothing enabled needs it). Optional: rename it back.
- Keep Apache **stopped** unless phpMyAdmin is needed (it serves `C:\xampp\htdocs`, including `.env` files).
- Untouched on purpose: MariaDB 10.4.32 config/port, OSGeo4W, `C:\xampp\php-8.2.12-backup`, `C:\xampp\phpMyAdmin-5.2.1-backup`.

## Next step

**All 8 roadmap milestones are code-complete AND live-verified. `CLAUDE.md`'s roadmap (§13) formally ends at M8.** At the start of the next session:

1. Remind the owner to commit M8 when ready — M4/M5/M6/M7 are already committed (`c985028`/`05be00c`/`109c521`/`41f802f`), never commit without being asked.
2. Ask the owner what's next — don't assume. Likely candidates not yet built: queues/Redis for the worker calls (everything is still synchronous, flagged throughout M5-M8), webhook retries (currently one best-effort attempt, no retry on failure), a production deploy plan (still `php artisan serve`/`npm run dev` only), or continued hardening/polish. This is a genuine open conversation, not a scripted milestone.
