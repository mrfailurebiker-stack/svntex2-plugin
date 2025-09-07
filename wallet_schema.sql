-- wallet_schema.sql
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    password VARCHAR(255)
);
CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    topup_balance DECIMAL(10,2) DEFAULT 0,
    income_balance DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    type ENUM('topup','income','rb','pb'),
    amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
CREATE TABLE bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    referral_bonus DECIMAL(10,2) DEFAULT 0,
    partnership_bonus DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
