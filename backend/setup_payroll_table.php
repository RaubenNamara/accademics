<?php
// Script to check and create payroll table if it doesn't exist
require_once 'config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if payroll table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'payroll'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "Payroll table already exists.\n";
    } else {
        echo "Payroll table does not exist. Creating it...\n";
        
        $sql = "
        CREATE TABLE payroll (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            period_year INT NOT NULL,
            period_month INT NOT NULL,
            basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
            net_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
            details JSON NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            generated_by INT NULL,
            CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            CONSTRAINT fk_payroll_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY uq_payroll_employee_period (employee_id, period_year, period_month)
        )";
        
        $db->exec($sql);
        echo "Payroll table created successfully.\n";
    }
    
    // Check if employees table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'employees'");
    $stmt->execute();
    $employeesExists = $stmt->fetch();
    
    if (!$employeesExists) {
        echo "WARNING: employees table does not exist. Please run migration 18_add_hr_staff_tables.sql\n";
    } else {
        echo "employees table exists.\n";
    }
    
    // Check if users table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    $usersExists = $stmt->fetch();
    
    if (!$usersExists) {
        echo "WARNING: users table does not exist.\n";
    } else {
        echo "users table exists.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
