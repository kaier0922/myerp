<?php
/**
 * =====================================================
 * 文件名：quote_delete.php
 * 功能：真正删除报价单（根据实际数据库结构）
 * 描述：删除报价单及其关联的所有数据
 * 
 * 删除顺序：
 * 1. quote_items (明细)
 * 2. quote_attachments (附件)
 * 3. quotes_history (历史)
 * 4. quotes (主表)
 * =====================================================
 */

session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();

require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// POST 请求做 CSRF 验证；GET 保持兼容（旧链接）
if ($isPost) {
    header('Content-Type: application/json; charset=utf-8');
    csrf_verify();
}

// 取 ID：POST body 或 GET 参数
$quote_id = $isPost ? intval($_POST['id'] ?? 0) : intval($_GET['id'] ?? 0);
if (!$quote_id) {
    if ($isPost) { echo json_encode(['success'=>false,'message'=>'缺少报价单ID'], JSON_UNESCAPED_UNICODE); exit; }
    $_SESSION['error'] = '缺少报价单ID';
    header('Location: quotes.php'); exit;
}
$conn = getDBConnection();

try {
    // 开始事务
    $conn->begin_transaction();
    
    // 1. 检查报价单是否存在
    $check_stmt = $conn->prepare("SELECT id, quote_no, status FROM quotes WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
    $check_stmt->bind_param("i", $quote_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('报价单不存在');
    }
    
    $quote = $result->fetch_assoc();
    $check_stmt->close();
    
    // 2. 可选：检查业务规则
    // 例如：已成交的报价单不允许删除
    /*
    if ($quote['status'] === '已成交') {
        throw new Exception('已成交的报价单不能删除，请联系管理员');
    }
    */
    
    // 3. 删除报价单明细 (quote_items)
    $delete_items_stmt = $conn->prepare("DELETE FROM quote_items WHERE quote_id = ?");
    $delete_items_stmt->bind_param("i", $quote_id);
    $deleted_items = 0;
    if ($delete_items_stmt->execute()) {
        $deleted_items = $delete_items_stmt->affected_rows;
    }
    $delete_items_stmt->close();
    
    // 4. 删除报价单附件 (quote_attachments)
    // 先获取附件路径，删除物理文件
    $attachments = $conn->query("SELECT id, file_path FROM quote_attachments WHERE quote_id = $quote_id");
    $deleted_files = 0;
    if ($attachments) {
        while ($att = $attachments->fetch_assoc()) {
            $file_path = $att['file_path'];
            // 如果文件路径存在且文件存在，则删除
            if (!empty($file_path) && file_exists($file_path)) {
                if (@unlink($file_path)) {
                    $deleted_files++;
                }
            }
        }
    }
    
    // 删除附件记录
    $delete_attachments_stmt = $conn->prepare("DELETE FROM quote_attachments WHERE quote_id = ?");
    $delete_attachments_stmt->bind_param("i", $quote_id);
    $deleted_attachments = 0;
    if ($delete_attachments_stmt->execute()) {
        $deleted_attachments = $delete_attachments_stmt->affected_rows;
    }
    $delete_attachments_stmt->close();
    
    // 5. 删除历史记录 (quotes_history) - 如果存在
    $check_history_table = $conn->query("SHOW TABLES LIKE 'quotes_history'");
    if ($check_history_table && $check_history_table->num_rows > 0) {
        $delete_history_stmt = $conn->prepare("DELETE FROM quotes_history WHERE quote_id = ?");
        $delete_history_stmt->bind_param("i", $quote_id);
        $delete_history_stmt->execute();
        $delete_history_stmt->close();
    }
    
    // 6. 删除报价单主表 (quotes)
    $delete_quote_stmt = $conn->prepare("DELETE FROM quotes WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
    $delete_quote_stmt->bind_param("i", $quote_id);
    
    if (!$delete_quote_stmt->execute()) {
        throw new Exception('删除报价单失败: ' . $delete_quote_stmt->error);
    }
    
    $delete_quote_stmt->close();
    
    // 提交事务
    $conn->commit();
    
    // 构建成功消息
    $message = "报价单 {$quote['quote_no']} 已成功删除";
    if ($deleted_items > 0) {
        $message .= "（包含 $deleted_items 条明细";
    }
    if ($deleted_attachments > 0) {
        $message .= "，$deleted_attachments 个附件";
    }
    if ($deleted_files > 0) {
        $message .= "，已删除 $deleted_files 个物理文件";
    }
    if ($deleted_items > 0 || $deleted_attachments > 0) {
        $message .= "）";
    }
    
    if ($isPost) {
        $conn->close();
        echo json_encode(['success'=>true,'message'=>$message], JSON_UNESCAPED_UNICODE); exit;
    }
    $_SESSION['success'] = $message;

} catch (Exception $e) {
    $conn->rollback();
    if ($isPost) {
        $conn->close();
        echo json_encode(['success'=>false,'message'=>'删除失败: '.$e->getMessage()], JSON_UNESCAPED_UNICODE); exit;
    }
    $_SESSION['error'] = '删除失败: ' . $e->getMessage();
}

$conn->close();
header('Location: quotes.php');
exit;
