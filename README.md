```
   ___           __ _     _   _          _       _         _  _       _   _  __ _
  / __|_ _ __ _ / _| |_  | | | |_ __  __| |__ _ | |_ ___  | \| |___  | |_(_)/ _(_) ___ _ _
 | (__| '_/ _` |  _|  _| | |_| | '_ \/ _` / _` ||  _/ -_) | .` / _ \ |  _| |  _| |/ -_) '_|
  \___|_| \__,_|_|  \__|  \___/| .__/\__,_\__,_| \__\___| |_|\_\___/  \__|_|_| |_|\___|_|
                                |_|
```

> 🚨 Don't find out about critical updates by accident. Get emailed.

A Craft CMS 5 plugin that checks for **critical** updates (CMS + plugins) and emails configured recipients. Designed to run on a cron schedule so you don't have to log into the CP to find out.

---

## 📦 Installation

Add the VCS repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Studio-Publica/craft-update-notifier"
        }
    ]
}
```

Then require and install:

```bash
composer require publica/craft-update-notifier:^1.0
php craft plugin/install update-notifier
```

## ⚙️ Configuration

Set the `CRITICAL_UPDATE_NOTIFY_EMAILS` environment variable with a comma-separated list of recipients:

```
CRITICAL_UPDATE_NOTIFY_EMAILS=dev@example.com,ops@example.com
```

If empty or missing, the command exits silently (no API call, no error).

## 🚀 Usage

```bash
# Check for critical updates and notify if found
php craft update-notifier/check

# Bypass dedup cache and always send if critical updates exist
php craft update-notifier/check --force
```

## ⏰ Cron Setup

Run the check daily. Example crontab entry for 8am NZST (20:00 UTC previous day):

```crontab
0 20 * * * php /path/to/craft update-notifier/check
```

## 🔍 How It Works

1. 📬 Reads recipients from `CRITICAL_UPDATE_NOTIFY_EMAILS` — if empty, exits early
2. 🌐 Calls `Craft::$app->getUpdates()->getUpdates(true)` to force a fresh API check
3. ✅ If no critical updates — exits early
4. 📋 Collects critical packages (CMS + plugins) with version and release notes
5. 🔑 Builds a SHA1 fingerprint of the critical package set
6. 💾 Checks Craft cache — if fingerprint matches a recent notification, skips (dedup)
7. 📧 Sends HTML + plain text email via Craft's mailer
8. ⏳ Caches fingerprint with a 5-day TTL (nags again if unresolved after 5 days)

### 🔁 Dedup Behaviour

| Scenario | Behaviour |
|---|---|
| ✅ No critical updates | No email. Cache untouched. |
| 🆕 New critical update(s) | Email sent. Fingerprint cached 5 days. |
| 💤 Same updates within 5 days | Cache hit, skip. |
| ➕ Additional package goes critical | Fingerprint changes, new email. |
| 💪 `--force` flag | Bypasses cache, always sends. |

## 📧 Email

**Subject format:**

```
[Site Name / env] Craft CMS - 2 critical plugin updates available (azure-blob, formie)
[Site Name / env] Craft CMS - Critical CMS update available (5.10.0)
[Site Name / env] Craft CMS - Critical CMS update available (5.10.0) + 1 critical plugin update available (formie)
```

**Body** includes a table of critical packages with version and release notes (HTML markup stripped).

## 📋 Requirements

- Craft CMS ^5.0
- A configured mailer (SMTP, Mailtrap, etc.)

---

Built with ☕ by [Studio Publica](https://publica.co.nz)
