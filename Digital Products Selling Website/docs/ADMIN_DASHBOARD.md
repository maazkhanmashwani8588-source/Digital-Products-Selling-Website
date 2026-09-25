# Admin Dashboard Documentation

## Overview

Digital Hub includes a production-ready full-featured admin dashboard with:

- **Dashboard Overview** — Sales stats, user metrics, revenue trends, recent orders
- **Product Management** — Create, edit, delete products with file uploads
- **Order Management** — View all orders, update status, payment tracking
- **Customer Management** — View customers, purchase history, download activity
- **Coupon Management** — Create and manage discount codes (percentage & fixed)
- **Analytics** — Sales trends, top products, category breakdown
- **Secure Access Control** — Admin role required, session-based authentication
- **Responsive Design** — Works on desktop and mobile (Bootstrap 5)

## Access & Authentication

### Admin Login

1. Navigate to `/frontend/admin/login.html`
2. Enter admin account credentials
3. Dashboard loads at `/frontend/admin/index.html`

**Note:** Only users with `role = 'admin'` can access the admin dashboard.

### Create Admin User (via MySQL)

```sql
INSERT INTO users (name, email, password, role, created_at) 
VALUES ('Admin User', 'admin@example.com', 
        PASSWORD_HASH_HERE, 'admin', NOW());
```

To generate admin password hash in PHP:
```php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

### Session Management

- Admin status stored in `sessionStorage` on client
- All backend endpoints require authentication via `$_SESSION['user_id']`
- Additional role check ensures `role = 'admin'` for all admin endpoints
- Logout clears session and redirects to login

## Backend API Reference

### Dashboard Statistics

**Endpoint:** `GET /backend/endpoints/admin/dashboard_stats.php`

**Response:**
```json
{
  "success": true,
  "revenue": {
    "total": 5250.00,
    "orders_count": 15,
    "by_date": [
      { "date": "2026-06-10", "revenue": 125.00, "orders": 2 },
      ...
    ]
  },
  "users": {
    "total": 42,
    "new_last_30": 8
  },
  "orders": {
    "total": 156,
    "paid": 145,
    "pending": 11
  },
  "products": {
    "total": 24,
    "top_5": [
      { "id": 1, "title": "...", "sales_count": 12, "revenue": 240.00 },
      ...
    ]
  },
  "recent_orders": [...]
}
```

### Products CRUD

**List Products**
```http
GET /backend/endpoints/admin/products_crud.php?page=1&per_page=20&search=keyword
```

**Create Product**
```http
POST /backend/endpoints/admin/products_crud.php
Content-Type: application/json

{
  "title": "Product Name",
  "price": 29.99,
  "category_id": 1,
  "description": "...",
  "short_description": "...",
  "sku": "SKU-123",
  "featured": 0,
  "status": "active"
}
```

**Update Product**
```http
PUT /backend/endpoints/admin/products_crud.php
Content-Type: application/json

{
  "id": 1,
  "title": "Updated Name",
  "price": 39.99,
  "status": "active"
}
```

**Delete Product**
```http
DELETE /backend/endpoints/admin/products_crud.php
Content-Type: application/json

{ "id": 1 }
```

### Orders Management

**List Orders**
```http
GET /backend/endpoints/admin/orders_list.php?page=1&status=paid&search=keyword
```

**Get Order Detail**
```http
GET /backend/endpoints/admin/order_detail.php?id=123
```

**Update Order Status**
```http
POST /backend/endpoints/admin/update_order_status.php
Content-Type: application/json

{
  "id": 123,
  "status": "paid"
}
```

Status options: `pending`, `paid`, `failed`

### Customers Management

**List Customers**
```http
GET /backend/endpoints/admin/customers_list.php?page=1&search=keyword
```

**Get Customer Detail**
```http
GET /backend/endpoints/admin/customer_detail.php?id=5
```

Returns customer info, order history, and download activity.

### Coupons Management

**List Coupons**
```http
GET /backend/endpoints/admin/coupons_crud.php?page=1
```

**Create Coupon**
```http
POST /backend/endpoints/admin/coupons_crud.php
Content-Type: application/json

