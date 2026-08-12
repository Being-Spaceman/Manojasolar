# Manoja Agencies — server

Plain PHP 8 + PDO. No framework, no Composer, no build step — this deploys by
uploading the files as-is to Hostinger shared hosting.

## Layout

```
/server
  /api          lead.php                     — public, the form posts here
  /leads        index.php, downloads.php,    — shared `desk`/`admin` login,
                call-sheet.php, logout.php      view + export only
  /admin        index.php, export-log.php,   — `admin`-only login, full control
                archive.php
  /lib          db.php, auth.php, leads.php, — not web-facing (.htaccess denied),
                export.php, archive.php,       included by the others
                panel.php
  /cron         digest.php,                  — (Stage 5) run by hPanel's cron
                monthly-archive.php             scheduler
  /migrations   001_leads.sql, 002_auth.sql  — run once by hand, not a migration
                                                 runner; .htaccess denied
  /archive      leads-YYYY-MM.xlsx           — written by the monthly archive;
                                                 .htaccess denied, gitignored output
  config.php            — gitignored, real credentials, create from the example
  config.example.php    — committed, dummy values, documents the shape
```

## Deploy paths

The Astro site builds to `dist/` and that becomes the document root
(`public_html/`). This `server/` folder is **not** a subdomain — its contents
go into the *same* `public_html/`, alongside the built site, so paths like
`/api/lead.php` and `/leads/` resolve on the same domain and certificate.

Upload:

| From | To (on Hostinger) |
|---|---|
| `dist/*` | `public_html/` |
| `server/api/` | `public_html/api/` |
| `server/leads/` | `public_html/leads/` |
| `server/admin/` | `public_html/admin/` |
| `server/lib/` | `public_html/lib/` |
| `server/cron/` | `public_html/cron/` |
| `server/config.php` | `public_html/config.php` |

`lib/`, `migrations/`, `archive/` and `config.php` are not meant to be
requested directly by a browser — they hold no page output of their own, or
(worse) hold credentials and lead exports. Shared hosting here has no
configurable document root, so instead of relocating them outside
`public_html/` (fragile — the exact folder layout above `public_html/`
varies by hosting account), each of those folders ships its own deny-all
`.htaccess`, and the site's root `public/.htaccess` denies `config.php`
specifically by filename. `Options -Indexes` (also in `public/.htaccess`)
additionally stops directory listings everywhere else.

## First-time setup in hPanel

1. **Create the database.** hPanel → Databases → MySQL Databases → create a
   new database and a new database user, and attach the user to the database
   with all privileges. Note the host (usually `localhost`), database name,
   username and password — hPanel shows these on the same screen.
2. **Run the schema.** hPanel → Databases → phpMyAdmin → open the new
   database → SQL tab → paste the contents of `migrations/001_leads.sql` →
   Go. This creates the `leads` and `export_log` tables. There is no
   migration runner; if the schema changes later, write and run a new
   `NNN_description.sql` file by hand the same way.
