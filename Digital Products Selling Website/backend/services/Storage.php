<?php
/**
 * Secure File Storage Service
 * 
 * Handles file uploads to AWS S3 or local storage with:
 * - File validation (type, size, magic bytes)
 * - Virus scanning (ClamAV optional)
 * - Signed URLs for time-limited access
 * - Metadata storage and retrieval
 */

class Storage
{
    private $driver;
    private $s3Client;
    private $localStoragePath;
    private $logger;
    
    // Configuration
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/mp4',
        'video/quicktime',
        'audio/mpeg',
        'audio/wav'
    ];
    
    private const MAX_FILE_SIZE = 5 * 1024 * 1024 * 1024; // 5GB
    private const MIN_FILE_SIZE = 1; // 1 byte
    private const SCAN_WITH_CLAM_AV = false; // Set to true if ClamAV available

    public function __construct()
    {
        $this->driver = getenv('STORAGE_DRIVER') ?: 'local';
        $this->localStoragePath = getenv('LOCAL_STORAGE_PATH') ?: __DIR__ . '/../../storage/products';
        
        // Initialize S3 client if needed
        if ($this->driver === 's3') {
            try {
                if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                    require_once __DIR__ . '/../../vendor/autoload.php';
                    
                    $this->s3Client = new \Aws\S3\S3Client([
                        'version' => 'latest',
                        'region'  => getenv('AWS_S3_REGION') ?: 'us-east-1',
                        'credentials' => [
                            'key'    => getenv('AWS_ACCESS_KEY_ID'),
                            'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                        ]
                    ]);
                } else {
                    throw new Exception('AWS SDK not installed. Run: composer require aws/aws-sdk-php');
                }
            } catch (Exception $e) {
                @file_put_contents(__DIR__ . '/../logs/storage.log', date('c') . " S3 initialization failed: " . $e->getMessage() . "\n", FILE_APPEND);
                throw $e;
            }
        } else {
            // Ensure local storage directory exists
            if (!is_dir($this->localStoragePath)) {
                @mkdir($this->localStoragePath, 0755, true);
            }
        }
    }

    /**
     * Upload file to storage backend
     * 
     * @param string|array $file - Either file path or $_FILES array element
     * @param array $options - ['product_id', 'custom_name', 'metadata']
     * @return array - ['success' => bool, 'key' => str, 'size' => int, 'mime' => str, 'hash' => str, 'error' => str]
     */
    public function upload($file, $options = [])
    {
        try {
            // Parse input
            if (is_array($file) && isset($file['tmp_name'])) {
                $tmpPath = $file['tmp_name'];
                $originalName = $file['name'];
                $tmpSize = $file['size'];
            } else {
                $tmpPath = $file;
                $originalName = $options['custom_name'] ?? basename($file);
                $tmpSize = filesize($file);
            }

            // Validate file exists and is readable
            if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
                return ['success' => false, 'error' => 'File not found or not readable'];
            }

            // Validate file size
            if ($tmpSize < self::MIN_FILE_SIZE || $tmpSize > self::MAX_FILE_SIZE) {
                return ['success' => false, 'error' => 'File size invalid (must be 1B - 5GB)'];
            }

            // Get MIME type
            $mimeType = mime_content_type($tmpPath);
            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                return ['success' => false, 'error' => 'File type not allowed: ' . $mimeType];
            }

            // Validate magic bytes (prevent disguised executables)
            if (!$this->validateMagicBytes($tmpPath, $mimeType)) {
                return ['success' => false, 'error' => 'File magic bytes do not match MIME type (possible malicious file)'];
            }

            // Optional: scan with ClamAV
            if (self::SCAN_WITH_CLAM_AV) {
                $scanResult = $this->scanWithClamAV($tmpPath);
                if (!$scanResult['safe']) {
                    return ['success' => false, 'error' => 'File failed malware scan: ' . $scanResult['threat']];
                }
            }

            // Calculate file hash
            $fileHash = hash_file('sha256', $tmpPath);

            // Generate storage key (prevents directory traversal attacks)
            $productId = $options['product_id'] ?? 'unknown';
            $storageKey = $this->generateStorageKey($originalName, $productId, $fileHash);

            // Upload to backend
            if ($this->driver === 's3') {
                $result = $this->uploadToS3($tmpPath, $storageKey, $mimeType);
            } else {
                $result = $this->uploadToLocal($tmpPath, $storageKey);
            }

            if (!$result['success']) {
                return $result;
            }

            // Log successful upload
            @file_put_contents(__DIR__ . '/../logs/storage.log', 
                date('c') . " File uploaded: {$storageKey} (size: {$tmpSize}, hash: {$fileHash})\n", 
                FILE_APPEND);

            return [
                'success' => true,
                'key' => $storageKey,
                'size' => $tmpSize,
                'mime' => $mimeType,
                'hash' => $fileHash,
                'original_name' => $originalName
            ];

        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/../logs/storage.log', 
                date('c') . " Upload error: " . $e->getMessage() . "\n", 
                FILE_APPEND);
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get a signed/secure URL for downloading the file
     * 
     * @param string $storageKey - Storage key returned from upload()
     * @param int $expirySeconds - URL validity in seconds (default 1 hour)
     * @return string - Signed URL or local download endpoint
     */
    public function getSignedUrl($storageKey, $expirySeconds = 3600)
    {
        if ($this->driver === 's3') {
            try {
                $bucket = getenv('AWS_S3_BUCKET');
                $cmd = $this->s3Client->getCommand('GetObject', [
                    'Bucket' => $bucket,
                    'Key'    => $storageKey
                ]);
                $request = $this->s3Client->createPresignedRequest($cmd, '+' . $expirySeconds . ' seconds');
                $url = (string)$request->getUri();
                return $url;
            } catch (Exception $e) {
                @file_put_contents(__DIR__ . '/../logs/storage.log', 
                    date('c') . " Failed to generate S3 signed URL: " . $e->getMessage() . "\n", 
                    FILE_APPEND);
                return null;
            }
        } else {
            // For local storage, return endpoint that will verify payment and serve file
            $expires = time() + $expirySeconds;
            $sig = hash_hmac('sha256', $storageKey . '|' . $expires, getenv('APP_SECRET') ?: 'change_this_secret');
            return '/backend/endpoints/download.php?key=' . urlencode($storageKey) . '&expires=' . $expires . '&sig=' . $sig;
        }
    }

    /**
     * Delete file from storage
     * 
     * @param string $storageKey - Storage key
     * @return bool - Success status
     */
    public function delete($storageKey)
    {
        try {
            if ($this->driver === 's3') {
                $bucket = getenv('AWS_S3_BUCKET');
                $this->s3Client->deleteObject([
                    'Bucket' => $bucket,
                    'Key'    => $storageKey
                ]);
            } else {
                $filePath = $this->localStoragePath . '/' . $this->sanitizeStorageKey($storageKey);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            @file_put_contents(__DIR__ . '/../logs/storage.log', 
                date('c') . " File deleted: {$storageKey}\n", 
                FILE_APPEND);
            return true;
        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/../logs/storage.log', 
                date('c') . " Delete error: " . $e->getMessage() . "\n", 
                FILE_APPEND);
            return false;
        }
    }

    /**
     * Check if file exists
     * 
     * @param string $storageKey - Storage key
     * @return bool
     */
    public function exists($storageKey)
    {
        try {
            if ($this->driver === 's3') {
                $bucket = getenv('AWS_S3_BUCKET');
                return $this->s3Client->doesObjectExist($bucket, $storageKey);
            } else {
                $filePath = $this->localStoragePath . '/' . $this->sanitizeStorageKey($storageKey);
                return file_exists($filePath);
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get file stream for local serving (used by download.php)
     * 
     * @param string $storageKey - Storage key
     * @return resource|null - File handle or null
     */
    public function getFileStream($storageKey)
    {
        if ($this->driver !== 'local') {
            return null;
        }
        
        $filePath = $this->localStoragePath . '/' . $this->sanitizeStorageKey($storageKey);
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }
        
        return fopen($filePath, 'rb');
    }

    /**
     * Get file size
     * 
     * @param string $storageKey - Storage key
     * @return int|null - File size in bytes or null
     */
    public function getFileSize($storageKey)
    {
        try {
            if ($this->driver === 's3') {
                $bucket = getenv('AWS_S3_BUCKET');
                $result = $this->s3Client->headObject([
                    'Bucket' => $bucket,
                    'Key'    => $storageKey
                ]);
                return $result['ContentLength'];
            } else {
                $filePath = $this->localStoragePath . '/' . $this->sanitizeStorageKey($storageKey);
                if (file_exists($filePath)) {
                    return filesize($filePath);
                }
            }
        } catch (Exception $e) {
            return null;
        }
        return null;
    }

    // ============= PRIVATE HELPER METHODS =============

    private function uploadToS3($tmpPath, $storageKey, $mimeType)
    {
        try {
            $bucket = getenv('AWS_S3_BUCKET');
            $this->s3Client->putObject([
                'Bucket'      => $bucket,
                'Key'         => $storageKey,
                'SourceFile'  => $tmpPath,
                'ContentType' => $mimeType,
                'ServerSideEncryption' => 'AES256',
                'Metadata'    => [
                    'uploaded-at' => date('c'),
                    'uploaded-by' => $_SESSION['user_id'] ?? 'unknown'
                ]
            ]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'S3 upload failed: ' . $e->getMessage()];
        }
    }

    private function uploadToLocal($tmpPath, $storageKey)
    {
        try {
            $destPath = $this->localStoragePath . '/' . $this->sanitizeStorageKey($storageKey);
            $destDir = dirname($destPath);
            
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            
            if (!copy($tmpPath, $destPath)) {
                return ['success' => false, 'error' => 'Failed to copy file'];
            }
            
            chmod($destPath, 0644);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Local upload failed: ' . $e->getMessage()];
        }
    }

    private function generateStorageKey($originalName, $productId, $fileHash)
    {
        // Format: products/{product_id}/{date}/{hash}-{filename}
        // Prevents directory traversal and collision
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = preg_replace('/[^a-z0-9\-_]/i', '', $name); // Remove special chars
        $name = substr($name, 0, 50); // Limit length
        $key = 'products/' . $productId . '/' . date('Y/m/d') . '/' . substr($fileHash, 0, 16) . '-' . $name . '.' . $ext;
        return $key;
    }

    private function sanitizeStorageKey($storageKey)
    {
        // Prevent directory traversal attacks
        $storageKey = str_replace('..', '', $storageKey);
        $storageKey = ltrim($storageKey, '/');
        return $storageKey;
    }

    private function validateMagicBytes($filePath, $mimeType)
    {
        // Read file header to verify magic bytes
        $handle = fopen($filePath, 'rb');
        if (!$handle) return false;
        
        $magic = fread($handle, 12);
        fclose($handle);
        
        $magicPatterns = [
            'application/pdf' => '/^%PDF/i',
            'application/zip' => '/^PK\x03\x04/i',
            'application/x-rar-compressed' => '/^Rar!\x1a\x07/i',
            'image/jpeg' => '/^\xFF\xD8\xFF/i',
            'image/png' => '/^\x89PNG\r\n\x1a\n/i',
            'image/gif' => '/^GIF8[9a]/i',
            'text/plain' => true, // Allow any magic bytes for text
        ];
        
        if (!isset($magicPatterns[$mimeType])) {
            return true; // Unknown type, allow
        }
        
        if ($magicPatterns[$mimeType] === true) {
            return true;
        }
        
        return preg_match($magicPatterns[$mimeType], $magic) === 1;
    }

    private function scanWithClamAV($filePath)
    {
        // Integration with ClamAV for malware scanning (optional)
        // Requires: apt-get install clamav clamav-daemon
        // Or use: composer require mglaman/clamav-php
        
        if (!function_exists('clamscan_scanfile')) {
            return ['safe' => true]; // ClamAV not available, assume safe
        }
        
        $result = clamscan_scanfile($filePath);
        return [
            'safe' => !$result['has_virus'],
            'threat' => $result['has_virus'] ? $result['virus_name'] : null
        ];
    }
}
