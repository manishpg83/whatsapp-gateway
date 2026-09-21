I want to build a completely new WhatsApp Gateway SaaS from scratch.

This is a clean new project. Do not assume any existing application code.

## 1. What I am building

I want to build my own WhatsApp gateway similar in concept to WaTrend.

A user should be able to:

1. Register/login to my website.
2. Create a WhatsApp connection/instance.
3. See a QR code.
4. Scan the QR code using their normal WhatsApp mobile app.
5. Have that WhatsApp account connected to my system.
6. See the connection status as Connected.
7. Receive an `instance_id`.
8. Receive an `access_token`.
9. Use my REST API with the `instance_id` and `access_token`.
10. Send WhatsApp messages through my API.
11. Eventually receive incoming WhatsApp messages and expose them through webhooks.

IMPORTANT:

I do NOT want to use the Meta WhatsApp Business Cloud API.

The system should use a WhatsApp Web/device-session approach through a suitable Node.js WhatsApp Web-compatible library.

For the initial implementation, investigate and use Baileys (`@whiskeysockets/baileys`) if it is technically appropriate and compatible with the current Node.js version.

This is an unofficial WhatsApp Web/device-session integration. Do not implement methods to bypass WhatsApp security, CAPTCHA, rate limits, account enforcement, or other platform protections.

---

# 2. Technology stack

## Backend

* Laravel
* PHP
* MySQL
* phpMyAdmin for database administration
* Blade templates
* Bootstrap 5
* Vite
* Laravel queues
* Redis

Do NOT use PostgreSQL.

The local development database must be MySQL because I use XAMPP/phpMyAdmin.

## WhatsApp Worker

* Node.js
* TypeScript
* Baileys
* Fastify or another lightweight HTTP server
* Pino logging
* Zod validation
* Vitest for tests

The worker is responsible for WhatsApp device/session communication.

Laravel should NOT directly communicate with WhatsApp.

---

# 3. High-level architecture

The architecture should be:

Browser
↓
Laravel
↓
MySQL
↓
Redis / Laravel Queue
↓
Node.js WhatsApp Worker
↓
Baileys
↓
WhatsApp Web / WhatsApp device session

The Laravel application handles:

* users
* authentication
* dashboard
* WhatsApp instance records
* API credentials
* message records
* API requests
* queues
* webhooks
* business logic

The Node.js worker handles:

* WhatsApp sessions
* QR generation
* QR updates
* connection state
* authentication state
* sending messages
* receiving messages
* session reconnects
* WhatsApp-specific events

---

# 4. Multi-user requirement

This must be a multi-tenant SaaS.

Multiple users will use the system.

Example:

User A
→ Instance A
→ WhatsApp Account A

User B
→ Instance B
→ WhatsApp Account B

User C
→ Instance C
→ WhatsApp Account C

One user must never be able to access another user's instances, messages, tokens, or session information.

Every database query and API endpoint must enforce ownership.

---

# 5. Initial MVP

Do NOT build everything at once.

The first MVP should only contain:

### Authentication

* Register
* Login
* Logout
* Password hashing
* Session authentication
* Basic validation

### Dashboard

A simple Bootstrap dashboard showing:

* logged-in user
* number of WhatsApp instances
* connection status
* basic navigation

### WhatsApp instance management

User can:

* create an instance
* see QR code
* scan QR code
* see connection status
* disconnect instance
* reconnect instance

### API credentials

After an instance is connected:

* generate `instance_id`
* generate secure `access_token`
* show the credentials to the user
* never store the raw token in the database
* store a secure hash of the token

### Messaging

Initial API should support only:

* send plain text message

Example concept:

POST /api/v1/messages/send

with:

instance_id
access_token
to
message

The API should authenticate the token and verify ownership.

### Incoming messages

After the basic send functionality works, implement:

* receiving a WhatsApp message
* storing it
* exposing it through a webhook/event mechanism

---

# 6. Database

Use MySQL.

I will manage the database using phpMyAdmin.

Initial database entities should be planned around:

users
whatsapp_sessions
api_tokens
messages

Additional tables can be introduced only when genuinely necessary.

Do not create a huge database schema before the requirements need it.

Use Laravel migrations.

Do not manually create production tables through phpMyAdmin if a Laravel migration should manage them.

phpMyAdmin is primarily for viewing/managing the MySQL database during development.

---

# 7. WhatsApp sessions

Each WhatsApp connection must have its own session.

A user may eventually have multiple WhatsApp connections.

Example:

User 1

* Instance A
* Instance B
* Instance C

Each instance must have its own WhatsApp authentication/session data.

For the MVP, session credentials can be stored outside the Git repository in a protected local directory.

Do not commit WhatsApp authentication credentials to Git.

Later we can consider encrypted/database-backed session storage.

---

# 8. Laravel ↔ Node worker

