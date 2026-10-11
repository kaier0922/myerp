<?php
/**
 * 文件名: product_delete.php
 * 功能: 软删除产品（设置 is_active=0）
 * 返回 JSON
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success'=>false,'message'=>'请先登录'], JSON_UNESCAPED_UNICODE));
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    die(json_encode(['success'=>false,'message'=>'无效的产品ID'], JSON_UNESCAPED_UNICODE));
}

$conn = getDBConnection();
// 权限：超管可删任意产品（含公共产品 user_id=0）；普通用户只能删自己的私有产品
$_uid     = (int)($_SESSION['user_id'] ?? 0);
$_issuper = (($_SESSION['role'] ?? '') === 'super_admin') || is_admin();
if ($_issuper) {
    $ck = $conn->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1");
    $ck->bind_param('i', $id);
} else {
    // 普通用户不能删 user_id=0 的公共产品
    $ck = $conn->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1 AND user_id = ? AND user_id != 0");
    $ck->bind_param('ii', $id, $_uid);
}
$ck->execute();
$row = $ck->get_result()->fetch_assoc();
$ck->close();
if (!$row) {
    $conn->close();
    die(json_encode(['success'=>false,'message'=>'权限不足，公共产品库只有超级管理员可以删除'], JSON_UNESCAPED_UNICODE));
}
// 软删除：设置 is_active = 0
if ($_issuper) {
    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $_uid);
}
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if ($ok) {
    writeLog($conn, 'products', 'delete', '删除产品');
    echo json_encode(['success'=>true,'message'=>'产品「'.$row['name'].'」已删除'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success'=>false,'message'=>'删除失败，请重试'], JSON_UNESCAPED_UNICODE);
}
?>
