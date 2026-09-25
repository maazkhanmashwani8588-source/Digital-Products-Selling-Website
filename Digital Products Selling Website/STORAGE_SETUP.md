# File Storage Migration & Setup Guide

## Quick Start

### For Development (Local Storage)

```bash
# 1. Update database
mysql -u root digital_hub < database/migration_2_add_file_storage.sql

# 2. Create storage directory
mkdir -p storage/products
chmod 755 storage/products

# 3. Update .env
STORAGE_DRIVER=local
LOCAL_STORAGE_PATH=storage/products

# 4. That's it! Admin can now upload files via product management
```

### For Production (AWS S3)

```bash
# 1. Update database
mysql -u root digital_hub < database/migration_2_add_file_storage.sql

# 2. Install AWS SDK
composer require aws/aws-sdk-php

# 3. Create S3 bucket via AWS Console or CLI:
aws s3 mb s3://digital-hub-products --region us-east-1

# 4. Create IAM user with S3 access:
#    - Go to AWS IAM > Users > Create User > Set Programmatic Access
#    - Attach Policy: AmazonS3FullAccess (or create custom policy)
#    - Save Access Key ID and Secret Access Key

# 5. Configure .env with S3 credentials:
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_S3_REGION=us-east-1
AWS_S3_BUCKET=digital-hub-products

# 6. Optional: Block public S3 access
aws s3api put-bucket-versioning --bucket digital-hub-products --versioning-configuration Status=Enabled
aws s3api put-public-access-block --bucket digital-hub-products \
  --public-access-block-configuration \
  "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

## API Usage (Admin)

### Upload Product File

```javascript
// HTML
<input type="file" id="fileInput" />
<button onclick="uploadFile()">Upload</button>

// JavaScript
async function uploadFile() {
  const productId = 123; // Edit product ID
  const formData = new FormData();
  formData.append('file', document.getElementById('fileInput').files[0]);
  
  const response = await fetch(
    `/backend/endpoints/admin_upload_product_file.php?product_id=${productId}`,
    { method: 'POST', body: formData }
  );
  
  if (response.ok) {
    const result = await response.json();
    console.log('Upload successful:', result);
    // result.file_id - ID in product_files table
    // result.key - Storage key (path in S3 or local)
    // result.size - File size
    // result.hash - SHA-256 hash
  } else {
    console.error('Upload failed:', await response.text());
  }
}
```

### List Product Files

```javascript
const response = await fetch(
  '/backend/endpoints/product_files_list.php?product_id=123'
);
const { files } = await response.json();

files.forEach(file => {
  console.log(`${file.original_filename}: ${file.file_size} bytes, ${file.accessed_count} downloads`);
});
```

### Delete Product File

```javascript
await fetch(
  '/backend/endpoints/admin_delete_product_file.php?file_id=1',
  { method: 'DELETE' }
);
```

## Customer Flow

1. **Customer purchases product** → Stripe payment
2. **Webhook succeeds** → Email sent with:
   - Order confirmation
   - Download links (valid 1 hour)
   - Invoice PDF attachment
3. **Customer clicks download link** → File served securely
4. **Customer can regenerate links** → Via order detail page

## File Validation

### Allowed File Types

| Category | Types |
|----------|-------|
| Documents | PDF, DOC, DOCX, XLS, XLSX, TXT |
| Archives | ZIP, RAR, 7Z |
| Media | JPEG, PNG, GIF, MP4, MOV, MP3, WAV |

### Size Limits

- Minimum: 1 byte
- Maximum: 5 GB

### Security Checks

1. ✅ File size validation
2. ✅ MIME type whitelist
3. ✅ Magic bytes verification (prevents disguised executables)
4. ✅ Optional: ClamAV malware scanning
5. ✅ SHA-256 hash computed for integrity

## Troubleshooting

### Upload fails with "File type not allowed"

**Solution 1:** Verify file type
```bash
file your-file.pdf
# Should output: PDF document, version 1.4
```

**Solution 2:** Check MIME type detection
```php
echo mime_content_type('your-file.pdf'); // application/pdf
```

**Solution 3:** Add MIME type to whitelist
Edit `backend/services/Storage.php` and add to `ALLOWED_MIME_TYPES`.

### S3 "Access Denied" error

1. Verify AWS credentials in `.env`
2. Check IAM user has S3 permissions:
   ```bash
   aws s3 ls s3://digital-hub-products --profile your-profile
   ```
3. Verify bucket exists in correct region

### "File not found" on download

Check database:
```sql
SELECT id, storage_key, original_filename, is_active 
FROM product_files 
WHERE product_id = 123;
```

Verify file exists in storage:
```bash
# Local:
ls -la storage/products/123/2026/06/18/

# S3:
aws s3 ls s3://digital-hub-products/products/123/2026/06/18/
```

### Storage logs

Check for errors:
```bash
tail -f backend/logs/storage.log
tail -f backend/logs/stripe.log
```

## Performance Tips

### Local Storage
- Monitor disk space usage
- Set up cron job to delete old files (>90 days)
- Use SSD for faster I/O

### S3 Storage
- Enable S3 Transfer Acceleration for faster uploads
- Use CloudFront CDN for geographic distribution
- Set up S3 lifecycle policies to archive old files
- Monitor costs: typically $0.023 per GB/month

## Security Checklist

- [ ] S3 bucket has Block Public Access enabled
- [ ] IAM user has minimal permissions (S3 only)
- [ ] Signed URLs expire after reasonable time (1 hour default)
- [ ] All uploads validated (size, type, magic bytes)
- [ ] Download access requires paid order
- [ ] All operations logged (upload, delete, download)
- [ ] Credentials stored in `.env`, never in code
- [ ] HTTPS enforced in production
- [ ] Regular backups of MySQL `product_files` table

## Migration from Old System (Legacy)

If you have existing products with `file_path` in database:

1. **Existing downloads still work** — `/download.php` supports legacy mode
2. **New uploads use product_files** — Automatically
3. **Gradual migration**: No need to migrate existing products immediately
4. **To migrate manually**:
   ```bash
   # Example: Move legacy product to new storage
   # 1. Manually upload file via admin UI
   # 2. Old file_path continues to work as fallback
   # 3. Both systems coexist
   ```

## Documentation

- [STORAGE.md](../docs/STORAGE.md) — Complete storage system documentation
- [Architecture & API Reference](../docs/STORAGE.md#architecture)
- [Email Integration](../docs/EMAIL.md#stripe-webhook-stripe_webhookphp)
