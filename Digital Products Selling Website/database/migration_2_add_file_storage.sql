-- Migration: Add secure file storage tables and update products table
-- Run after schema.sql has been created

USE `digital_hub`;

-- Create product_files table to store file metadata
CREATE TABLE IF NOT EXISTS product_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  storage_key VARCHAR(512) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100),
  file_size INT NOT NULL,
  file_hash VARCHAR(64),
  storage_driver ENUM('s3', 'local') DEFAULT 'local',
  s3_bucket VARCHAR(100),
  s3_region VARCHAR(50),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  accessed_count INT DEFAULT 0,
  last_accessed TIMESTAMP NULL,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  KEY (storage_key),
  KEY (product_id, is_active)
);

-- Add file_id foreign key to downloads table (optional, for better tracking)
-- ALTER TABLE downloads ADD COLUMN product_file_id INT, ADD FOREIGN KEY (product_file_id) REFERENCES product_files(id);

-- Update products table to reference product_files instead of storing file_path directly
-- (Keep file_path columns for backward compatibility during migration, remove after testing)
-- ALTER TABLE products ADD COLUMN primary_file_id INT, ADD FOREIGN KEY (primary_file_id) REFERENCES product_files(id);
