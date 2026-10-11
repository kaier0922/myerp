<?php
/**
 * =====================================================
 * 文件名：receipt_delete.php
 * 功能：删除收据（真删除，含明细级联）
 * 日期：2025-11-12
 * =====================================================
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
if (!function_exists('getDBConnection')) { die('❌ config.php 未提供 getDBConnection()'); }
$conn = getDBConnection();
if (!($conn instanceof mysqli)) { die('❌ 数据库连接未建立：请检查 config.php::getDBConnection() 返回 mysqli 连接'); }
?>
<?php
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: receipts.php'); exit; }

$ck = $conn->prepare("SELECT id FROM receipts WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
if (!$ck) { die('❌ 预处理失败：' . $conn->error); }
$ck->bind_param('i', $id);
$ck->execute();
$row = $ck->get_result()->fetch_assoc();
if (!$row) { header('Location: receipts.php'); exit; }

$conn->begin_transaction();
try {
    $stmt_tmp=$conn->prepare("DELETE FROM receipt_items WHERE receipt_id=?"); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $stmt_tmp->close();
    $del = $conn->prepare("DELETE FROM receipts WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
    if (!$del) { throw new Exception('预处理失败：' . $conn->error); }
    $del->bind_param('i', $id);
    if (!$del->execute()) { throw new Exception('删除失败：' . $del->error); }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die('❌ 删除失败：' . $e->getMessage());
}

writeLog($conn, 'receipts', 'delete', '删除收据');
header('Location: receipts.php?msg=deleted');
exit;
