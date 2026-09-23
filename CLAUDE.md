# CLAUDE.md — WhatsApp Gateway SaaS

> This file is the standing instruction set for any AI coding assistant working in this
> repository. Read it fully before doing anything. The rules in **Section 1 (Golden Rules)**
> override convenience, speed, and cleverness. When in doubt, stop and ask.

---

## 0. What we are building

A multi-tenant **WhatsApp Gateway SaaS** (conceptually similar to WaTrend). A registered user can:

1. Register / log in.
2. Create a WhatsApp connection ("instance").
3. See a QR code and scan it with their normal WhatsApp mobile app.
4. Get the instance to **Connected** state.
5. Receive an `instance_id` and an `access_token`.
6. Call our REST API to **send WhatsApp text messages**.
7. Later: receive incoming messages and get them via **webhooks**.

**Integration approach:** unofficial WhatsApp Web / multi-device session via a Node.js
WhatsApp-Web-compatible library (**Baileys**). We do **NOT** use the Meta WhatsApp Business
Cloud API.

**Hard boundary — non-negotiable:** This is an unofficial device-session integration. We do
**not** build, suggest, or install anything designed to bypass WhatsApp security, CAPTCHA,
rate limits, account bans/enforcement, or any platform protection. If a task drifts toward
evasion of platform enforcement, stop and say so.

---

## 1. GOLDEN RULES (behavioural — read every time)

The project owner is a **beginner**. Work accordingly:

1. **Small milestones only.** Never build everything at once. One milestone per turn.
2. **Explain before you code.** Before any significant change:
   - inspect the current project state,
   - explain what you will change and why,
   - list the exact files and commands involved,
   - then **wait for approval**.
3. **After each milestone**, always provide:
   - what was created/changed,
   - the exact commands to run,
   - the expected output,
   - how to test it,
   - then **STOP and wait for confirmation**. The owner will usually reply `done`.
4. **Do not auto-continue** to the next milestone. Wait for the go-ahead.
5. **Do not silently install packages.** Name every package, its purpose, and get approval
   before installing anything non-trivial.
6. **Do not rewrite working code** unnecessarily. **Do not refactor unrelated code.**
7. **No speculative architecture.** Don't build abstractions, tables, or services the current
   milestone doesn't need.
8. **Prefer simple, maintainable code** over clever code. Optimise for a beginner reading it later.
9. **Verify before locking versions** (Laravel, Baileys, Node) — see Section 3.
10. When explaining commands, assume **Windows + XAMPP + PowerShell/CMD**. Give copy-pasteable commands.

---

## 2. Architecture

```
Browser
  │  (HTTPS, Blade + Bootstrap dashboard, session auth)
  ▼
Laravel (PHP)  ── MySQL (users, instances, tokens, messages)
  │  │
  │  └── Redis / Laravel Queue (jobs, cache, rate limiting)
  │
  │  internal HTTP API (shared secret)
  ▼
Node.js WhatsApp Worker (TypeScript, Fastify, Pino, Zod)
  ▼
Baileys
  ▼
WhatsApp Web / device session
```

**Responsibility split (do not blur this line):**

- **Laravel owns:** users, auth, dashboard, instance records, API credentials, message
  records, public API, queues, webhooks, business logic, ownership/authorization.
- **Node worker owns:** WhatsApp sessions, QR generation/refresh, connection state, auth
  state, sending/receiving messages, reconnects, WhatsApp-specific events.
- **Laravel must NEVER talk to WhatsApp directly.** All WhatsApp interaction goes through the worker.

---

## 3. Tech stack & version policy

**Backend:** Laravel, PHP 8.4 (local), MySQL, Blade, Bootstrap 5, Vite, Laravel Queues, Redis.
Do **NOT** use PostgreSQL. Local DB **must** be MySQL (XAMPP/phpMyAdmin). Do **NOT** use Tailwind.

**Worker:** Node.js, TypeScript, Baileys, Fastify (or similar lightweight server), Pino
logging, Zod validation, Vitest for tests.

### Locked versions (decided and verified at M0, 2026-09-21 — do not re-litigate)

