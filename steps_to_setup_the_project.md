Here's the full setup for a fresh Windows/XAMPP machine (matches `CLAUDE.md`'s locked versions).

## Prerequisites to install first

| Tool | Version | Why |
|---|---|---|
| **XAMPP** | includes PHP 8.4.x + MariaDB | Backend runtime + DB |
| **Composer** | latest | PHP dependency manager |
| **Node.js** | v20+ (project uses v24.11.0) | Both `backend/` (Vite) and `whatsapp-worker/` need it |
| **Git** | any recent | Clone the repo |

Verify PHP has `pdo_mysql`, `curl`, `fileinfo`, `mbstring`, `openssl` enabled (`php -m`) — all standard in XAMPP's default `php.ini`.

## 1. Get the code

```powershell
cd C:\xampp\htdocs\projects
git clone <your-repo-url> whatsapp-gateway
cd whatsapp-gateway
```

## 2. Backend (Laravel)

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Then edit `backend\.env`:
- `DB_DATABASE=whatsapp_gateway` (matches the DB you'll create next)
- `WORKER_BASE_URL=http://127.0.0.1:3001` (already the default)
- `INTERNAL_API_SECRET=` — generate one, e.g. `php -r "echo bin2hex(random_bytes(32));"` — **must match the worker's `.env` exactly**
- `CASHFREE_API_KEY` / `CASHFREE_API_SECRET` / `CASHFREE_ENV=sandbox` — only needed if you want billing to work; leave blank otherwise, nothing else breaks

Create the database (start MySQL in the XAMPP control panel first):
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE whatsapp_gateway;"
```

Migrate and build front-end assets:
```powershell
php artisan migrate
npm install
npm run build
```

Run it:
```powershell
php artisan serve
```
→ `http://127.0.0.1:8000`

**Also needed for full functionality (separate terminals):**
```powershell
php artisan queue:work
```
Without this, incoming-message webhook deliveries just sit in the `jobs` table and never actually get delivered.

## 3. Worker (Node/Baileys)

```powershell
cd ..\whatsapp-worker
npm install
copy .env.example .env
```

Edit `whatsapp-worker\.env`:
- `INTERNAL_API_SECRET=` — **the exact same string you put in `backend\.env`**
- `LARAVEL_CALLBACK_URL=http://127.0.0.1:8000/internal/worker/events` (default is fine)
- `LARAVEL_BASE_URL=http://127.0.0.1:8000` (default is fine)
- `SESSION_STORAGE_PATH=C:\whatsapp-secrets` — **must be outside the repo and outside `C:\xampp\htdocs`**, since Apache serves `htdocs`

Create that folder (it's not in git, on purpose):
```powershell
New-Item -ItemType Directory -Force C:\whatsapp-secrets
```

Baileys is pinned exact (`7.0.0-rc14`) already in `package.json`, so plain `npm install` picks up the right version — no manual edit needed.

Run it:
```powershell
npm run dev
```

## 4. Bring it all up

You need **3 terminals running simultaneously**:
```powershell
# Terminal 1 — backend/
php artisan serve

# Terminal 2 — backend/
php artisan queue:work

# Terminal 3 — whatsapp-worker/
npm run dev

#Terminal 4 - ngrok

ngrok start whatsapp --config "$env:USERPROFILE\.ngrok-prachi\ngrok.yml"
```

Visit `http://127.0.0.1:8000`, register a new account, create an instance, scan the QR.

## Known gotcha on some XAMPP installs

If any outbound HTTPS call from PHP (e.g. billing/Cashfree) fails with `SSL certificate problem: self-signed certificate in certificate chain`, it means `php.ini`'s `curl.cainfo`/`openssl.cafile` aren't set. Fix: download the Mozilla CA bundle to `C:\xampp\php\extras\ssl\cacert.pem` and point both ini settings at it, then restart `php artisan serve`. Hit this once already on the current machine — ask me if it comes up again.

## Not needed

- **Redis** — not used; queue/cache/session all run on the `database` driver.
- **Docker** — only relevant if you later add Redis.

To set admin and go to admin dashboard: 

Register with normal register page
then from tinker set that email to admin

php artisan tinker
>>> $u = App\Models\User::where('email', 'admin@admin.com')->first();
>>> $u->is_admin = true;
>>> $u->save();
