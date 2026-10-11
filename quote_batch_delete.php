<?php
/**
 * =====================================================
 * 文件名：quote_batch_delete.php
 * 功能：批量删除报价单（根据实际数据库结构）
 * 描述：一次性删除多个选中的报价单及其关联数据
 * 
 * 删除顺序（每个报价单）：
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

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

csrf_verify();

// 获取要删除的ID列表
$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => '请选择要删除的报价单']);
    exit;
}

// 验证所有ID都是数字
foreach ($ids as $id) {
    if (!is_numeric($id)) {
        echo json_encode(['success' => false, 'message' => '无效的报价单ID']);
        exit;
    }
}

$conn = getDBConnection();
$success_count = 0;
$fail_count = 0;
$errors = [];
$total_items_deleted = 0;
$total_attachments_deleted = 0;
$total_files_deleted = 0;

try {
    // 开始事务
    $conn->begin_transaction();
    
    // 检查 quotes_history 表是否存在
    $has_history_table = false;
    $check_history = $conn->query("SHOW TABLES LIKE 'quotes_history'");
    if ($check_history && $check_history->num_rows > 0) {
        $has_history_table = true;
    }
    
    foreach ($ids as $quote_id) {
        $quote_id = intval($quote_id);
        
        try {
            // 1. 检查报价单是否存在
            $check_stmt = $conn->prepare("SELECT quote_no, status FROM quotes WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
            $check_stmt->bind_param("i", $quote_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                $errors[] = "报价单ID $quote_id 不存在";
                $fail_count++;
                $check_stmt->close();
                continue;
            }
            
            $quote = $result->fetch_assoc();
            $quote_no = $quote['quote_no'];
            $check_stmt->close();
            
            // 2. 可选：检查业务规则
            /*
            if ($quote['status'] === '已成交') {
                $errors[] = "报价单 {$quote_no} 已成交，不能删除";
                $fail_count++;
                continue;
            }
            */
            
            // 3. 删除报价单明细
            $delete_items_stmt = $conn->prepare("DELETE FROM quote_items WHERE quote_id = ?");
            $delete_items_stmt->bind_param("i", $quote_id);
            if ($delete_items_stmt->execute()) {
                $total_items_deleted += $delete_items_stmt->affected_rows;
            }
            $delete_items_stmt->close();
            
            // 4. 删除附件（物理文件和数据库记录）
            $attachments = $conn->query("SELECT id, file_path FROM quote_attachments WHERE quote_id = $quote_id");
            if ($attachments) {
                while ($att = $attachments->fetch_assoc()) {
                    $file_path = $att['file_path'];
                    if (!empty($file_path) && file_exists($file_path)) {
                        if (@unlink($file_path)) {
                            $total_files_deleted++;
                        }
                    }
                }
            }
            
            $delete_attachments_stmt = $conn->prepare("DELETE FROM quote_attachments WHERE quote_id = ?");
            $delete_attachments_stmt->bind_param("i", $quote_id);
            if ($delete_attachments_stmt->execute()) {
                $total_attachments_deleted += $delete_attachments_stmt->affected_rows;
            }
            $delete_attachments_stmt->close();
            
            // 5. 删除历史记录（如果表存在）
            if ($has_history_table) {
                $delete_history_stmt = $conn->prepare("DELETE FROM quotes_history WHERE quote_id = ?");
                $delete_history_stmt->bind_param("i", $quote_id);
                $delete_history_stmt->execute();
                $delete_history_stmt->close();
            }
            
            // 6. 删除主表
            $delete_quote_stmt = $conn->prepare("DELETE FROM quotes WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
            $delete_quote_stmt->bind_param("i", $quote_id);
            
            if ($delete_quote_stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "删除报价单 {$quote_no} 失败: " . $delete_quote_stmt->error;
                $fail_count++;
            }
            
            $delete_quote_stmt->close();
            
        } catch (Exception $e) {
            $errors[] = "删除报价单ID $quote_id 时出错: " . $e->getMessage();
            $fail_count++;
        }
    }
    
    // 提交事务
    $conn->commit();
    
    // 构建详细消息
    $message = "成功删除 $success_count 个报价单";
    
    $details = [];
    if ($total_items_deleted > 0) {
        $details[] = "$total_items_deleted 条明细";
    }
    if ($total_attachments_deleted > 0) {
        $details[] = "$total_attachments_deleted 个附件记录";
    }
    if ($total_files_deleted > 0) {
        $details[] = "$total_files_deleted 个物理文件";
    }
    
    if (!empty($details)) {
        $message .= "（包含 " . implode("，", $details) . "）";
    }
    
    if ($fail_count > 0) {
        $message .= "，失败 $fail_count 个";
    }
    
    writeLog($conn, 'quotes', 'delete', '批量删除报价单');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'success_count' => $success_count,
        'fail_count' => $fail_count,
        'items_deleted' => $total_items_deleted,
        'attachments_deleted' => $total_attachments_deleted,
        'files_deleted' => $total_files_deleted,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => '批量删除失败: ' . $e->getMessage()
    ]);
}

$conn->close();
