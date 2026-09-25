Digital Hub

Modern, responsive e-commerce platform for selling digital products (eBooks, software, templates, courses).

## Features

**Customer Experience:**
- ✅ Responsive product catalog with search & filters
- ✅ Session-based shopping cart
- ✅ Secure checkout with Stripe payments
- ✅ Order management and download history
- ✅ Secure file downloads with signed URLs
- ✅ Invoice generation (HTML/PDF)
- ✅ Email confirmations with download links
- ✅ User authentication & password reset

**Admin Panel:**
- ✅ Dashboard with real-time sales metrics
- ✅ Product management (CRUD + file uploads)
- ✅ Order management with status updates
- ✅ Customer management with analytics
- ✅ Coupon/discount code management
- ✅ Advanced analytics (charts, trends, top products)
- ✅ Secure admin authentication

**File Storage:**
- ✅ AWS S3 + local storage support
- ✅ File validation (type, size, magic bytes)
- ✅ Malware scanning (ClamAV optional)
- ✅ Signed URLs for secure downloads
- ✅ Complete metadata tracking

**Backend Infrastructure:**
- ✅ PHP 7.4+ with MySQL database
- ✅ Session-based authentication
- ✅ SMTP email integration (PHPMailer)
- ✅ Stripe payment processing with webhooks
- ✅ Comprehensive error logging

## Project Structure

```
frontend/
├── index.html           (Home/product listing)
├── auth/               (Login, register, password reset)
├── product.html        (Single product detail)
├── cart.html          (Shopping cart)
├── checkout.html      (Payment form)
├── download_history.html
├── order_detail.html
├── admin/             (Admin dashboard)
│   ├── index.html     (Main dashboard)
│   └── login.html     (Admin login)

backend/
├── config.php         (App configuration)
├── db.php            (Database connection)
├── auth_middleware.php
├── admin_middleware.php
├── services/
│   ├── Email.php      (SMTP email service)
│   ├── Storage.php    (S3/local file storage)
├── endpoints/
│   ├── auth_*.php     (Authentication)
│   ├── products_*.php (Product management)
│   ├── cart_*.php     (Cart operations)
│   ├── checkout.php   (Order creation)
│   ├── stripe_*.php   (Payment processing)
│   ├── download.php   (Secure downloads)
│   ├── generate_invoice.php
│   ├── admin/         (Admin endpoints)
│   │   ├── dashboard_stats.php
│   │   ├── products_crud.php
│   │   ├── orders_list.php
│   │   ├── customers_list.php
│   │   ├── coupons_crud.php
│   │   └── analytics_sales.php

database/
├── schema.sql         (MySQL schema)
├── seeds/
│   └── products_seed.sql
├── migration_2_add_file_storage.sql

docs/
├── API.md            (Endpoint documentation)
├── EMAIL.md          (Email service guide)
├── STORAGE.md        (File storage guide)
├── ADMIN_DASHBOARD.md (Admin panel docs)
```

## Quick Start

### 1. Setup Database

```bash
mysql -u root < database/schema.sql
mysql -u root digital_hub < database/seeds/products_seed.sql
mysql -u root digital_hub < database/migration_2_add_file_storage.sql
```

### 2. Install Dependencies

```bash
composer require stripe/stripe-php dompdf/dompdf phpmailer/phpmailer aws/aws-sdk-php
```

### 3. Configure Environment

```bash
cp .env.example .env
# Edit .env with your settings:
# - Database credentials
# - Stripe keys
# - Email (SMTP)
# - File storage (local or S3)
```

### 4. Create Admin User

```bash
# Via MySQL:
INSERT INTO users (name, email, password, role) 
VALUES ('Admin', 'admin@example.com', SHA2('password', 256), 'admin');

# Or via PHP script to use password_hash()
```

### 5. Run Server

```bash
php -S localhost:8000 -t .
```

