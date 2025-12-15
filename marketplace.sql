-- database/schema.sql
CREATE DATABASE IF NOT EXISTS template_marketplace;
USE template_marketplace;

-- Admin table with hardcoded default credentials
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (username: admin, password: admin)
-- Password is hashed using password_hash('admin', PASSWORD_DEFAULT)
INSERT INTO admins (username, password_hash, email) VALUES 
('admin', '$2y$10$r8rO2Jk9zYwLm7Vq5WQh7eB6n8cT9uX0yZ1A2B3C4D5E6F7G8H9I0J1K2L', 'admin@marketplace.com');

-- Platform settings
CREATE TABLE platform_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    platform_name VARCHAR(100) DEFAULT 'PromptTemplates',
    logo_url VARCHAR(255),
    contact_phone VARCHAR(20) DEFAULT '+254 700 000 000',
    contact_email VARCHAR(100) DEFAULT 'support@prompttemplates.com',
    tiktok_url VARCHAR(255),
    facebook_url VARCHAR(255),
    whatsapp_number VARCHAR(20),
    instagram_url VARCHAR(255),
    mpesa_business_shortcode VARCHAR(20),
    mpesa_passkey VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO platform_settings (platform_name) VALUES ('PromptTemplates');

-- Website templates
CREATE TABLE templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    background_url VARCHAR(255),
    zip_file_url VARCHAR(255) NOT NULL,
    preview_html TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    downloads_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users (customers)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Purchases
CREATE TABLE purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    mpesa_receipt VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    download_url VARCHAR(255),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Purchase items
CREATE TABLE purchase_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_id INT,
    template_id INT,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id)
);

-- Failed payments
CREATE TABLE failed_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(50),
    phone_number VARCHAR(20),
    amount DECIMAL(10,2),
    error_message TEXT,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);