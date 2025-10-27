<?php
session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 只有管理员可以删除用户
if ($role !== 'admin') {
    header('Location: users.php?error=no_permission');
    exit;
}

// 获取要删除的用户ID
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 验证ID
if ($user_id === 0) {
    header('Location: users.php?error=invalid_id');
    exit;
}

// 不能删除自己
if ($user_id === $current_user_id) {
    header('Location: users.php?error=cannot_delete_self');
    exit;
}

$conn = getDBConnection();

// 查询用户信息
$stmt = $conn->prepare("SELECT username, nickname FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $stmt->close();
    $conn->close();
    header('Location: users.php?error=user_not_found');
    exit;
}

$username = $user['username'] ?? $user['nickname'] ?? 'user';

// 删除用户
$delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$delete_stmt->bind_param("i", $user_id);

if ($delete_stmt->execute()) {
    $delete_stmt->close();
    $conn->close();
    header('Location: users.php?success=deleted&username=' . urlencode($username));
    exit;
} else {
    $delete_stmt->close();
    $conn->close();
    header('Location: users.php?error=delete_failed');
    exit;
}
?>