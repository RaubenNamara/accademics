<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Create leave_requests table
$sql = "
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('annual','sick','maternity','paternity','compassionate','unpaid','other') NOT NULL DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(5,2) DEFAULT 0,
    reason TEXT,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_by INT NULL,
    approved_by INT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_leave_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
)
";

try {
    $db->exec($sql);
    echo "leave_requests table created successfully\n";
    
    // Create index
    $db->exec("CREATE INDEX IF NOT EXISTS idx_leave_employee_status ON leave_requests (employee_id, status)");
    echo "Index created successfully\n";
    
    // Verify table exists
    $stmt = $db->query("SHOW TABLES LIKE 'leave_requests'");
    $result = $stmt->fetchAll();
    echo "Table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
