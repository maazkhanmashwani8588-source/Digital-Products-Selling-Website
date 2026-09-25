# Email System Documentation

## Overview

Digital Hub includes a comprehensive email system for transactional emails:
- **Order Confirmation** — sent after successful Stripe payment with invoice and download links
- **Password Reset** — sent when users request password reset

## Configuration

### Environment Variables

Set these in your `.env` file:

```
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=noreply@digitalhub.local
MAIL_FROM_NAME=Digital Hub
```

### SMTP Providers

#### Gmail
- Host: `smtp.gmail.com`
- Port: `587` (TLS) or `465` (SSL)
- Username: Your Gmail address
- Password: Generate an [App Password](https://myaccount.google.com/apppasswords) (requires 2FA enabled)

#### SendGrid
- Host: `smtp.sendgrid.net`
- Port: `587`
- Username: `apikey`
- Password: Your SendGrid API key

#### Other Providers
- Mailgun, AWS SES, etc. — check their documentation for SMTP settings

## Installation

```bash
composer require phpmailer/phpmailer
```

## Architecture

### Email Service Class

**Location:** `backend/services/Email.php`

```php
$email = new Email();
$email->sendOrderConfirmation($to, $name, $orderId, $total, $downloadLinks, $invoicePdf);
$email->sendPasswordResetLink($to, $name, $resetLink);
```

Features:
- SMTP authentication
- HTML email templates
- Attachment support (invoice PDFs)
- Error logging to `backend/logs/email.log`
- Graceful fallback if email not configured

### Email Templates

**Location:** `backend/templates/`

- `order_confirmation.html` — responsive order confirmation with download buttons
- `password_reset.html` — password reset link email

Templates are rendered with data via PHP `extract()` and `ob_get_clean()`.

## Integration Points

### 1. Stripe Webhook (`backend/endpoints/stripe_webhook.php`)

After successful payment:
1. Generates download links (HMAC-signed, valid for 30 days)
2. Creates invoice PDF
3. Sends order confirmation email with:
   - Download links
   - Invoice PDF attachment
   - Customer name and order ID

**Email is sent regardless of success/failure — errors logged to `backend/logs/email.log`**

### 2. Forgot Password (`backend/endpoints/forgot-password.php`)

When user requests password reset:
1. Generates reset token (valid 1 hour)
2. Attempts to send email with reset link
3. Falls back to returning link in JSON response if email not configured (demo mode)

## Download Links

Download links are **signed with HMAC-SHA256** using `APP_SECRET`:

```
Format: /backend/endpoints/download.php?file=...&order=...&expires=...&sig=...
Validity: 30 days from email send time
Verification: HMAC signature + order status check
```

### How Links Work

1. User receives email with download link
2. User clicks link → `download.php` verifies signature and order status
3. If valid: file is served; download record created in DB
4. Signature expires after 30 days (prevents unauthorized access)

## Logging

All email operations are logged to `backend/logs/email.log`:

```
2026-06-18T12:34:56+00:00 [sendOrderConfirmation] Successfully sent to customer@example.com
2026-06-18T12:34:57+00:00 [sendPasswordResetLink] SMTP error: Connection refused
```

## Error Handling

### If Email Not Configured

- Webhook: order is still marked paid; email error logged; no exception thrown
- Forgot Password: reset link returned in JSON response (demo mode)

### If Email Fails

- Error logged with full exception message
- User is not blocked — orders are still created/completed
- Admin can manually send emails or resend via dashboard (future feature)

## Security

- SMTP passwords stored in environment variables (not in code)
- Email addresses validated with `filter_var(FILTER_VALIDATE_EMAIL)`
- Download links signed with HMAC-SHA256
- User IDs and order IDs included in metadata (prevents signature spoofing)
- Prepared statements used for all DB queries

## Testing

### Gmail Test

1. Enable 2FA on Gmail account
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Set in `.env`:
   ```
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   ```
4. Create a test order → check inbox

### Local Test (Mailhog)

For development, use [Mailhog](https://github.com/mailhog/MailHog) to capture emails:

```bash
# Install and run Mailhog
./MailHog

# Set in .env
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
```

Then view emails at http://localhost:8025

## Future Enhancements

- Email templates in database (admin customizable)
- Webhook for failed emails (retry mechanism)
- Email history/audit log
- Manual email resend from admin dashboard
- Email queue (async sending via background jobs)
- SMS notifications (Twilio integration)
- Promotional email campaigns