{
  "code": "SAVE20",
  "type": "percent",
  "value": 20,
  "expires_at": "2026-12-31 23:59:59"
}
```

**Update Coupon**
```http
PUT /backend/endpoints/admin/coupons_crud.php
Content-Type: application/json

{
  "id": 1,
  "value": 25,
  "expires_at": "2027-01-31 23:59:59"
}
```

**Delete Coupon**
```http
DELETE /backend/endpoints/admin/coupons_crud.php
Content-Type: application/json

{ "id": 1 }
```

### Analytics

**Get Sales Analytics**
```http
GET /backend/endpoints/admin/analytics_sales.php?days=30
```

Returns sales by date, top products, and category breakdown.

## Frontend Components

### Dashboard Page (`/frontend/admin/index.html`)

**Features:**
- Stats cards: Revenue, Orders, Users, Products
- Revenue trend chart (line graph)
- Recent orders table
- Top 5 products list

**Sections:**
1. **Dashboard** — Overview and key metrics
2. **Products** — List, search, create, edit, delete
3. **Orders** — List with filters, view detail, update status
4. **Customers** — Search, view detail, purchase history
5. **Coupons** — Create, edit, delete discount codes
6. **Analytics** — Sales trends, top products, categories

### Admin Login Page (`/frontend/admin/login.html`)

- Secure login form
- Role validation (admin only)
- Session storage for admin flag
- Error messaging

### UI Components

**Built with:**
- Bootstrap 5 (responsive grid, modals, buttons)
- Chart.js (sales & product analytics charts)
- Bootstrap Icons (navigation)
- Vanilla JavaScript (AJAX calls, DOM updates)

**Responsive:**
- Desktop: Full sidebar navigation
- Tablet: Flexible layout
- Mobile: Stacked navigation, touch-friendly buttons

## Database Tables

### Key Tables

| Table | Purpose |
|-------|---------|
| `users` | Users with `role = 'admin'` for dashboard access |
| `products` | Managed via Products section |
| `orders` | Managed via Orders section |
| `order_items` | Order line items |
| `customers` | User info (viewed in Customers section) |
| `coupons` | Discount codes (managed in Coupons section) |
| `product_files` | Product download files |
| `payments` | Payment records |

### Admin Queries

**Total revenue (30 days):**
```sql
SELECT SUM(total) FROM orders 
WHERE status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**Order breakdown:**
```sql
SELECT status, COUNT(*) FROM orders GROUP BY status;
```

**Top products:**
```sql
SELECT p.title, COUNT(oi.id) as sales 
FROM products p
JOIN order_items oi ON p.id = oi.product_id
GROUP BY p.id
ORDER BY sales DESC
LIMIT 5;
```

## Security

### Authentication & Authorization

- ✅ Session-based auth (PHP sessions)
- ✅ Role checking in every admin endpoint
- ✅ Admin role required for all admin routes
- ✅ User ID validated in every request

### Data Protection

- ✅ All queries use prepared statements (SQL injection prevention)
- ✅ Input validation on all forms
- ✅ Output escaping in HTML (via Bootstrap & vanilla JS)
- ✅ Admin operations logged (via MySQL)

### Access Control

- Admin dashboard only accessible to admin users
- Customer data only viewable by admins
- Orders can only be modified by admins
- Products can only be managed by admins

## Usage Examples

### Create a New Product

```javascript
fetch('/backend/endpoints/admin/products_crud.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    title: 'New eBook',
    price: 19.99,
    category_id: 2,
    description: 'A great eBook',
    status: 'active'
  })
}).then(r => r.json()).then(data => {
  if (data.success) {
    console.log('Product created with ID:', data.id);
    // Reload products list
  }
});
```

### Update Order Status