| Item | Decision |
|---|---|
| Laravel | **13.x** (latest seen: v13.32.0; use constraint `^13.0`). Requires PHP ^8.3. Laravel 12 rejected: bug fixes ended 2026-08-13. **Installed at M1: v13.32.0** (skeleton `laravel/laravel` v13.10.1, PHPUnit 12.5.35). |
| PHP | 8.4.1 (XAMPP, `C:\xampp\php.exe`, ini: `C:\xampp\php.ini`) |
| Composer / Laravel installer | 2.8.12 / 5.24.9 |
| Node.js / npm | v24.11.0 (LTS) / 11.6.1. Baileys needs Node >=20. |
| Git | 2.51.0 |
| Database | XAMPP **MariaDB 10.4.32**, accepted as our "MySQL" for local dev (Laravel supports MariaDB 10.3+). Laravel driver: `mysql`. Dev `root` with no password is for local only. Production must use a current MariaDB / MySQL 8. |
| Baileys | Package **`baileys`**, pinned to the **exact** version `7.0.0-rc14` (no `^`/`~`). It is a release candidate; `@whiskeysockets/baileys` is the same release and is not used. ESM-only (`"type": "module"`), so the worker must be an ES-module TypeScript project. **Not installed yet — install at M5 with approval.** |
| Testing | PHPUnit (backend), Vitest (worker) |
| Auth | Hand-built Laravel session auth using the built-in `Auth` facade. No starter kit, no Tailwind. Bootstrap 5 is added via npm at M2, with package approval. |
| Redis | Not installed. Docker Desktop is installed but its engine must be started manually when needed. |

**PHP extensions.** Laravel 13 requires `ctype`, `filter`, `hash`, `mbstring`, `openssl`,
`session`, `tokenizer`. In practice we also need `pdo` + **`pdo_mysql`** (enabled 2026-09-21),
`fileinfo`, `curl`, `xml`. Check with `php -m`. Do not change `php.ini` without asking.

**Re-verify (`npm view baileys version`, Packagist) before installing anything new; if a
version changes, update this table.**

---

## 4. Repository structure

Two apps in one repo. Use Laravel's **normal** structure inside `backend/` — do not fight the
framework. If a proposed structure conflicts with Laravel conventions, explain the trade-off
before changing anything.

```
whatsapp-gateway/
├── CLAUDE.md
├── README.md
├── PROJECT_BRIEF.md        # the original brief
├── .gitignore
│
├── backend/                # Laravel app (standard Laravel layout inside)
│   ├── app/  database/  resources/  routes/  tests/
│   ├── composer.json
│   └── package.json        # Vite / Bootstrap front-end tooling
│
├── whatsapp-worker/        # Node.js + TypeScript worker
│   ├── src/  tests/
│   ├── package.json
│   └── tsconfig.json
│
└── docs/
```

