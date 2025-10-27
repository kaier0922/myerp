<?php
$password = 'sz28960660';
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
echo "明文密码: " . $password . "\n";
echo "正确哈希值: " . $hashed_password . "\n";
?>