**Access points:**
- 🏪 Customer: http://localhost:8000/frontend/
- 🛒 Cart: http://localhost:8000/frontend/cart.html
- 👤 Login: http://localhost:8000/frontend/auth/login.html
- 📊 Admin: http://localhost:8000/frontend/admin/
- 🔐 Admin Login: http://localhost:8000/frontend/admin/login.html

## Admin Dashboard

Access: `/frontend/admin/login.html`

Features:
- **Dashboard**: Sales metrics, revenue trends, recent orders
- **Products**: Create, edit, delete products with file uploads
- **Orders**: View orders, update status, track payments
- **Customers**: Search customers, view purchase history
- **Coupons**: Create discount codes (% or fixed)
- **Analytics**: Charts, top products, category breakdown

See [ADMIN_SETUP.md](ADMIN_SETUP.md) for admin panel guide.

## Database Setup

### Schema Highlights

**Users** (with admin role):
```sql
SELECT * FROM users WHERE role = 'admin';
```

**Products** (with digital download fields):
```sql
SELECT * FROM products WHERE status = 'active';
```

**Orders** (with payment tracking):
```sql
SELECT * FROM orders WHERE status = 'paid';
```

**Product Files** (secure storage metadata):
```sql
SELECT * FROM product_files;
```

**Coupons** (discount codes):
```sql
SELECT * FROM coupons WHERE expires_at > NOW();
```

## Configuration

### Environment Variables (.env)

```env
# Database
DB_HOST=127.0.0.1
DB_NAME=digital_hub
DB_USER=root
DB_PASS=

# App
BASE_URL=http://localhost:8000
APP_SECRET=change_in_production

# Payments (Stripe)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Email (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password

# File Storage
STORAGE_DRIVER=local            # or 's3'
LOCAL_STORAGE_PATH=storage/products
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=digital-hub-products
```

### Payment Setup (Stripe)

1. Create Stripe account: https://stripe.com
2. Get API keys from dashboard
3. Set `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` in `.env`
4. Configure webhook: `POST /backend/endpoints/stripe_webhook.php`
5. Test with Stripe CLI: `stripe listen --forward-to localhost:8000/backend/endpoints/stripe_webhook.php`

### Email Setup (Gmail)

1. Enable 2FA on Gmail
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Set in `.env`:
   ```env
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   ```

### File Storage Setup

**Development (Local):**
```env
STORAGE_DRIVER=local
LOCAL_STORAGE_PATH=storage/products
```

**Production (AWS S3):**
```env
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=wJalr...
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=digital-hub-products
```

## API Documentation

### Authentication Endpoints
- `POST /backend/endpoints/auth_register.php` — Register new user
- `POST /backend/endpoints/auth_login.php` — User login
- `POST /backend/endpoints/logout.php` — Logout
- `POST /backend/endpoints/forgot-password.php` — Request password reset
- `POST /backend/endpoints/reset-password.php` — Reset password

### Product Endpoints
- `GET /backend/endpoints/products_list.php` — List products
- `GET /backend/endpoints/product_detail.php` — Product detail
- `POST /backend/endpoints/admin/products_crud.php` — Create product (admin)
- `PUT /backend/endpoints/admin/products_crud.php` — Update product (admin)
- `DELETE /backend/endpoints/admin/products_crud.php` — Delete product (admin)

### Order & Payment Endpoints
- `POST /backend/endpoints/checkout.php` — Create order
- `POST /backend/endpoints/stripe_create_checkout.php` — Create Stripe session
- `POST /backend/endpoints/stripe_webhook.php` — Stripe webhook handler
- `GET /backend/endpoints/admin/orders_list.php` — List orders (admin)
- `POST /backend/endpoints/admin/update_order_status.php` — Update order status (admin)

### File Endpoints
- `POST /backend/endpoints/admin_upload_product_file.php` — Upload product file (admin)
- `GET /backend/endpoints/generate_download_link.php` — Generate download link
- `GET /backend/endpoints/download.php` — Download file (secure)
- `POST /backend/endpoints/generate_invoice.php` — Generate invoice