Laravel and the Node worker should communicate through an internal API.

Example conceptual endpoints:

Laravel → Worker:

POST /sessions
DELETE /sessions/{id}
POST /sessions/{id}/messages

Worker → Laravel:

QR update callback
connection status callback
message received callback
message status callback

Use a shared internal secret or another secure authentication mechanism for Laravel ↔ Worker communication.

Do not expose the internal worker API publicly unless required.

---

# 9. API authentication

There are two separate authentication systems.

## Dashboard

Use normal Laravel browser/session authentication.

Example:

login
→ Laravel session
→ dashboard

## Public messaging API

Use:

instance_id + access_token

The access token must:

* be cryptographically random
* be shown only when appropriate
* be stored hashed
* not be stored as plaintext
* be revocable
* be associated with an instance/user

Never log raw access tokens.

---

# 10. Security requirements

Security is important.

Implement:

* password hashing
* CSRF protection for web forms
* validation
* authorization/ownership checks
* secure API token generation
* hashed API tokens
* rate limiting where appropriate
* secrets in `.env`
* no secrets committed to Git
* no WhatsApp session credentials committed to Git
* safe logging
* no raw access tokens in logs
* no raw passwords in logs

Do not implement anything designed to bypass WhatsApp security or platform enforcement.

---

# 11. UI

Use Bootstrap 5.

I want a clean SaaS dashboard.

Do not use Tailwind unless there is a specific reason.

Do not use a huge UI framework.

Initial pages:

* Login
* Register
* Dashboard
* WhatsApp Instances
* Create/Connect Instance
* QR Code screen
* Instance details
* API credentials
* API documentation/basic usage page

Keep the UI simple initially.

We can improve the visual design after the functionality works.

---

# 12. Project structure

A possible structure is:

whatsapp-gateway/

├── CLAUDE.md
├── README.md
├── .gitignore
│
├── backend/
│   ├── app/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── tests/
│   ├── composer.json
│   └── package.json
│
├── whatsapp-worker/
│   ├── src/
│   ├── tests/
│   ├── package.json
│   └── tsconfig.json
│
└── docs/

Do not blindly follow this structure if Laravel's normal structure makes more sense. Explain any structural decision before making a major change.

---

# 13. Development environment

My local environment is Windows/XAMPP.

Current environment includes approximately:

PHP 8.4
Composer
Node.js
npm
Git
MySQL through XAMPP
phpMyAdmin
Docker Desktop

The project should be developed locally first.

Laravel should initially run using:

php artisan serve

Do not configure Apache/nginx production deployment during the MVP.

---

# 14. Redis

Redis will eventually be used for:

* Laravel queues
* caching
* rate limiting
* background jobs

We can run Redis using Docker Desktop.

Do not make Redis the first setup task unless it is actually required for the current milestone.

---

# 15. API example

The eventual API should conceptually look like:

POST /api/v1/messages/send

Authorization:
Bearer ACCESS_TOKEN

Request:

{
"instance_id": "INSTANCE_ID",
"to": "919XXXXXXXXX",
"message": "Hello"
}

Response should contain useful information such as:

{
"success": true,
"message_id": "..."
}

Do not finalize the exact API contract until the database/session architecture has been reviewed.

---

# 16. Important development rules for Claude

I am a beginner.

Do NOT make huge changes without explaining them.

Work in small milestones.

Before coding:

1. Inspect the current project.
2. Explain what will be changed.
3. Give me the exact commands/files involved.
4. Wait for my approval when the change is significant.

After each milestone:

1. Explain what was created.
2. Tell me exactly what commands I should run.
3. Tell me what output I should expect.
4. Tell me how to test it.
5. Stop and wait for my confirmation.

I will often reply with "done".

Do not automatically continue to the next milestone.

Do not silently install large numbers of packages.

Do not rewrite working code unnecessarily.

Do not refactor unrelated code.

Do not create placeholder architecture that is not needed.

Prefer simple, maintainable code.

---

# 17. Claude's first responsibility

Before writing application code:

1. Read this entire brief.
2. Inspect the local environment.
3. Check installed versions of:

   * PHP
   * Composer
   * Laravel installer
   * Node
   * npm
   * Git
   * MySQL
   * Redis
   * Docker
4. Check whether the current directory is empty or already contains files.
5. Verify Laravel's current stable version compatible with my PHP version.
6. Verify the current Baileys/Node compatibility before locking versions.
7. Identify any PHP extensions Laravel requires.
8. Identify any potential compatibility issues.

Do not install anything yet.

Then create/update `CLAUDE.md`.

Then give me:

1. The proposed architecture.
2. The proposed folder structure.
3. The database plan.
4. The Laravel/Node communication plan.
5. The milestone plan.
6. Any important technical decisions that need my approval.
7. The exact next Claude prompt I should use.

STOP after this.

Do not start building the application until I approve the architecture.
