<?php
$hash = '$2y$10$QghkQBqEoNrZ9/w3KTB88O4axfFLdNy/jbI7vVySWDk.X8qUY5y72';

if (password_verify('admin123', $hash)) {
    echo "PASSWORD MATCH ✅";
} else {
    echo "PASSWORD FAIL ❌";
}