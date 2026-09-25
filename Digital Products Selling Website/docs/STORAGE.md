# Secure File Storage System

## Overview

Digital Hub implements a production-ready, scalable file storage system with:
- **Dual backend support**: AWS S3 for production, local storage for development
- **Server-side uploads**: Only admins can upload files; client cannot access raw storage
- **Metadata tracking**: File size, mime type, hash, access count stored in MySQL
- **Signed URLs**: Time-limited, cryptographically signed download links (S3) or HMAC-signed endpoints (local)
- **Access control**: Downloads only available after verified payment
- **Virus scanning**: Optional ClamAV integration for malware detection
- **Audit trail**: Full logging of uploads, deletions, and downloads

## Architecture

### Storage Backend

```
┌─────────────────────────────────────────────────────────┐
│                    Admin Uploads Files                   │
│         /backend/endpoints/admin_upload_product_file.php │
└──────────────────────┬──────────────────────────────────┘
                       │ Storage.php
                       ├─ Validate (type, size, magic bytes)
                       ├─ Scan with ClamAV (optional)
                       └─ Upload to S3 or Local
                       
┌─────────────────────────────────────────────────────────┐
│              product_files Table (MySQL)                 │
│  Stores: storage_key, filename, size, mime, hash, etc   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│              Verification & Download                     │
│   Customer pays → Webhook verifies → Email with link     │
│   generate_download_link.php → Storage.getSignedUrl()   │
│   /download.php → Stream file or redirect to S3          │
└─────────────────────────────────────────────────────────┘
```

### Database Schema

**product_files table:**
```sql
CREATE TABLE product_files (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  storage_key VARCHAR(512),        -- Format: products/123/2026/06/abc123-filename.pdf
  original_filename VARCHAR(255),
  mime_type VARCHAR(100),
  file_size INT,
  file_hash VARCHAR(64),           -- SHA-256 for integrity verification
  storage_driver ENUM('s3', 'local'),
  s3_bucket VARCHAR(100),
  s3_region VARCHAR(50),
  uploaded_at TIMESTAMP,
  accessed_count INT DEFAULT 0,    -- Download count
  last_accessed TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1,  -- For soft deletes
  FOREIGN KEY (product_id) REFERENCES products(id)
);
```

**Backward compatibility:**
- `products.file_path` still used for legacy downloads (old orders)
- New orders use `product_files` table exclusively
- Migration path: create product_files records alongside old file_path

## Configuration

### Development (Local Storage)

```env
STORAGE_DRIVER=local
LOCAL_STORAGE_PATH=storage/products
APP_SECRET=your-secret-key
```

Files stored at: `storage/products/123/2026/06/18/hash-filename.pdf`

### Production (AWS S3)

```env
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=digital-hub-products
APP_SECRET=your-secret-key
```

