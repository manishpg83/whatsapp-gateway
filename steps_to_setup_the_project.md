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
php artisan queue:listen --tries=1
```
Without this, incoming-message webhook deliveries just sit in the `jobs` table and never actually get delivered, and bulk campaigns never send.

**While developing, use `queue:listen` (above), not `queue:work`.** `queue:listen` reloads the code for every job, so code and setting changes apply straight away. `queue:work` loads the app once and keeps running old code until you restart it (`Ctrl+C`, then run it again) — use `queue:work` only on a real server, where it is faster.

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
php artisan queue:listen --tries=1

# Terminal 3 — whatsapp-worker/
npm run dev

#Terminal 4 - ngrok

ngrok start whatsapp --config "$env:USERPROFILE\.ngrok-prachi\ngrok.yml"
```
https://salute-rupture-lark.ngrok-free.dev/

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


**The Pause scenerio in Chatbot**

Scenario 1: Dropdown = "Don't pause", customer sends "hi"
10:00 Rahul: hi
10:00 Bot: Hello! Welcome to Sharma Store…
The bot replies right away, same as always.

Scenario 2: Dropdown = "Don't pause", customer sends "hi", you also reply by hand
10:00 Rahul: hi
10:00 Bot: Hello! Welcome…
10:01 You (from your phone): Hi Rahul, your order is ready
10:02 Rahul: hi, ok thanks
10:02 Bot: Hello! Welcome… ← the bot still replies
With "Don't pause", the bot ignores your manual replies and keeps answering every matching message. That can look odd while you're chatting with someone.

Scenario 3: Dropdown = 30 minutes, customer sends "hi", nobody replies by hand
10:00 Rahul: hi
10:00 Bot: Hello! Welcome…
10:20 Rahul: hi
10:20 Bot: Hello! Welcome…
No pause happens. The 30-minute timer only starts when you send a message yourself. If you never reply, the bot behaves exactly as it does with "Don't pause". The customer is never left without an answer.

Scenario 4: Dropdown = 30 minutes, you reply by hand
10:00 Rahul: hi
10:00 Bot: Hello! Welcome…
10:05 You (from your phone): Hi Rahul, your order is ready ← pause starts, until 10:35
10:10 Rahul: hi, when can I collect?
10:10 Bot: (silent; you're handling this chat)
10:20 You: Anytime after 5pm ← pause moves forward, now until 10:50
10:50 The pause ends by itself
11:00 Rahul: hi
11:00 Bot: Hello! Welcome… ← the bot is back
Each manual reply restarts the 30 minutes from that moment.

Meanwhile, if Priya sends hi at 10:10, the bot answers her normally, because only Rahul's chat is paused.

Scenario 5: You start the chat yourself by sending "hi"
10:00 You (from your phone, to Rahul): hi ← pause starts, until 10:30
10:05 Rahul: hi
10:05 Bot: (silent)
It doesn't matter who wrote first or what you typed. Any message you send by hand pauses that chat.


**In Live server**

To run npm from backend directory and if permission issue comes
cd /var/www/html/projects/whatsapp-gateway
sudo su
Password: root
npm ci
npm run dev

**To display the secret key file, run**
cat ~/.ssh/github_actions_deploy_automatically