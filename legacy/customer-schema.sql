-- Customer Table Schema for SVNTeX 2.0
CREATE TABLE IF NOT EXISTS svntex_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    referral_id VARCHAR(20),
    employee_id VARCHAR(20),
    otp_code VARCHAR(10),
    otp_verified TINYINT(1) DEFAULT 0,
    twofa_enabled TINYINT(1) DEFAULT 0,
    twofa_secret VARCHAR(32),
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