```javascript
fetch('/backend/endpoints/admin/update_order_status.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    id: 123,
    status: 'paid'
  })
}).then(r => r.json()).then(data => {
  if (data.success) {
    console.log('Order updated');
    loadOrders(); // Refresh list
  }
});
```

### View Analytics

```javascript
fetch('/backend/endpoints/admin/analytics_sales.php?days=30')
  .then(r => r.json())
  .then(data => {
    console.log('Sales by date:', data.sales_by_date);
    console.log('Top products:', data.top_products);
    // Update charts...
  });
```

## Troubleshooting

### Admin Dashboard Won't Load

1. Check if logged in as admin: `sessionStorage.getItem('admin_logged_in')`
2. Verify admin role in database: `SELECT role FROM users WHERE id = YOUR_ID`
3. Check browser console for AJAX errors
4. Verify backend endpoints are accessible: `/backend/endpoints/admin/dashboard_stats.php`

### Can't Login

1. Verify admin account exists: `SELECT * FROM users WHERE email = 'admin@example.com' AND role = 'admin'`
2. Test password: `password_verify('password', hash_from_db)`
3. Check session is being set: Verify cookies in browser

### Charts Not Showing

1. Verify Chart.js is loaded: `window.Chart` should exist
2. Check analytics endpoint returns data: Open `/backend/endpoints/admin/analytics_sales.php` in browser
3. Verify chart canvas elements exist in HTML

### CRUD Operations Failing

1. Check admin role: All endpoints verify `role = 'admin'`
2. Verify authentication: `$_SESSION['user_id']` must be set
3. Check request format: POST/PUT/DELETE must use `application/json`
4. Review error response from backend

## Performance Tips

### Database Optimization

- **Indexing:** Add indexes on frequently queried columns:
  ```sql
  CREATE INDEX idx_orders_status ON orders(status);
  CREATE INDEX idx_orders_user_id ON orders(user_id);
  CREATE INDEX idx_order_items_order_id ON order_items(order_id);
  ```

- **Pagination:** Always paginate large lists (20-50 items per page)

- **Caching:** Consider caching dashboard stats for 5 minutes:
  ```php
  $cache_key = 'dashboard_stats_' . date('Y-m-d-H-i/5');
  ```

### Frontend Optimization

- Charts only update when section viewed
- Pagination prevents loading all records
- Search debouncing to reduce requests
- Lazy load product thumbnails

## Future Enhancements

- [ ] Bulk product import (CSV upload)
- [ ] Email templates editor
- [ ] Advanced analytics (customer lifetime value, retention)
- [ ] Inventory/stock tracking
- [ ] Refund management
- [ ] Tax configuration
- [ ] Export reports (PDF/CSV)
- [ ] Role-based access control (limited admin privileges)
- [ ] Email blast to customers
- [ ] Support ticket management
- [ ] Admin activity audit log
- [ ] Dark mode toggle

## File Structure

```
frontend/admin/
├── index.html         (Main dashboard)
├── login.html         (Admin login)
backend/endpoints/admin/
├── dashboard_stats.php       (Stats & overview)
├── products_crud.php         (Product management)
├── orders_list.php          (Orders list)
├── order_detail.php         (Order details)
├── update_order_status.php   (Update order)
├── customers_list.php       (Customers list)
├── customer_detail.php      (Customer details)
├── coupons_crud.php         (Coupon management)
├── analytics_sales.php      (Analytics data)
backend/
├── admin_middleware.php      (Admin auth middleware)
```

## Testing Checklist

- [ ] Admin can login with correct credentials
- [ ] Non-admin user cannot access admin dashboard
- [ ] Dashboard stats load correctly
- [ ] Can create new product
- [ ] Can edit product
- [ ] Can delete product (with order check)
- [ ] Can view all orders
- [ ] Can update order status
- [ ] Can view customer details
- [ ] Can create coupon
- [ ] Can view analytics charts
- [ ] All forms validate input
- [ ] Pagination works
- [ ] Search filters work
- [ ] Charts render correctly
- [ ] Responsive design on mobile