**S3 Bucket Policy (recommended):**
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::digital-hub-products/*",
      "Condition": {
        "StringNotLike": {
          "aws:userid": "AIDACKCEVSQ6C2EXAMPLE:*"
        }
      }
    }
  ]
}
```

This prevents direct public access; downloads only via signed URLs from application.

## File Upload Pipeline

### 1. Admin Uploads File

**Endpoint:** `POST /backend/endpoints/admin_upload_product_file.php?product_id=123`
**Auth:** Admin only
**Input:** Multipart form-data with `file` field

```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);

const response = await fetch(
  `/backend/endpoints/admin_upload_product_file.php?product_id=123`,
  { method: 'POST', body: formData }
);

const result = await response.json();
// result.file_id, result.key, result.size, result.mime, result.hash
```

### 2. Validation & Security Checks

**Storage.php::upload()** validates:

| Check | Details |
|-------|---------|
| **File exists** | Must be readable |
| **File size** | 1 byte - 5 GB |
| **MIME type** | Whitelist (PDF, ZIP, Office docs, images, video, audio) |
| **Magic bytes** | Verify file header matches MIME type |
| **Malware scan** | Optional ClamAV (requires separate setup) |

**Allowed file types:**
- Documents: PDF, DOC, DOCX, XLS, XLSX, TXT
- Archives: ZIP, RAR, 7Z
- Media: JPEG, PNG, GIF, MP4, MOV, MP3, WAV

### 3. Storage & Metadata

File uploaded to:
- **S3**: `s3://digital-hub-products/products/123/2026/06/18/abc123-filename.pdf`
- **Local**: `storage/products/123/2026/06/18/abc123-filename.pdf`

Metadata stored in MySQL `product_files` table:
- `storage_key`: Full path in storage backend
- `original_filename`: User-visible name
- `file_hash`: SHA-256 for integrity verification
- `file_size`: Size in bytes
- `mime_type`: MIME type (application/pdf, etc.)
- `uploaded_at`: Timestamp
- `accessed_count`: Download counter

### 4. Email with Download Links

After Stripe payment succeeds:
1. Webhook retrieves all files for order's products
2. Storage.getSignedUrl() generates time-limited links:
   - **S3**: Pre-signed URL via AWS API (valid 1 hour)
   - **Local**: HMAC-signed `/download.php?key=...&expires=...&sig=...` (valid 1 hour)
3. Email sent with clickable download buttons
4. Download links also available in order detail page

## Download & File Serving

### Endpoint: `/backend/endpoints/download.php`

Supports two modes:

#### Mode 1: New (Product Files)
```
GET /backend/endpoints/download.php?key=storage_key&expires=1234567890&sig=hmac
```

1. **Verify signature**: HMAC-SHA256(key|expires) == sig
2. **Check expiry**: time() < expires
3. **Verify access**: User has paid order containing this file
4. **For S3**: Redirect to AWS pre-signed URL
5. **For local**: Stream file with proper headers

#### Mode 2: Legacy (Products.file_path)
```
GET /backend/endpoints/download.php?file=path&order=123&expires=1234567890&sig=hmac
```

Backward compatible with pre-existing orders using old file_path system.

### Security Measures

| Layer | Implementation |
|-------|-----------------|
| **Authentication** | Verify $_SESSION['user_id'] |
| **Authorization** | Verify user owns paid order |
| **Expiry** | Links valid only X hours (1 hour default) |
| **Signature** | HMAC-SHA256 prevents tampering |
| **Rate limiting** | Optional: throttle downloads per user |
| **Logging** | All downloads logged to `/backend/logs/downloads.log` |

## Admin Management

### List Product Files

**Endpoint:** `GET /backend/endpoints/product_files_list.php?product_id=123`

```javascript
const response = await fetch(`/backend/endpoints/product_files_list.php?product_id=123`);
const { files } = await response.json();
// files[].id, storage_key, original_filename, file_size, uploaded_at, accessed_count
```

### Delete Product File

**Endpoint:** `DELETE /backend/endpoints/admin_delete_product_file.php?file_id=1`

```javascript
await fetch(`/backend/endpoints/admin_delete_product_file.php?file_id=1`, 
  { method: 'DELETE' });
```

- Soft-deletes (sets `is_active = 0`)
- Removes from storage backend (S3 or local)
- Existing download links still work (file retained for 30 days, optional)

## API Reference

### Upload Endpoint

```http
POST /backend/endpoints/admin_upload_product_file.php?product_id=123
Content-Type: multipart/form-data

file: [binary file content]
```

**Response:**
```json
{
  "success": true,
  "file_id": 1,
  "key": "products/123/2026/06/18/abc123-filename.pdf",
  "size": 5242880,
  "mime": "application/pdf",
  "hash": "abc123...",
  "original_name": "filename.pdf"
}
```

### Get Files List

```http
GET /backend/endpoints/product_files_list.php?product_id=123
```

**Response:**
```json
{
  "success": true,
  "files": [
    {
      "id": 1,
      "storage_key": "products/123/2026/06/18/...",
      "original_filename": "ebook.pdf",
      "mime_type": "application/pdf",
      "file_size": 5242880,
      "uploaded_at": "2026-06-18 12:34:56",
      "accessed_count": 15,
      "is_active": 1
    }
  ]
}
```

### Generate Download Link

```http
GET /backend/endpoints/generate_download_link.php?file_id=1&order_id=456
```

**Response:**
```json
{
  "success": true,
  "download_url": "https://s3.amazonaws.com/... or /backend/endpoints/download.php?...",
  "expires_in_seconds": 3600,
  "file_name": "ebook.pdf"
}
```

### Download File

```http
GET /backend/endpoints/download.php?key=...&expires=...&sig=...
```

**Response:** File stream (binary) with headers:
- `Content-Type: application/octet-stream`
- `Content-Disposition: attachment; filename="..."`
- `Content-Length: [bytes]`

## Logging

### Log Files

| File | Events |
|------|--------|
| `/backend/logs/storage.log` | Uploads, deletions, S3 errors |
| `/backend/logs/stripe.log` | Payment processing & email errors |
| `/backend/logs/downloads.log` | Download access attempts |

### Log Format

```
2026-06-18T12:34:56+00:00 File uploaded: products/123/2026/06/18/abc123.pdf (size: 5242880, hash: abc123...)
2026-06-18T12:35:12+00:00 email sent to user@example.com for order 456
2026-06-18T12:36:00+00:00 Download: user_id=1, file=products/123/2026/06/18/abc123.pdf, order=456
```

## Installation & Setup

### 1. Create Migration

```bash
mysql -u root digital_hub < database/migration_2_add_file_storage.sql
```

### 2. Install AWS SDK (optional, for S3)

```bash
composer require aws/aws-sdk-php
```

### 3. Create Storage Directory (local only)

```bash
mkdir -p storage/products
chmod 755 storage/products
```

### 4. Configure .env

```env
STORAGE_DRIVER=local
LOCAL_STORAGE_PATH=storage/products
```

Or for S3:
```env
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=digital-hub-products
```

### 5. Test Upload

Upload a file via admin panel, verify:
- File appears in `product_files` table
- Entry in `storage/products/` or S3 console
- Email received with download link

## Troubleshooting

### "File type not allowed"
- Check MIME type detection: `file -i your-file.pdf`
- Add MIME type to `ALLOWED_MIME_TYPES` in `Storage.php`
- Verify file magic bytes match extension

### "File not found" on download
- Verify file exists in storage backend
- Check `product_files.storage_key` in MySQL
- Review `storage.log` for upload errors

### AWS S3 connection fails
- Verify IAM credentials in `.env`
- Check S3 bucket name and region
- Test credentials: `aws s3 ls s3://bucket-name/`

### Signed URLs expire too quickly
- For S3: Default 1 hour, adjust `getSignedUrl()` parameter
- For local: Generate new link via `generate_download_link.php`

## Security Best Practices

1. **Never expose storage backend**
   - S3 bucket not publicly readable
   - Local storage outside web root (if possible)
   - All access via signed URLs/endpoints

2. **Validate all uploads**
   - Check file type, size, and magic bytes
   - Scan with antivirus (optional)
   - Use unique filenames to prevent overwrite

3. **Log access for audit trail**
   - Track who downloaded what and when
   - Alert on unusual patterns

4. **Rotate credentials regularly**
   - AWS IAM keys: change every 90 days
   - APP_SECRET: update in production

5. **Monitor storage costs**
   - S3: Set lifecycle policies (archive old files)
   - Limit file size to 5 GB
   - Delete inactive files after 90 days

## Future Enhancements

- [ ] Virus scanning via ClamAV
- [ ] CDN integration (CloudFront)
- [ ] Compressed format support (.rar, .7z)
- [ ] Batch file uploads
- [ ] Direct S3 uploads from browser (pre-signed POST)
- [ ] File preview (PDF viewer, image gallery)
- [ ] Bandwidth throttling
- [ ] Download history analytics
