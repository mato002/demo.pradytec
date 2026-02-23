# What to Pick from This Project for Bulk SMS on a New Website

Use this as a checklist when setting up bulk SMS on your new site.

---

## 1. Configuration (constants)

**From:** `core/constants.php`

Define these in your new site (config or constants file):

```php
define("SMS_SURL", "http://bulk.pradytec.com/api/sms/send");
define("SMS_PURL", "http://bulk.pradytec.com/api/sms/payment");
define("SMS_BURL", "http://bulk.pradytec.com/api/sms/balance");
// Logs API uses same base as balance but path: .../api/sms/logs (see smslogs in functions)
```

---

## 2. Credentials (API config)

SMS is sent with **apikey**, **appname**, and **senderId** from your bulk SMS provider (Pradytec bulk).

**In this project:** Stored in DB table `config` (database 1), row for your client, column `settings` = JSON with keys: `apikey`, `appname`, `senderid` (and optionally `company`, `email`).

**For the new site:** Store the same three somewhere (e.g. config table or env):

- `apikey`
- `appname`
- `senderid`

You need a small helper that returns this array (like `mficlient()` here). Example:

```php
function getSmsConfig(){
    // e.g. from DB: SELECT settings FROM config WHERE client = ?
    // or from env: return ['apikey'=>getenv('SMS_APIKEY'), 'appname'=>..., 'senderid'=>...];
    return ['apikey'=>'YOUR_KEY','appname'=>'YOUR_APP','senderid'=>'YOUR_SENDER'];
}
```

---

## 3. Core SMS functions (copy these)

**From:** `core/functions.php`

Copy these four functions **as-is** (they only need the constants above and your config helper):

| Function       | Lines (approx) | Purpose |
|----------------|----------------|---------|
| `sendSMS($to, $mssg)`   | 2948–2976  | Send SMS. `$to` = one number or comma-separated list. |
| `smslogs($data)`        | 2978–2997  | Fetch logs from provider (optional, for “SMS Logs” page). |
| `smsbalance()`         | 2999–3020  | Fetch wallet balance (optional). |
| `paysms($phone, $amnt)` | 3022–3043  | Top-up wallet (optional). |

**Dependency:** In `sendSMS`, replace `mficlient()` with your own config getter (e.g. `getSmsConfig()`). Same for `smslogs`, `smsbalance`, `paysms` if you use them.

**Minimal for “just send SMS”:** You only need **`sendSMS()`** + constants + config.

---

## 4. Database tables (if you want scheduling & templates)

**Database:** Central/primary DB (this project uses “database 1”).

Create these tables:

```sql
-- Templates (optional; for reusable messages)
CREATE TABLE sms_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  message TEXT NOT NULL
);

-- Schedule queue (for “send later” and cron)
CREATE TABLE sms_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client INT NOT NULL,
  message TEXT NOT NULL,
  contacts TEXT NOT NULL,
  type ENUM('similar','multiple') NOT NULL,
  schedule INT NOT NULL,
  time INT NOT NULL
);

-- For “multiple” type: avoid sending same number twice for same schedule
CREATE TABLE sms_queues (
  phone VARCHAR(32) NOT NULL,
  sid INT NOT NULL,
  PRIMARY KEY (phone, sid)
);
```

- **similar:** one message for all; `contacts` = JSON array of phone numbers.
- **multiple:** different message per number; `contacts` = JSON object `{ "2547..." : "message", ... }`.

If your new site has no “client” concept, you can use `client = 1` or drop the column and simplify.

---

## 5. Scheduled sending (cron)

**From:** `mfs/smschron.php`

**Logic to reuse:**

- Run periodically (e.g. every 5–10 minutes).
- Select: `SELECT * FROM sms_schedule WHERE client = ? AND schedule <= UNIX_TIMESTAMP()`.
- For each row:
  - **type = 'similar':**  
    `sendSMS(implode(",", json_decode($row['contacts'], true)), $row['message']);`  
    then delete the row.
  - **type = 'multiple':**  
    Decode `contacts` as `{ phone => message }`. In a transaction, send in batches (e.g. 60 per run), record sent numbers in `sms_queues`, update `sms_schedule.contacts` (remove sent) or delete row when empty.

**What to pick:** The algorithm from `smschron.php` (lines 56–94), adapted to your DB layer and config. You don’t need to copy the installment-reminder part unless your new site has the same loans/schedule data.

---

## 6. UI / flow (optional)

If you want the same “Bulk SMS” experience:

| From this project      | Use for |
|------------------------|--------|
| `mfs/bulksms.php`      | Main UI: send form, templates, logs, schedule list, getclients, smrecips, topup. |
| `mfs/dbsave/bulksms.php` | Save template, save temp message, Excel upload, delete schedule/template, topup request. |
| `mfs/dbsave/sendsms.php` | Send/schedule to clients or staff; personalization (CLIENT, IDNO, LOAN_BALANCE, etc.). |

Heavy dependency: `bulksms.php` and `sendsms.php` depend on this app’s **clients**, **staff**, **loans**, **branches**, roles, etc. So for a **different** website you typically:

- **Either** copy only the **simple** parts: one “Compose SMS” form that posts to a small script that calls `sendSMS($to, $message)` (and optionally inserts into `sms_schedule` for “send later”).
- **Or** copy the full `bulksms.php` + `dbsave/bulksms.php` + `dbsave/sendsms.php` and **replace** every reference to `org{id}_clients`, `org{id}_staff`, loans, branches, etc., with your new site’s tables (or stub them).

Recommendation: **Implement a minimal “Send SMS” page** that:
1. Takes: recipients (textarea or list), message, optional “send at” (datetime).
2. If “send at” is empty or past: call `sendSMS()`.
3. If “send at” is future: insert one row into `sms_schedule` (type `similar` or `multiple`), then let cron (from point 5) send it.

---

## 7. Summary: minimal “pick list” for new site

| Need                         | Pick from here |
|-----------------------------|----------------|
| **Just send SMS**            | Constants (SMS_SURL, etc.) + config (apikey, appname, senderid) + **sendSMS()** from `core/functions.php`. |
| **Send later / queue**      | Tables `sms_schedule` + `sms_queues` + cron logic from `mfs/smschron.php`. |
| **Templates**               | Table `sms_templates` + your own UI to CRUD; no need for full bulksms UI. |
| **Logs**                    | **smslogs()** + SMS logs API (balance URL with `/logs`). |
| **Balance / top-up**        | **smsbalance()**, **paysms()** + your UI. |
| **Full Bulk SMS UI**        | `mfs/bulksms.php`, `mfs/dbsave/bulksms.php`, `mfs/dbsave/sendsms.php` — then adapt to your DB and auth. |

**Smallest start:** Constants + config + `sendSMS()` + one form that posts to a script calling `sendSMS()`. Add scheduling and cron when you need “send later”.
