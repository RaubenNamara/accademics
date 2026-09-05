<?php
// Run this file to generate a valid password hash and update the database
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Generated hash for 'admin123':\n";
echo $hash . "\n\n";

// Update database
try {
    $db = new PDO("mysql:host=localhost;dbname=accademics_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@school.com'");
    $stmt->execute([$hash]);
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Admin password updated successfully!\n";
        echo "You can now login with: admin@school.com / admin123\n";
    } else {
        echo "No user found. Make sure you imported the schema first.\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
