<?php
/**
 * quote_approve.php — 报价单审核接口
 * approve   → status='已确认'
 * unapprove → status='草稿'
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'未登录'], JSON_UNESCAPED_UNICODE); exit;
}
csrf_verify();

$user_id  = $_SESSION['user_id'];
$quote_id = intval($_POST['quote_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if (!$quote_id || !$action) {
    echo json_encode(['success'=>false,'message'=>'参数错误'], JSON_UNESCAPED_UNICODE); exit;
}

$conn = getDBConnection();
$chk = $conn->prepare("SELECT id, quote_no, status FROM quotes WHERE id=? AND user_id=?");
$chk->bind_param('ii', $quote_id, $user_id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$row) {
    echo json_encode(['success'=>false,'message'=>'报价单不存在或无权操作'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

if ($action === 'approve') {
    if ($row['status'] === '已确认') {
        echo json_encode(['success'=>false,'message'=>'已经是已确认状态'], JSON_UNESCAPED_UNICODE);
        $conn->close(); exit;
    }
    $stmt = $conn->prepare("UPDATE quotes SET status='已确认', approved=1, approved_by=?, approved_at=NOW() WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
    $stmt->bind_param("ii", $user_id, $quote_id);
    $stmt->execute();
    echo json_encode(['success'=>true,'message'=>'报价单「'.$row['quote_no'].'」已确认，可生成送货单'], JSON_UNESCAPED_UNICODE);

} elseif ($action === 'unapprove') {
    if ($row['status'] === '草稿') {
        echo json_encode(['success'=>false,'message'=>'已是草稿状态'], JSON_UNESCAPED_UNICODE);
        $conn->close(); exit;
    }
    $stmt = $conn->prepare("UPDATE quotes SET status='草稿', approved=0, approved_by=NULL, approved_at=NULL WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
    $stmt->bind_param("i", $quote_id);
    $stmt->execute();
    writeLog($conn, 'quotes', 'unapprove', '取消确认报价单 '.$row['quote_no']);
    echo json_encode(['success'=>true,'message'=>'已取消确认，退回草稿'], JSON_UNESCAPED_UNICODE);

} else {
    echo json_encode(['success'=>false,'message'=>'未知操作'], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