3. **Create `config.php`.** Copy `config.example.php` to `config.php`, fill
   in the DB credentials from step 1, and set:
   - `mail_to` — where the daily digest goes (Stage 5)
   - `desk_password_hash` / `admin_password_hash` — bcrypt hashes, generate
     each with `php -r "echo password_hash('the-password', PASSWORD_BCRYPT), PHP_EOL;"`
     (run that locally if PHP CLI isn't available on the host — the hash
     itself has no server dependency)
   - `ip_hash_salt` — a random 64-hex-character string, generate with
     `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. This is a pepper
     mixed into every stored IP hash; changing it later makes the rate limiter
     forget everyone's recent history, which is harmless.
4. **Upload** per the table above, then submit the live lead form once to
   confirm a row lands in `leads`.

## `api/lead.php`

- POST only (405 otherwise), honeypot (`website` field) returns a fake
  success and inserts nothing.
- Validates `lead_type` (`business` | `individual`) and the fields required
  for that type; mobile must be a 10-digit Indian number starting 6–9; GSTIN
  is always optional and, if present, checked for format only (no checksum).
- Consent is required.
- Stores a salted SHA-256 hash of the submitter's IP — never the raw IP —
  and rate-limits to 3 submissions per IP hash per hour by counting recent
  rows in `leads` itself (no separate rate-limit table).
- Does **not** send a per-lead email — `cron/digest.php` (Stage 5) sends one
  digest a day instead.

**Field names are prefixed per audience** (`biz_firm_name`, `biz_mobile`, …
vs `ind_contact_name`, `ind_mobile`, …), because `LeadForm.astro` renders
both fieldsets at once for progressive enhancement — without JS there is no
script to hide the one the visitor didn't pick, so both sit in the same
`<form>` simultaneously. Distinct names stop the two fieldsets' fields from
colliding in `$_POST`; `lead.php` reads only the pair that matches the
submitted `lead_type` and writes them into the shared, unprefixed `leads`
columns.

**Two response shapes**, chosen by the request itself:
- A JS-enhanced submission `fetch()`es with `Accept: application/json` and
  gets back `{"ok":true}` or `{"ok":false,"errors":{field: message}}` (messages
  in the submitted locale), which the form's script turns into inline field
  errors.
- A no-JS submission is a plain browser form POST with no such header. It
  can't render inline errors — there's no script to do it — so it gets a
  303 redirect to the thank-you page on success (the path comes from a
  hidden `thanks_path` field the form sets, since there's no JS-only header
  to branch on) and a minimal static error list on failure. This is what
  makes "either fieldset submits correctly without JS" true in practice, not
  just in markup.

## `/leads` and `/admin`

Session auth, no user table — just two bcrypt password hashes in
`config.php` (`desk_password_hash`, `admin_password_hash`). Whichever one a
submitted password matches decides the role; the admin password also
satisfies everything the desk role can do, since admin is a superset.

- **Sessions**: `httponly`, `secure`, `samesite=Strict` (`lib/auth.php`,
  `mnj_session_start()`). `secure` means these panels only work over HTTPS —
  fine, since `public/.htaccess` already forces HTTPS site-wide.
- **CSRF**: one token per session (`mnj_csrf_token()`), a hidden field on
  every POST form (`mnj_csrf_field()`), checked before any write.
- **Failed-login rate limit**: 5 per IP hash per 15 minutes, shared across
  `/leads` and `/admin` (`login_attempts` table, `002_auth.sql`). A
  successful login doesn't clear the counter early — it's a small, cheap
  table and simplicity won here.
- **`/leads`** (`leads/index.php`) — desk or admin login. Table of leads,
  newest first, paginated, filterable by date range / type / status. Row
  colour and a status pill both key off the same class
  (`new`=red, `exported`=amber, `contacted`=green). Each row has a `wa.me`
  link and a `tel:` link. **Deliberately has no edit/delete/status-change
  UI** — that's the read-only boundary from the brief.
- **`/leads/downloads`** — CSV and XLSX, both hand-rolled (`lib/export.php`;
  XLSX is real OOXML via `ZipArchive`, not a `.xls`-renamed XML file, so it
  opens correctly in both Excel and Google Sheets without a "repair?"
  prompt). Date range defaults to the current month. Every export writes a
  row to `export_log` and marks the matching **new** leads `exported` —
  before streaming the file, so an aborted download still leaves an
  accurate trail.
- **`/leads/call-sheet`** — print-only A4 view, ~12 leads/page
  (`page-break-after` per chunk), a wide ruled "Notes" column with nothing
  in the cell for handwriting. `@media print` hides the toolbar; there's no
  other chrome to hide.
- **`/admin`** — admin login only; a desk login visiting `/admin/*` gets a
  plain 403, not a redirect back to a login screen it would just fail again.
  Adds inline status change, delete (with a confirm dialog), and an
  edit-in-a-`<details>` form per row — none of which `/leads` exposes.
  Also: `/admin/export-log.php` (every export pull, read-only) and
  `/admin/archive.php` (re-run the monthly archive on demand — see below).
- **Counts on `/admin`**: leads per day for the last 7 days, and a
  B2B/B2C total split. Deliberately simple — a dashboard is not the brief.

## Monthly archive (`lib/archive.php`)

`mnj_run_monthly_archive(?string $yearMonth)` pulls every lead created in a
given calendar month and writes `leads-YYYY-MM.xlsx` into `server/archive/`
(`.htaccess`-denied, not downloadable from a browser — pull it via
(S)FTP/File Manager). MySQL stays the permanent record; this is a
convenience copy, per the brief. Shared by two callers:

- `cron/monthly-archive.php` (Stage 5) — runs automatically on the 1st,
  archives *last* month.
- `/admin/archive.php` — a manual "run it now" button, for backfilling a
  month or checking the output before trusting the cron. Accepts an optional
  `YYYY-MM`; blank defaults to last month, same as the cron.

## Cron

Both scripts are plain PHP with no HTTP concerns — run them with the PHP
binary directly, not by curling a URL (which would otherwise need its own
auth to stop a random request from triggering them). hPanel → Advanced →
Cron Jobs → "Run a PHP script".

| Script | Schedule | Cron expression | What it does |
|---|---|---|---|
| `cron/digest.php` | Once daily, evening | `0 20 * * *` (8:00 PM server time) | Emails a digest of leads from the last 24 hours to `mail_to` in `config.php`. Sends nothing if there were none. |
| `cron/monthly-archive.php` | Once monthly, early on the 1st | `15 0 1 * *` (00:15 on the 1st) | Writes last month's leads to `server/archive/leads-YYYY-MM.xlsx`. Safe to also trigger by hand from `/admin/archive.php` — same underlying function, idempotent (re-running overwrites the same file rather than duplicating rows anywhere). |

If hPanel's cron UI asks for a full command instead of just a path, it's
typically:

```
php /home/<hosting-user>/domains/manojaagencies.in/public_html/cron/digest.php
php /home/<hosting-user>/domains/manojaagencies.in/public_html/cron/monthly-archive.php
```

(exact path depends on the account — hPanel's cron job screen shows the
correct base path for the account when you create the job).

**Note on WhatsApp:** the digest's per-lead `wa.me` links are the ceiling of
what's possible without the paid WhatsApp Business API — automatic outbound
sending isn't available on this budget, and nothing here is built assuming
it will be. One tap opens the chat with the message already typed; that's
the mechanism, not a placeholder for something more automated.
