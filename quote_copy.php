<?php
/**
 * =====================================================
 * 文件名：quote_copy.php
 * 功能：复制报价单
 * 描述：基于现有报价单创建新的副本，包括所有明细项
 * 版本：1.0
 * 创建日期：2025-10-31
 * =====================================================
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'quotes', 'create', '复制报价单');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$quote_id = $_GET['id'] ?? 0;

if (!$quote_id) {
    $_SESSION['error_message'] = '无效的报价单ID';
    header('Location: quotes.php');
    exit;
}

$conn = getDBConnection();

// 开启事务
$conn->begin_transaction();

try {
    // 1. 获取原报价单信息
    $quote_sql = "SELECT * FROM quotes WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
    $stmt = $conn->prepare($quote_sql);
    $stmt->bind_param("i", $quote_id);
    $stmt->execute();
    $original_quote = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$original_quote) {
        throw new Exception('报价单不存在');
    }
    
    // 2. 生成新的报价单编号
    $date_prefix = date('Ymd');
    $check_sql = "SELECT quote_no FROM quotes WHERE quote_no LIKE ? ORDER BY quote_no DESC LIMIT 1";
    $stmt = $conn->prepare($check_sql);
    $like_pattern = "QT{$date_prefix}%";
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_quote = $result->fetch_assoc();
        $last_no = intval(substr($last_quote['quote_no'], -4));
        $new_no = $last_no + 1;
    } else {
        $new_no = 1;
    }
    $stmt->close();
    
    $new_quote_no = sprintf("QT%s%04d", $date_prefix, $new_no);
    
    // 3. 插入新的报价单主记录（完全动态）
    $fields_to_copy = [];
    $values_to_copy = [];
    $types_to_copy = '';
    
    // 排除不需要复制的字段
    $exclude_fields = ['id', 'created_at', 'updated_at'];
    
    foreach ($original_quote as $field => $value) {
        if (!in_array($field, $exclude_fields)) {
            $fields_to_copy[] = $field;
            
            // 特殊字段处理
            if ($field == 'quote_no') {
                $values_to_copy[] = $new_quote_no;
            } elseif ($field == 'quote_date') {
                $values_to_copy[] = date('Y-m-d');
            } elseif ($field == 'status') {
                $values_to_copy[] = '草稿';
            } else {
                $values_to_copy[] = $value;
            }
            
            // 确定数据类型
            if (in_array($field, ['customer_id', 'valid_days', 'id'])) {
                $types_to_copy .= 'i';
            } elseif (in_array($field, ['discount', 'final_amount'])) {
                $types_to_copy .= 'd';
            } else {
                $types_to_copy .= 's';
            }
        }
    }
    
    $field_list = implode(', ', $fields_to_copy);
    $placeholders = str_repeat('?, ', count($fields_to_copy) - 1) . '?';
    
    $insert_quote_sql = "INSERT INTO quotes ($field_list) VALUES ($placeholders)";
    $stmt = $conn->prepare($insert_quote_sql);
    $stmt->bind_param($types_to_copy, ...$values_to_copy);
    
    if (!$stmt->execute()) {
        throw new Exception('创建新报价单失败: ' . $stmt->error);
    }
    
    $new_quote_id = $conn->insert_id;
    $stmt->close();
    
    // 4. 复制报价单明细项
    $items_sql = "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY seq ASC";
    $stmt = $conn->prepare($items_sql);
    $stmt->bind_param("i", $quote_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (count($items) > 0) {
        // 动态构建插入语句（根据实际存在的字段）
        $sample_item = $items[0];
        $fields = array_keys($sample_item);
        
        // 排除不需要复制的字段
        $exclude_fields = ['id', 'created_at', 'updated_at'];
        $insert_fields = array_diff($fields, $exclude_fields);
        
        $field_list = implode(', ', $insert_fields);
        $placeholders = str_repeat('?, ', count($insert_fields) - 1) . '?';
        
        $insert_item_sql = "INSERT INTO quote_items ($field_list) VALUES ($placeholders)";
        $stmt = $conn->prepare($insert_item_sql);
        
        foreach ($items as $item) {
            // 更新 quote_id 为新的ID
            $item['quote_id'] = $new_quote_id;
            
            // 准备绑定的值
            $bind_values = [];
            $bind_types = '';
            
            foreach ($insert_fields as $field) {
                $bind_values[] = $item[$field];
                
                // 确定类型
                if (in_array($field, ['id', 'quote_id', 'seq', 'quantity'])) {
                    $bind_types .= 'i';
                } elseif (in_array($field, ['price', 'subtotal'])) {
                    $bind_types .= 'd';
                } else {
                    $bind_types .= 's';
                }
            }
            
            $stmt->bind_param($bind_types, ...$bind_values);
            
            if (!$stmt->execute()) {
                throw new Exception('复制明细项失败: ' . $stmt->error);
            }
        }
        
        $stmt->close();
    }
    
    // 5. 提交事务
    $conn->commit();
    
    // 设置成功消息
    $_SESSION['success_message'] = "✅ 复制成功！新报价单号：{$new_quote_no}，已自动保存为草稿。";
    
    // 重定向到列表页（让用户能看到新建的报价单）
    header("Location: quotes.php");
    exit;
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    
    $_SESSION['error_message'] = '复制失败: ' . $e->getMessage();
    header('Location: quotes.php');
    exit;
    
} finally {
    $conn->close();
}
?>
