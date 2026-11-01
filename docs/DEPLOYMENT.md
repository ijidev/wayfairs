# Deploying Wayfairs

Two supported layouts. Choose the one your host allows.

| Layout | Document root | URL | Notes |
| --- | --- | --- | --- |
| **A. Recommended** | `.../wayfairs/public` | `https://example.com/` | Nothing outside `public/` is reachable. No rewriting needed beyond `public/.htaccess`. |
| **B. Shared hosting** | `.../wayfairs` (usually `public_html/`) | `https://example.com/` | Shipped root `.htaccess` dispatches every request into `public/` and denies application internals. |

Both end with clean URLs at the domain root. A request for `/public/...` is
redirected to the equivalent clean URL, and `/index.php` is redirected to `/`.

## Why `/public` appeared in the URL

If the domain points at the project root instead of `public/`, the only
reachable entry point is `/public/index.php`, so every page needs a `/public`
prefix. The root of the domain also falls back to a raw directory listing and
exposes `app/`, `database/`, `storage/`, and `tests/` over HTTP. That is a data
exposure problem, not just a cosmetic one: `storage/config.php` holds database
credentials and the encryption key, `storage/*.sqlite` holds customer data, and
`tests/run.php` is executable.

Layout B fixes the URLs and blocks those paths. Layout A removes them from the
web root entirely.

## Layout A — point the document root at `public/`

1. Upload the project **outside** the public directory, for example
   `/home/USER/wayfairs`.
2. In cPanel, open **Domains** and edit the document root of the domain or
   subdomain to `/home/USER/wayfairs/public`.
   - If cPanel does not let you edit the primary domain's root, create the site
     as an addon domain or subdomain and set its root to that `public` directory.
3. Open `https://example.com/install`.

When the document root is `public/`, the repository's root `.htaccess`,
`index.php`, and the per-directory `.htaccess` files sit outside the web root
and are inert. `base_path` must stay empty.

## Layout B — document root is the project root (`public_html/`)

Upload the project so that `public_html/` contains `app/`, `public/`,
`storage/`, and the rest. Then confirm these eleven files are present; the
repository ships all of them:

```
.htaccess                  root front controller and deny rules
index.php                  forwards to public/index.php
public/.htaccess           static files, routes, and /public canonicalisation
app/.htaccess
config/.htaccess
database/.htaccess
docs/.htaccess
scripts/.htaccess
storage/.htaccess
tests/.htaccess
```

In cPanel File Manager enable **Settings → Show Hidden Files (dotfiles)** or the
`.htaccess` files will not appear and will not be uploaded.

`public_html/.htaccess` may already exist on some hosts (for example a previous
WordPress install). Replace or back it up deliberately; do not leave both.

### Requirements

- `mod_rewrite` enabled and `.htaccess` overrides permitted
  (`AllowOverride All`, or at least `FileInfo Options AuthConfig`).
- PHP 8.2+ with PDO, Sodium, OpenSSL, cURL, mbstring, intl, and fileinfo.

If the host ignores `.htaccess`, every route except `/` returns 404 and the
application directories stay exposed. Do not use Layout B in that case.

## After either layout

1. Create an empty MySQL or MariaDB database and import the single-file schema:
   `mysql -u USER -p DBNAME < database/mysql/wayfairs-install.sql`.
   The script declares `utf8mb4` and `utf8mb4_unicode_ci` on every table, so the
   database's own default collation does not matter.
2. Open `https://example.com/install`, choose MySQL, and leave **Base path**
   empty. The installer writes `storage/config.php` with mode 0600 where the
   filesystem allows it.
3. Optionally set `WAYFAIRS_COINGECKO_API_KEY` in the PHP environment for
   production-grade crypto rates.

### If `base_path` was set to `public`

Pages will still be generated with a `/public` prefix. Edit
`storage/config.php` and set:

```php
'base_path' => '',
```

then sign in again, because the session cookie path changes.

## Verify the deployment

Replace the host with your domain. `403` is the expected answer for every
application path.

```
curl -sI  https://example.com/ | head -1                  # 200 or 303
curl -sI  https://example.com/install | head -1           # 200 before setup
curl -sI  https://example.com/assets/app.css | head -1    # 200
curl -sI  https://example.com/public/install | head -1    # 301 -> /install
curl -sI  https://example.com/index.php | head -1         # 301 -> /
curl -sI  https://example.com/storage/config.php | head -1 # 403
curl -sI  https://example.com/storage/wayfairs.sqlite | head -1 # 403
curl -sI  https://example.com/tests/run.php | head -1     # 403
curl -sI  https://example.com/app/bootstrap.php | head -1 # 403
curl -sI  https://example.com/database/mysql/wayfairs-install.sql | head -1 # 403
```

Directory listings must never appear for `/`, `/storage/`, or `/database/`.

## If the site was reachable before the fix

Layout B shipped without a root `.htaccess` in earlier commits, so on such a
host the following were downloadable until the files above were added:

- `storage/config.php` — database credentials and the Sodium application key.
- `storage/wayfairs.sqlite` — the whole local database (Layout A/B previews).
- `tests/run.php` — an HTTP-triggered installer test runner.

If the site was publicly reachable in that state:

1. Rotate the MySQL user's password and update `storage/config.php`.
2. Rotate the administrator password and review **Admin → Audit** for unexpected
   entries.
3. If a SQLite file or the configuration was exposed, treat the installation as
   compromised and reinstall from a clean database and a fresh `app_key`.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `500 Internal Server Error` on every page | `Options` or `AllowOverride` is too restrictive for the shipped `.htaccess`; ask the host to allow `FileInfo Options AuthConfig`, or switch to Layout A. |
| `/` lists directories | No root `.htaccess`; it was not uploaded (dotfiles hidden in File Manager). |
| Every route 404s but `/` works | `mod_rewrite` is disabled. |
| URLs still contain `/public` | `base_path` is `public` in `storage/config.php`, or an old bookmark; the 301 fixes the bookmark. |
| Login succeeds then immediately expires | Cookies are set for the wrong path; `base_path` does not match the actual URL prefix. |
| `ERROR 1273 Unknown collation: 'utf8mb4_0900_ai_ci'` | An older cPanel/MariaDB server. The shipped schema already uses `utf8mb4_unicode_ci`; re-upload `database/mysql/wayfairs-install.sql` and `app/Core/Migrator.php`, or replace `utf8mb4_0900_ai_ci` with `utf8mb4_unicode_ci` in both. Drop and recreate the empty database, then re-import. |
| `ERROR 1005 ... Foreign key constraint is incorrectly formed` | Pre-fix schema, where `site_settings.updated_by` was `utf8mb4` while `users.id` is `ascii`. Re-upload the current migration files and re-import into a fresh database. |
| Import stops part-way through a migration | `DELIMITER` is a client directive. Use the `mysql`/`mariadb` command line or let `/install` run the migrations; a panel importer may not understand `DELIMITER`, and the web installer does. |
