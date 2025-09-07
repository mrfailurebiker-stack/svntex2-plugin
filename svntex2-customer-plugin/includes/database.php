<?php
// SVNTeX 2.0 Database Schema Creation
function svntex2_create_tables() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();

    $tables = [];
    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(20) UNIQUE NOT NULL,
        mobile VARCHAR(15) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        referral_id VARCHAR(20),
        employee_id VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_wallets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(20) NOT NULL,
        topup_balance DECIMAL(10,2) DEFAULT 0,
        income_balance DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY (customer_id) REFERENCES {$wpdb->prefix}svntex_customers(customer_id)
    ) $charset_collate;";
    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_kyc (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(20) NOT NULL,
        aadhaar_number VARCHAR(20),
        pan_number VARCHAR(20),
        bank_name VARCHAR(100),
        bank_account VARCHAR(30),
        ifsc_code VARCHAR(20),
        upi_id VARCHAR(50),
        aadhaar_front VARCHAR(255),
        aadhaar_back VARCHAR(255),
        pan_card VARCHAR(255),
        bank_passbook VARCHAR(255),
        status VARCHAR(20) DEFAULT 'pending',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES {$wpdb->prefix}svntex_customers(customer_id)
    ) $charset_collate;";
    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(20) NOT NULL,
        type ENUM('topup','purchase','pb','rb','withdrawal','adjustment'),
        amount DECIMAL(10,2),
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES {$wpdb->prefix}svntex_customers(customer_id)
    ) $charset_collate;";
    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(100),
        email VARCHAR(255) UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'svntex2_create_tables');