### Admin Endpoints
- `GET /backend/endpoints/admin/dashboard_stats.php` — Dashboard metrics
- `GET /backend/endpoints/admin/customers_list.php` — List customers
- `GET /backend/endpoints/admin/coupons_crud.php` — List coupons
- `POST /backend/endpoints/admin/coupons_crud.php` — Create coupon
- `GET /backend/endpoints/admin/analytics_sales.php` — Analytics data

See [docs/API.md](docs/API.md) for full endpoint reference.

## Documentation

- [Admin Dashboard Guide](ADMIN_SETUP.md)
- [Admin Panel Documentation](docs/ADMIN_DASHBOARD.md)
- [Email Service Guide](docs/EMAIL.md)
- [File Storage Guide](docs/STORAGE.md)
- [File Storage Setup](STORAGE_SETUP.md)
- [API Reference](docs/API.md)

## Security Best Practices

✅ **Implemented:**
- Session-based authentication (PHP sessions)
- Password hashing with `password_hash()`
- Prepared statements (SQL injection prevention)
- HMAC-SHA256 signed download links
- File validation (type, size, magic bytes)
- Stripe webhook signature verification
- Email sent only after payment confirmed
- Admin role required for admin endpoints

⚠️ **Production Recommendations:**
- Enable HTTPS/TLS
- Set secure session cookie flags
- Implement rate limiting
- Add CSRF tokens
- Enable security headers (HSTS, CSP)
- Regular security audits
- Database backups
- WAF (Web Application Firewall)
- Two-factor authentication (2FA) for admins

## Testing

### Manual Testing Checklist

- [ ] Register new user
- [ ] Login with correct credentials
- [ ] Add product to cart
- [ ] Complete checkout with test Stripe card
- [ ] Download purchased file
- [ ] Admin login
- [ ] Create product
- [ ] View orders
- [ ] Update order status
- [ ] View analytics
- [ ] Create coupon

### Test Stripe Card Numbers

```
4242 4242 4242 4242  → Visa (success)
4000 0025 0000 3155  → Visa (requires auth)
5555 5555 5555 4444  → Mastercard
3782 822463 10005    → American Express
```

Expiry: Any future date  
CVC: Any 3 digits

## Performance Optimization

- **Database**: Prepared statements, proper indexing
- **Frontend**: Responsive design, lazy loading
- **API**: Pagination (20 items default), query optimization
- **Files**: S3 with CloudFront CDN for distribution
- **Caching**: Consider Redis for session/dashboard stats

## Deployment

### Recommended Hosting

- **Server**: VPS (AWS EC2, DigitalOcean, Linode)
- **Database**: Managed MySQL (AWS RDS, Digital Ocean)
- **Storage**: AWS S3 or Spaces
- **Email**: SendGrid, Mailgun, AWS SES
- **CDN**: CloudFront or Cloudflare

### Deployment Steps

1. Clone repository
2. Install dependencies: `composer install`
3. Configure `.env` with production values
4. Run database migrations
5. Create admin user
6. Set file permissions: `chmod 755 storage/logs storage/invoices storage/products`
7. Enable HTTPS
8. Configure backups

## Support & Issues

- Check [docs/](docs/) for detailed guides
- Review error logs: `backend/logs/`
- Test API endpoints individually
- Verify `.env` configuration
- Check browser console for JavaScript errors

## License

© 2026 Digital Hub. All rights reserved.

## Next Steps

Suggested improvements:
- [ ] Support ticket system
- [ ] Customer reviews & ratings
- [ ] Affiliate program
- [ ] Email marketing campaigns
- [ ] Advanced analytics (LTV, retention, cohorts)
- [ ] Inventory/stock management
- [ ] Refund processing
- [ ] Tax configuration
- [ ] Role-based access control
- [ ] API rate limiting & monitoring
