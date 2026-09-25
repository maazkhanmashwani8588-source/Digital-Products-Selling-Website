# Admin Dashboard Setup Guide

## Quick Start

### 1. Create Admin User

Via MySQL CLI:
```sql
USE digital_hub;

-- Create admin account
INSERT INTO users (name, email, password, role, is_verified, created_at) 
VALUES (
  'Admin User',
  'admin@example.com',
  '$2y$10$ABC...XYZ',  -- Replace with password_hash() output
  'admin',
  1,
  NOW()
);
```

Or via PHP (one-time script):
```php
<?php
require 'backend/db.php';

$email = 'admin@example.com';
$password = password_hash('secure_password', PASSWORD_DEFAULT);

$stmt = $pdo->prepare('
  INSERT INTO users (name, email, password, role, is_verified, created_at)
  VALUES (?, ?, ?, ?, 1, NOW())
');
$stmt->execute(['Admin User', $email, $password, 'admin']);
echo 'Admin created!';
?>
```

### 2. Access Admin Dashboard

1. Navigate to: **http://localhost/frontend/admin/login.html**
2. Enter admin credentials:
   - Email: `admin@example.com`
   - Password: `secure_password`
3. Dashboard loads at: **http://localhost/frontend/admin/index.html**

### 3. Verify Backend Endpoints

Test endpoints are accessible:
```bash
curl http://localhost/backend/endpoints/admin/dashboard_stats.php
# Should return JSON (with auth error if not logged in)
```

## Features Available

### Dashboard Overview
- Last 30 days revenue
- Order count and status breakdown
- User growth metrics
- Top 5 products by sales
- Recent orders table
- Revenue trend chart

### Product Management
- View all products (searchable, paginated)
- Create new product
- Edit product (name, price, status, description)
- Delete product (blocked if has orders)
- File upload management

### Order Management
- List all orders (filter by status)
- Search orders by ID, email, customer name
- View order detail (items, customer, payment info)
- Update order status (pending → paid → fulfilled)

### Customer Management
- List all customers
- Search customers by name/email
- View customer profile (orders, downloads)
- Customer purchase history

### Coupon Management
- Create discount codes (percentage or fixed)
- Set coupon expiry date
- View all coupons
- Edit coupon value
- Delete expired coupons

### Analytics
- Sales revenue trend (30-day chart)
- Top 10 products by sales
- Category breakdown (orders & revenue)
- Export-ready data

## Database Requirements

Ensure these tables exist:
```sql
-- User management
SELECT * FROM users WHERE role = 'admin';

-- Products
SELECT * FROM products;

-- Orders
SELECT * FROM orders;
SELECT * FROM order_items;

-- Customers
SELECT * FROM users WHERE role = 'user';

-- Coupons
SELECT * FROM coupons;

-- Payments
SELECT * FROM payments;
```

Run migrations if needed:
```bash
mysql -u root digital_hub < database/schema.sql
mysql -u root digital_hub < database/migration_2_add_file_storage.sql
```

## File Structure

```
frontend/admin/
├── index.html         (Main dashboard - all sections)
├── login.html         (Admin login)

backend/endpoints/admin/
├── dashboard_stats.php
├── products_crud.php
├── orders_list.php
├── order_detail.php
├── update_order_status.php
├── customers_list.php
├── customer_detail.php
├── coupons_crud.php
├── analytics_sales.php

backend/
├── admin_middleware.php  (Role verification)

docs/
├── ADMIN_DASHBOARD.md    (Full documentation)
```

## Troubleshooting

### "Unauthorized - admin access required"
**Solution:** Verify user role in database:
```sql
SELECT role FROM users WHERE email = 'admin@example.com';
-- Should return: admin
```

### Admin dashboard not loading
**Solution:** Check:
1. Browser console for JavaScript errors
2. Network tab - verify `/backend/endpoints/admin/dashboard_stats.php` returns 200
3. Session is set: `echo $_SESSION['user_id'];` in test file

### Charts not displaying
**Solution:**
1. Verify Chart.js is loaded: `<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/...">`
2. Check analytics endpoint returns valid data
3. Browser console for Chart.js errors

### CRUD operations fail
**Solution:**
1. Verify Content-Type header: `'Content-Type': 'application/json'`
2. Check request method: POST for create, PUT for update, DELETE for delete
3. Review backend error logs

## Security Notes

✅ **Implemented:**
- Admin role required for all endpoints
- Session-based authentication
- Prepared statements (SQL injection prevention)
- Input validation
- CSRF protection via session

⚠️ **Recommended for Production:**
- Enable HTTPS only
- Set secure session cookie flags
- Implement rate limiting on admin endpoints
- Add audit logging (who changed what, when)
- Two-factor authentication (2FA) for admin accounts
- IP whitelisting for admin access

## Performance Optimization

### Database Queries
- All endpoints support pagination (default 20 items)
- Indexes on frequently queried columns
- Prepared statements for efficiency

### Frontend
- Charts only render when needed
- Minimal DOM manipulation
- CSS Grid/Flexbox for responsive layout

### Caching (Future)
```php
$cache_key = 'admin_stats_' . date('Y-m-d-H');
if ($cached = apcu_fetch($cache_key)) {
    return jsonResponse($cached);
}
// ... run query ...
apcu_store($cache_key, $data, 3600); // 1 hour
```

## Example: Create Product via Dashboard

1. Click "Add Product" button
2. Fill in form:
   - Product Name: "Advanced PHP Course"
   - Price: $49.99
   - Description: "Learn PHP from basics to advanced"
   - Status: Active
3. Click "Save"
4. Product appears in list
5. Can now upload file via file management

## Example: Update Order Status

1. Navigate to Orders section
2. Find order by ID or customer email
3. Click "View" button on order
4. Change status dropdown from "pending" to "paid"
5. Click confirm
6. Customer receives download email (if SMTP configured)

## Example: Create Discount Coupon

1. Navigate to Coupons section
2. Click "Add Coupon"
3. Fill in form:
   - Code: "SUMMER2026"
   - Type: Percentage
   - Value: 15
   - Expires: 2026-09-01
4. Click "Save"
5. Code available for customers at checkout

## Support & Documentation

- Full API reference: `docs/ADMIN_DASHBOARD.md`
- Backend code: `backend/endpoints/admin/`
- Frontend code: `frontend/admin/index.html`
- Database schema: `database/schema.sql`

## Next Steps

1. ✅ Admin Dashboard built and tested
2. → Suggested improvements:
   - Email blast to customers
   - Support ticket management
   - Advanced analytics (LTV, retention, cohorts)
   - Inventory management
   - Refund processing
   - Tax configuration
   - Role-based access control (limited admins)
