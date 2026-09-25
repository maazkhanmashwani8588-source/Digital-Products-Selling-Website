USE `digital_hub`;

INSERT INTO categories (name, slug) VALUES ('Ebooks','ebooks'), ('Templates','templates'), ('Courses','courses')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku,title,slug,short_description,description,thumbnail,preview_images,file_path,file_size,file_type,file_hash,version,price,sale_price,category_id,tags,featured,status,download_limit)
VALUES
('SKU-EBOOK-001','Learn PHP Quickly','learn-php-quickly','A concise PHP ebook to get you started.','Full description for Learn PHP Quickly.','/frontend/assets/placeholder.png','["/frontend/assets/placeholder.png"]','/downloads/learn-php.zip',204800,'application/zip','abc123','1.0',19.99,9.99,1,'php,ebook',1,'active',100),
('SKU-TPL-001','Modern Landing Template','modern-landing','Responsive landing page template.','Full template description.','/frontend/assets/placeholder.png','["/frontend/assets/placeholder.png"]','/downloads/modern-landing.zip',512000,'application/zip','def456','2.3',29.99,NULL,2,'template,html',1,'active',NULL),
('SKU-COURSE-001','Productivity Course','productivity-course','A short course on productivity.','Full course description.','/frontend/assets/placeholder.png','["/frontend/assets/placeholder.png"]','/downloads/productivity-course.zip',1048576,'application/zip','ghi789','1.2',49.99,39.99,3,'course,video',0,'active',NULL)
ON DUPLICATE KEY UPDATE title = VALUES(title);
