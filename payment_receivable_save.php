<?php
/**
 * =====================================================
 * 文件名：payment_receivable_save.php
 * 版本：  1.0
 * 日期：  2026-03-17
 * 功能：  手动新建应收账款记录
 * =====================================================
 */
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'finance', 'receive', '应收款保存');
    header('Location: login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: finance.php'); exit;
}

$user_id          = $_SESSION['user_id'];
$customer_id      = intval($_POST['customer_id']        ?? 0);
$manual_name      = trim($_POST['manual_customer_name'] ?? '');
$total_amount     = floatval($_POST['total_amount']     ?? 0);
$bill_date        = trim($_POST['bill_date']            ?? '');
$due_date         = trim($_POST['due_date']             ?? '');
$notes            = trim($_POST['notes']                ?? '');

// 手动输入模式：customer_id=0，用散客占位
// accounts_receivable.customer_id NOT NULL，需要一个有效 ID
// 如果是手动输入，尝试在 customers 表里查或用默认散客 ID
if ($customer_id === 0 && $manual_name) {
    $conn = getDBConnection();
    // 先查有没有同名客户
    $cs = $conn->prepare("SELECT id FROM customers WHERE company_name=? OR contact_name=? LIMIT 1");
    $cs->bind_param('ss', $manual_name, $manual_name);
    $cs->execute();
    $cr = $cs->get_result()->fetch_assoc();
    $cs->close();
    if ($cr) {
        $customer_id = intval($cr['id']);
    } else {
        // 自动创建一条简单客户记录
        $ins = $conn->prepare("INSERT INTO customers (user_id, contact_name, created_at) VALUES (?, ?, NOW())");
        $ins->bind_param('is', (int)$_SESSION['user_id'], $manual_name);
        $ins->execute();
        $customer_id = $conn->insert_id;
        $ins->close();
    }
    $conn->close();
    // 将手动名称追加到备注
    $notes = $manual_name . ($notes ? '：' . $notes : '');
} else {
    $conn = getDBConnection();
}

// 生成唯一账单号 AR-YYYYMMDD-XXXX
if (!$customer_id || $total_amount <= 0 || !$bill_date) {
    die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">参数错误，请返回重试<br><a href="javascript:history.back()">返回</a></div>');
}
$date_str = date('Ymd', strtotime($bill_date));
$count = $conn->query("SELECT COUNT(*) as c FROM accounts_receivable WHERE bill_no LIKE 'AR-$date_str-%'")->fetch_assoc()['c'];
$bill_no = 'AR-' . $date_str . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
// 防重复
while ($conn->query("SELECT id FROM accounts_receivable WHERE bill_no='$bill_no'")->fetch_assoc()) {
    $count++;
    $bill_no = 'AR-' . $date_str . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

$due_date_sql = $due_date ? "'$due_date'" : 'NULL';
$notes_esc    = $conn->real_escape_string($notes);

$stmt = $conn->prepare("INSERT INTO accounts_receivable
    (user_id, customer_id, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status, notes)
    VALUES (?, ?, ?, ?, ?, 0, ?, $due_date_sql, '未收款', ?)");
$stmt->bind_param('iissdds', (int)$_SESSION['user_id'], $customer_id, $bill_no, $bill_date, $total_amount, $total_amount, $notes);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close();
    $conn->close();
    header('Location: finance.php?tab=receivable&msg=created');
    exit;
} else {
    $err = $conn->error;
    $stmt->close();
    $conn->close();
    die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">创建失败：'.$err.'<br><a href="javascript:history.back()">返回</a></div>');
}
?>
