<?php
/**
 * =====================================================
 * 文件名：delivery_approve.php
 * 版本：  1.4
 * 日期：  2026-03-17
 * 功能：  审核送货单，自动生成应收账款记录
 * =====================================================
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

$user_id     = $_SESSION['user_id'];
$delivery_id = intval($_POST['id'] ?? 0);

if (!$delivery_id) {
    echo json_encode(['success'=>false,'message'=>'参数错误，缺少送货单ID'], JSON_UNESCAPED_UNICODE); exit;
}

$conn = getDBConnection();

// 获取送货单（FOR UPDATE 防并发）
$conn->begin_transaction();
$stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ? AND user_id = " . intval($_SESSION['user_id']) . " FOR UPDATE");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$delivery) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>'送货单不存在'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

// 已审核则直接返回，防止重复提交
if ($delivery['approved']) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>'该送货单已审核，请勿重复操作'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

// ── 查重：用 bill_no 格式 AR-日期-ID 查，最可靠 ──
$bill_no = 'AR-' . date('Ymd', strtotime($delivery['delivery_date'])) . '-' . str_pad($delivery_id, 4, '0', STR_PAD_LEFT);
$dup_stmt = $conn->prepare("SELECT id, bill_no FROM accounts_receivable WHERE bill_no=?");
$dup_stmt->bind_param('s', $bill_no);
$dup_stmt->execute();
$dup = $dup_stmt->get_result()->fetch_assoc();
$dup_stmt->close();
if ($dup) {
    // AR 已存在，补标记审核状态
    if (!$delivery['approved']) {
        $stmt_tmp=$conn->prepare("UPDATE deliveries SET approved=1, approved_by=?, approved_at=NOW() WHERE id=?"); $stmt_tmp->bind_param("ii",$user_id,$delivery_id); $stmt_tmp->execute(); $stmt_tmp->close();
    }
    echo json_encode(['success'=>false,'message'=>'应收账款已存在（'.$dup['bill_no'].'）'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

// ── 确定 customer_id（NOT NULL，必须找到） ──
$customer_id = (intval($delivery['customer_id']) > 0) ? intval($delivery['customer_id']) : null;

if (!$customer_id && !empty($delivery['customer_name'])) {
    // 用名称反查 customers 表
    $cs = $conn->prepare("SELECT id FROM customers WHERE company_name=? OR contact_name=? LIMIT 1");
    $cs->bind_param('ss', $delivery['customer_name'], $delivery['customer_name']);
    $cs->execute();
    $cr = $cs->get_result()->fetch_assoc();
    $cs->close();
    if ($cr) $customer_id = intval($cr['id']);
}

if (!$customer_id) {
    echo json_encode([
        'success' => false,
        'message' => '找不到客户「'.$delivery['customer_name'].'」的档案（customer_id为空），请先在客户管理中建立该客户，或直接在应收账款中手动新增'
    ], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

// ── 生成 AR ──
try {
    // 标记送货单已审核
    $upd = $conn->prepare("UPDATE deliveries SET approved=1, approved_by=?, approved_at=NOW() WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
    $upd->bind_param('ii', $user_id, $delivery_id);
    $upd->execute();
    $upd->close();

    // 插入应收账款（字段完全对齐实际表结构，customer_id NOT NULL）
    $bill_date = date('Y-m-d');
    $due_date  = date('Y-m-d', strtotime('+30 days'));
    $amount    = floatval($delivery['total_amount']);
    // notes 存入送货单号，方便后续查询关联
    $notes = '送货单 ' . $delivery['delivery_no'] . ' 审核自动生成';

    $ar = $conn->prepare("INSERT INTO accounts_receivable
        (user_id, customer_id, related_type, related_id, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status, notes)
        VALUES (?, ?, 'delivery', ?, ?, ?, ?, 0, ?, ?, '未收款', ?)");
    $ar->bind_param('isissddss',
        $_SESSION['user_id'], $customer_id, $delivery_id, $bill_no, $bill_date,
        $amount, $amount, $due_date, $notes);
    $ar->execute();

    if ($ar->affected_rows < 1) {
        throw new Exception('应收账款插入失败：' . $conn->error);
    }
    $ar->close();

    // ── 写应收流水 + 更新客户累积余额 ──
    $bal_stmt = $conn->prepare("SELECT balance_delivery, balance_repair, balance_received FROM customers WHERE id=? AND user_id=?");
    $bal_stmt->bind_param('ii', $customer_id, $_SESSION['user_id']);
    $bal_stmt->execute();
    $bal = $bal_stmt->get_result()->fetch_assoc();
    $bal_stmt->close();
    $cur_delivery = floatval($bal['balance_delivery'] ?? 0);
    $cur_repair   = floatval($bal['balance_repair']   ?? 0);
    $cur_received = floatval($bal['balance_received'] ?? 0);
    $new_balance  = ($cur_delivery + $amount) + $cur_repair - $cur_received;

    $led = $conn->prepare("INSERT INTO receivable_ledger (user_id, customer_id, type, ref_id, ref_no, amount, balance_after, remark, created_by) VALUES (?,?,'delivery',?,?,?,?,?,?)");
    $led->bind_param('iiisddsi', $_SESSION['user_id'], $customer_id, $delivery_id, $delivery['delivery_no'], $amount, $new_balance, $notes, $user_id);
    $led->execute();
    $led->close();

    $upd_bal = $conn->prepare("UPDATE customers SET balance_delivery = balance_delivery + ? WHERE id=? AND user_id=?");
    $upd_bal->bind_param('dii', $amount, $customer_id, $_SESSION['user_id']);
    $upd_bal->execute();
    $upd_bal->close();

    // ── 按送货单明细减库存（用product_id精确匹配）──
    $items_stmt = $conn->prepare("SELECT product_id, product_name, quantity FROM delivery_items WHERE delivery_id=?");
    $items_stmt->bind_param('i', $delivery_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();
    foreach ($items as $item) {
        $pid = intval($item['product_id'] ?? 0);
        $qty = intval($item['quantity']);
        if ($pid <= 0 || $qty <= 0) continue; // 无product_id（服务类）跳过
        $pm = $conn->prepare("SELECT id, stock_quantity FROM products WHERE id=? AND (user_id=0 OR user_id=?) AND is_active=1");
        $pm->bind_param('ii', $pid, $_SESSION['user_id']);
        $pm->execute();
        $pr = $pm->get_result()->fetch_assoc();
        $pm->close();
        if (!$pr || $pr['stock_quantity'] <= 0) continue;
        $deduct = min($qty, intval($pr['stock_quantity']));
        $pu = $conn->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=? AND (user_id=0 OR user_id=?)");
        $pu->bind_param('iii', $deduct, $pid, $_SESSION['user_id']);
        $pu->execute();
        $pu->close();
    }

    $conn->commit();
    writeLog($conn, 'deliveries', 'approve', '确认送货单');
    echo json_encode([
        'success' => true,
        'message' => '审核成功，已生成应收账款',
        'ar_no'   => $bill_no
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>'审核失败：'.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
