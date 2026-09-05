<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$email = 'smacon@gmail.com';
$newPassword = 'admin123';
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE email = :email");
$stmt->execute([
    ':password_hash' => $passwordHash,
    ':email' => $email
]);

if ($stmt->rowCount() > 0) {
    echo "Password reset successfully for $email\n";
    echo "New password: admin123\n";
} else {
    echo "User not found or password already set\n";
}
?>