**WhatsApp session data lives OUTSIDE the repo and OUTSIDE `C:\xampp\htdocs`**, e.g.
`C:\whatsapp-secrets\` (exact path set in the worker's `.env` when M4/M5 needs it). Reason:
Apache serves `htdocs`, so anything under it could be web-reachable. `.gitignore` still lists
`secrets/` as a safety net. The repo is **one Git repo at the root** (`git init` at the root;
do not let `laravel new` create a second `.git` inside `backend/`).

---

## 5. Multi-tenancy & authorization (critical)

This is a multi-tenant SaaS. **One user must never access another user's** instances, messages,
tokens, or session data.

- Every query that touches tenant data must be **scoped to the owner** (e.g. via the
  authenticated user / instance ownership). Never trust an `instance_id` from a request without
  verifying it belongs to the caller.
- Every API endpoint must enforce ownership **server-side**, not in the UI.
- Prefer defence-in-depth: scope in the query **and** an explicit authorization check.

---

## 6. Two separate auth systems

1. **Dashboard (web):** standard Laravel session auth (login → session → dashboard). CSRF on all
   web forms.
2. **Public messaging API:** `instance_id` + `access_token`.
   - Token is **cryptographically random**.
   - Token is shown to the user **once** at generation time.
   - Store only a **hash** of the token — never plaintext. Use **SHA-256** (not bcrypt): tokens
     are long random strings, and a fast hash lets us look the token up directly.
   - Token is **revocable** and tied to an instance/user.
   - **Never log raw tokens.** Never log raw passwords.

Conceptual API (do **not** finalise the contract until DB/session design is reviewed):

```
POST /api/v1/messages/send
Authorization: Bearer ACCESS_TOKEN
{ "instance_id": "...", "to": "919XXXXXXXXX", "message": "Hello" }
→ { "success": true, "message_id": "..." }
```

The endpoint must authenticate the token, verify ownership, then dispatch the send via the worker.

---

## 7. Database

- MySQL only. Managed via **Laravel migrations** — never hand-create app tables in phpMyAdmin.
  phpMyAdmin is for **viewing/inspecting** during development.
- **Start minimal.** Initial entities to plan around (not an order to create all at once):
  `users`, `whatsapp_sessions` (instances), `api_tokens`, `messages`.
- Add tables only when a milestone genuinely needs them. No giant up-front schema.

**Approved table plan (each table is created only in its milestone):**

| Milestone | Table | Key columns |
|---|---|---|
| M1 | Laravel framework defaults: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Created by the default migrations. `sessions` is needed because the default session driver is `database`. |
| M5 | `whatsapp_sessions` | `id`, **`instance_id` (UUID, public)**, `user_id` FK, `name`, `status`, `qr_code`, `qr_updated_at`, `phone_number`, `connected_at`, `last_disconnect_reason`, timestamps |
| M6 | `api_tokens` | `id`, `whatsapp_session_id` FK, `name`, `token_hash` (unique), `token_prefix`, `last_used_at`, `revoked_at`, timestamps |
| M7 | `messages` | `id`, `whatsapp_session_id` FK, `direction`, `to_number`/`from_number`, `body`, `status`, `whatsapp_message_id`, `error`, timestamps |
| M8 | webhook settings | Decided at M8 |

- The public `instance_id` is a **UUID**, never the auto-increment `id` (prevents enumeration).
- Baileys auth files stay on the worker's disk, **not** in the database.

---

## 8. Laravel ↔ Worker communication

Internal HTTP API, authenticated with a **shared internal secret** (in `.env`, never committed).
Do not expose the worker's internal API publicly unless a milestone requires it.

Conceptual endpoints:

- **Laravel → Worker:** `POST /sessions`, `DELETE /sessions/{id}`, `POST /sessions/{id}/messages`
- **Worker → Laravel (callbacks):** QR update, connection status, message received, message status

**Approved details (exact payloads are still finalised at M4/M5):**

- The worker listens on **`127.0.0.1:3001` only**. Laravel runs on port 8000.
- **One shared secret** (`INTERNAL_API_SECRET` or similar) in both `.env` files, sent as an
  `X-Internal-Secret` header in both directions and compared in **constant time**.
- Worker → Laravel callbacks go to `POST /internal/worker/events`
  (event types: `qr.updated`, `connection.updated`, later `message.received`, `message.status`).
- The **QR reaches the browser via Laravel**: the worker posts the QR, Laravel stores it, and the
  page **polls** every 2–3 seconds. No WebSockets / Reverb.

Finalise the full contract **after** the DB/session architecture is reviewed and approved.

---

## 9. WhatsApp session storage

- Each instance has its own isolated session/auth data.
- A user may eventually own multiple instances (A, B, C…), each independent.
- **MVP:** store session credentials in a **local directory outside the repo and outside
  `C:\xampp\htdocs`** (e.g. `C:\whatsapp-secrets\`; see Section 4). One sub-folder per
  `instance_id`. **Never commit WhatsApp auth credentials to Git.**
- Later (not now): consider encrypted / DB-backed session storage.

---

## 10. Security checklist (apply throughout)

- Password hashing (framework default).
- CSRF protection on all web forms.
- Input validation everywhere (Laravel validation + Zod in the worker).
- Ownership/authorization checks on every tenant resource.
- Cryptographically random API tokens, stored **hashed**, revocable.
- Rate limiting where appropriate (Laravel `RateLimiter` on the `database` cache driver during
  the MVP; move to Redis later).
- All secrets in `.env`; nothing secret committed to Git.
- Safe logging: no raw tokens, no raw passwords, no full session credentials in logs.
- Nothing that bypasses WhatsApp/platform enforcement (see Section 0).

---

## 11. UI

Bootstrap 5, clean and simple SaaS dashboard. No Tailwind, no heavy UI framework. Function first,
polish later. Initial pages: Login, Register, Dashboard, Instances list, Create/Connect Instance,
QR screen, Instance details, API credentials, API docs/usage page.

---

## 12. Local dev environment

Windows + XAMPP. MySQL via XAMPP, phpMyAdmin for inspection, Docker Desktop available (for Redis
later). Run Laravel with `php artisan serve` during MVP — **no** Apache/nginx production setup yet.

**In the XAMPP control panel, start only MySQL. Keep Apache stopped** (it would serve
`C:\xampp\htdocs`, including `.env` files). phpMyAdmin needs Apache, so start Apache only briefly
when you need phpMyAdmin, then stop it. Alternatively inspect the DB with
`C:\xampp\mysql\bin\mysql.exe -u root`.

Redis is **not** the first task. Introduce it only when a milestone (queues/rate limiting) needs it;
run it via Docker Desktop when that time comes.

---

## 13. Milestone roadmap (build in this order, one at a time)

> Each milestone ends with: what changed, commands to run, expected output, how to test, then STOP.

- **M0 — Environment & plan (no code).** Inspect env, verify versions, confirm this file, present
  architecture/DB/comms/milestone plan for approval.
- **M1 — Laravel bootstrap.** Pre-step: `git init` at the repo root + root `.gitignore`, create
  an empty database. Then create the Laravel app in `backend/` (no starter kit, MySQL driver,
  PHPUnit), connect to MySQL, run the default migrations, confirm `php artisan serve` + default
  page works. No npm install and no Bootstrap yet.
- **M2 — Auth.** Register, login, logout, hashing, session auth, basic validation.
- **M3 — Dashboard.** Bootstrap dashboard: logged-in user, instance count, connection status,
  basic navigation.
- **M4 — Worker skeleton.** Node/TS + Fastify worker that boots, health check, internal-secret
  auth, no WhatsApp yet.
- **M5 — Instance + QR.** Create instance → worker starts a Baileys session → QR shown in UI →
  scan → **Connected** status → disconnect/reconnect.
- **M6 — API credentials.** Generate `instance_id` + `access_token` (shown once, stored hashed,
  revocable) after connection.
- **M7 — Send text message.** `POST /api/v1/messages/send` with token auth + ownership check →
  worker sends **synchronously** (no queue yet) → store message record. Queues + Redis come
  after the MVP works.
- **M8 — Incoming messages + webhooks.** Receive a message, store it, deliver via webhook/event.

Do not skip ahead. Do not merge milestones without being asked.

---

## 14. Handy commands (fill in / confirm as the project grows)

```bash
# Laravel (from backend/)
php artisan serve
php artisan migrate
php artisan test

# Front-end tooling (from backend/)
npm run dev

# Worker (from whatsapp-worker/)
npm run dev
npm test        # vitest
```

---

## 15. Never do (quick reference)

- ❌ Use Meta WhatsApp Business Cloud API.
- ❌ Build anything that bypasses WhatsApp/platform enforcement.
- ❌ Let Laravel talk to WhatsApp directly.
- ❌ Use PostgreSQL or Tailwind.
- ❌ Store raw tokens/passwords; log raw tokens/passwords.
- ❌ Commit `.env`, secrets, or WhatsApp session credentials.
- ❌ Hand-create app tables in phpMyAdmin instead of migrations.
- ❌ Install packages silently, refactor unrelated code, or auto-advance milestones.
- ❌ Build tables/abstractions the current milestone doesn't need.
