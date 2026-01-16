# reCAPTCHA + OTP Setup (TMM)

This project supports:

- **reCAPTCHA v2 (checkbox)** for registration forms
- **Email OTP** verification for account activation

Both are optional by configuration: if you don’t set keys, the UI hides reCAPTCHA and the backend skips verification.

---

## 1) Configure reCAPTCHA

### Option A: Use Google’s test keys (recommended for localhost/demo)
These keys always pass and are meant for development.

- Site key: `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`
- Secret key: `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`

### Option B: Create your own keys (recommended for deployment)
1. Go to Google reCAPTCHA admin console
2. Choose **reCAPTCHA v2 → “I’m not a robot” Checkbox**
3. Add your domain (for local dev you can add `localhost`)
4. Copy the site key + secret key

### Where to put the keys
You can configure keys in either of these places:

**(1) `.env` file (recommended)**
Create/update `c:\xampp\htdocs\tmm\.env`:

```
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here
```

**(2) Database app_settings table**
- `recaptcha_site_key`
- `recaptcha_secret_key`

Note: `.env` overrides database values.

---

## 2) Configure OTP Email Sending (SMTP)

OTP emails use PHPMailer. By default:
- If `TMM_SMTP_HOST` is set → sends via SMTP
- If not set → uses PHP `mail()` (often not configured on XAMPP)

### Recommended: set SMTP in `.env`
Edit `c:\xampp\htdocs\tmm\.env`:

```
TMM_SMTP_HOST=smtp.gmail.com
TMM_SMTP_PORT=587
TMM_SMTP_SECURE=tls
TMM_SMTP_USER=your_email@gmail.com
TMM_SMTP_PASS=your_app_password

SYSTEM_EMAIL=your_email@gmail.com
SYSTEM_NAME=TMM
```

If you use Gmail, you must use an **App Password** (not your normal password).

---

## 3) What “OTP not working” usually means

Most common causes:
- SMTP is not configured (XAMPP `mail()` doesn’t send by default)
- SMTP credentials/port/security mismatch
- Google blocked login (use App Password)
- Email is going to Spam/Promotions

---

## 4) Operator Registration OTP (How it works)

Operator registration:
1. Creates operator account as **Inactive**
2. Sends OTP email (`purpose = operator_register`)
3. OTP verification switches account to **Active**

After that, you can log in as operator normally.

