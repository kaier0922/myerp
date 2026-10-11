<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$type = $_GET['type'] ?? 'sales';
$allowed = ['sales','construction','outsource','rental','software','loan'];
if (!in_array($type, $allowed)) {
    echo json_encode(['error'=>'invalid type']);
    exit;
}

$conn = getDBConnection();
$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM contract_templates WHERE user_id=? AND contract_type=?");
$stmt->bind_param("is", $uid, $type);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$conn->close();

echo json_encode($row ?: (object)[]);
