<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
$phone = trim($_POST['phone'] ?? '');
if (empty($phone)) {
    echo json_encode(['exists' => false, 'message' => '']);
    exit;
}
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id FROM users WHERE (phone=? OR username=?) AND status=1 LIMIT 1");
$stmt->bind_param("ss", $phone, $phone);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();
$conn->close();
echo json_encode(['exists' => $exists, 'message' => '']);
