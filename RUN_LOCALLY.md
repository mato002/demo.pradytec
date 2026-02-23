# Running This Project Locally

This is a PHP + MySQL web app (MFI/loan management with Bulk SMS). Follow these steps to run it on your machine.

---

## 1. Requirements

- **PHP** 7.4 or higher (with cURL, PDO MySQL, mysqli, JSON)
- **MySQL** (MariaDB is fine)
- **Web server**: Apache (with `mod_rewrite`) **or** a stack that includes it (XAMPP, WAMP, Laragon, etc.)

The project is built for **Apache**. Using PHP’s built-in server is possible but needs path/rewrite adjustments.

---

## 2. Put the project under your web root

Example (XAMPP on Windows):

- Copy or clone the project so it sits in a folder under `htdocs`, e.g.:
  - `C:\xampp\htdocs\demo.pradtec\`
  - or `C:\xampp\htdocs\mfs\`

The app expects to be reached at:

- **If folder is `mfs`:**  
  `http://localhost/mfs/`  
  No path changes needed in code.

- **If folder is something else (e.g. `demo.pradtec`):**  
  Use `http://localhost/demo.pradtec/` and update the “localhost” paths as in step 4 below.

---

## 3. Create MySQL databases

The app uses **three** databases. For localhost, the code in `core/constants.php` expects these names by default:

| Database 1 (core) | Database 2 (defined) | Database 3 (accounts) |
|-------------------|----------------------|------------------------|
| `mfi_core`        | `mfi_defined`        | `mfi_accounts`         |

Create them in MySQL (e.g. with phpMyAdmin or command line):

```sql
CREATE DATABASE mfi_core;
CREATE DATABASE mfi_defined;
CREATE DATABASE mfi_accounts;
```

Default local credentials in the code: **user `root`**, **password empty** (see `core/constants.php`).

---

## 4. Configure for localhost

Edit **`core/constants.php`**:

- **Database credentials** (already set for a typical local setup):
  - For localhost it uses: `mfi_core`, `mfi_defined`, `mfi_accounts` and `root` with empty password.
  - If your MySQL user/password differ, change the `$usernames` and `$passwords` arrays for the localhost branch (see the top of the file).

- **URL and path when not using folder `mfs`**  
  If your project URL is e.g. `http://localhost/demo.pradtec`:

  - In `core/constants.php`, find the line that sets `$url` and `$path` using `$_SERVER['HTTP_HOST']=="localhost"`.  
    Set:
    - `$url` so it matches your URL path, e.g. `"localhost/demo.pradtec"`.
    - `$path` to your base path, e.g. `"/demo.pradtec"`.

  - In **`mfs/index.php`**, search for `path()` and the `go` variable:
    - In the JavaScript `path()` function, replace `"/mfs/mfs/"` with `"/demo.pradtec/mfs/"` (and for non-localhost, `"/mfs/"` with whatever your production base is, or leave as `/mfs/`).
    - In the line that sets `go` for session expiry, replace `"/mfs"` with `"/demo.pradtec"` so logout/redirect goes to your login page.

---

## 5. Database tables and initial data

The app expects the **core** database to have tables such as:

- `config` (company/SMS settings: apikey, appname, senderid, company, address, logo, etc.)
- `clients`
- `settings`
- `default_tables`
- `useroles`
- and others used by `checktables()` and the rest of the app.

There is no single “install.sql” in the repo. You either:

- **Use an existing dump** from a working installation (same codebase), and import it into `mfi_core` (and the other two DBs if the dump includes them), **or**
- **Run the app once**: the code creates some tables and defaults when `settings.createtables` is not set (see `checktables()` in `core/functions.php`). You will still need at least:
  - A row in `config` for your `CLIENT_ID` with a JSON `settings` column containing keys like `apikey`, `appname`, `senderid`, `company`, `address`, `logo`, etc.
  - A row in `clients` for the same client.
  - Corresponding data so that login and `mficlient()` work.

**CLIENT_ID** is set in `core/constants.php` (e.g. `4`). Your `config` and `clients` must use this same ID.

---

## 6. Run the app

1. Start **Apache** and **MySQL** (e.g. from XAMPP Control Panel).
2. Open a browser and go to:
   - **If folder is `mfs`:**  
     **http://localhost/mfs/**
   - **If folder is `demo.pradtec`:**  
     **http://localhost/demo.pradtec/**
3. You should see the **login** page. Log in with a user that exists in `org{CLIENT_ID}_staff` (e.g. the default admin created by `checktables()` if that has run).
4. After login you are redirected to the main app (e.g. **http://localhost/mfs/mfs/** or **http://localhost/demo.pradtec/mfs/**).

---

## 7. Optional: PHP built-in server

You can try:

```bash
cd C:\path\to\demo.pradtec
php -S localhost:8000
```

Then open **http://localhost:8000/**.

- Routing is done via `.htaccess` on Apache; the built-in server does not use it. So some URLs may 404 unless you add a router or serve from a subfolder.
- The JavaScript `path()` and redirects assume a base path (e.g. `/mfs/` or `/demo.pradtec/`). For `php -S` you may need to set the base to `""` or `"/"` and adjust links/AJAX so they hit the right paths.

For daily use, **Apache (or XAMPP/WAMP/Laragon) is recommended**.

---

## 8. Troubleshooting

| Issue | What to check |
|-------|----------------|
| Blank page / 500 | PHP error log; `core/constants.php` (DB names, credentials); that all three DBs exist. |
| “No database” / connection errors | `DB_HOST`, database names, and user/password in `core/constants.php` for the localhost branch. |
| Login fails or redirects wrong | That `config` and `clients` exist for `CLIENT_ID`; that staff user exists in `org{CLIENT_ID}_staff`; that `path()` and `go` in `mfs/index.php` match your folder (e.g. `/demo.pradtec/mfs/` and `/demo.pradtec`). |
| CSS/JS 404 | `$path` in PHP and `path()` in JS must match your folder (e.g. `/demo.pradtec` and `/demo.pradtec/mfs/`). |
| SMS not sending | `config.settings` in DB must have valid `apikey`, `appname`, `senderid` for the bulk SMS gateway. |

---

## Summary

1. Install PHP 7.4+, MySQL, and Apache (or XAMPP/WAMP/Laragon).
2. Put the project in a folder under the web root (e.g. `htdocs/demo.pradtec` or `htdocs/mfs`).
3. Create the three databases and set credentials in `core/constants.php`.
4. If the folder is not `mfs`, set the localhost URL/path in `core/constants.php` and in `mfs/index.php` (`path()` and `go`).
5. Ensure core DB has required tables and at least one client + config row for `CLIENT_ID`.
6. Open **http://localhost/your-folder/** and log in.
