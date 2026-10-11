<?php
/**
 * =====================================================
 * 文件名：payment_receive_save.php
 * 版本：  1.0
 * 日期：  2026-03-17
 * 功能：  为应收账款登记一笔收款记录
 * =====================================================
 */
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'finance', 'receive', '保存收款');
    header('Location: login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: finance.php'); exit;
}

$user_id          = $_SESSION['user_id'];
$ar_id            = intval($_POST['ar_id']            ?? 0);
$amount           = floatval($_POST['amount']          ?? 0);
$payment_date     = trim($_POST['payment_date']        ?? date('Y-m-d'));
$payment_method   = trim($_POST['payment_method']      ?? '');
$generate_receipt = intval($_POST['generate_receipt']  ?? 0);
$notes            = trim($_POST['notes']               ?? '');

if (!$ar_id || $amount <= 0) {
    die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">参数错误<br><a href="javascript:history.back()">返回</a></div>');
}

$conn = getDBConnection();

// 取应收账款
$stmt = $conn->prepare("SELECT * FROM accounts_receivable WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
$stmt->bind_param('i', $ar_id);
$stmt->execute();
$ar = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ar) {
    $conn->close();
    die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">找不到应收账款记录<br><a href="finance.php">返回</a></div>');
}

if ($amount > floatval($ar['outstanding_amount'])) {
    $conn->close();
    die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">收款金额超过未收金额（¥'.number_format($ar['outstanding_amount'],2).'）<br><a href="javascript:history.back()">返回</a></div>');
}

// 生成收款记录编号
$record_no = 'PR-' . date('Ymd') . '-' . str_pad($ar_id, 4, '0', STR_PAD_LEFT);
$dupStmt = $conn->prepare("SELECT id FROM payment_records WHERE record_no=?");
$dupStmt->bind_param('s', $record_no); $dupStmt->execute();
if ($dupStmt->get_result()->fetch_assoc()) $record_no = 'PR-' . date('YmdHis') . '-' . $ar_id;
$dupStmt->close();

$conn->begin_transaction();
try {
    // 1. 插入收款记录
    $pm  = $conn->real_escape_string($payment_method);
    $nt  = $conn->real_escape_string($notes);
    $stmt = $conn->prepare("INSERT INTO payment_records
        (user_id, record_no, related_type, related_id, payment_type, amount, payment_method,
         payment_date, generate_receipt, operator_id, notes)
        VALUES (?, ?, '应收账款', ?, '收款', ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isidssiis',
        $_SESSION['user_id'], $record_no, $ar_id, $amount, $payment_method,
        $payment_date, $generate_receipt, $user_id, $notes);
    $stmt->execute();
    $payment_record_id = $conn->insert_id;
    $stmt->close();

    // 2. 更新应收账款余额和状态
    $new_paid        = floatval($ar['paid_amount']) + $amount;
    $new_outstanding = floatval($ar['total_amount']) - $new_paid;
    $new_status      = $new_outstanding <= 0 ? '已收款' : '部分收款';

    $stmt = $conn->prepare("UPDATE accounts_receivable SET
        paid_amount=?, outstanding_amount=?, status=? WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
    $stmt->bind_param('ddsi', $new_paid, $new_outstanding, $new_status, $ar_id);
    $stmt->execute();
    $stmt->close();

    
    // 回写维修单付款状态
    if ($ar['related_type'] === 'repair' && intval($ar['related_id']) > 0) {
        $repair_id_from_no = intval($ar['related_id']);
        if ($repair_id_from_no > 0) {
            $ro_q = $conn->prepare("SELECT total_fee, paid_amount FROM repair_orders WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
            $ro_q->bind_param('i', $repair_id_from_no); $ro_q->execute();
            $ro_data = $ro_q->get_result()->fetch_assoc(); $ro_q->close();
            if ($ro_data) {
                $new_repair_paid = floatval($ro_data['paid_amount']) + $amount;
                $repair_pay_status = $new_repair_paid >= floatval($ro_data['total_fee']) ? 'paid' : 'partial';
                $upd_repair = $conn->prepare("UPDATE repair_orders SET paid_amount=?, payment_status=? WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
                $upd_repair->bind_param('dsi', $new_repair_paid, $repair_pay_status, $repair_id_from_no);
                $upd_repair->execute(); $upd_repair->close();
            }
        }
    }
    // 回写送货单付款状态
    if ($ar['related_type'] === 'delivery' && intval($ar['related_id']) > 0) {
        $delivery_id = intval($ar['related_id']);
        $dq = $conn->prepare("SELECT total_amount, paid_amount FROM deliveries WHERE id=?");
        $dq->bind_param('i', $delivery_id); $dq->execute();
        $ddata = $dq->get_result()->fetch_assoc(); $dq->close();
        if ($ddata) {
            $new_delivery_paid = floatval($ddata['paid_amount']) + $amount;
            $delivery_pay_status = $new_delivery_paid >= floatval($ddata['total_amount']) ? 'paid' : 'partial';
            $upd_delivery = $conn->prepare("UPDATE deliveries SET paid_amount=?, payment_status=? WHERE id=?");
            $upd_delivery->bind_param('dsi', $new_delivery_paid, $delivery_pay_status, $delivery_id);
            $upd_delivery->execute(); $upd_delivery->close();
        }
    }

    // 回写送货单付款状态
    if ($ar['related_type'] === 'delivery' && intval($ar['related_id']) > 0) {
        $delivery_id = intval($ar['related_id']);
        $dq = $conn->prepare("SELECT total_amount, paid_amount FROM deliveries WHERE id=?");
        $dq->bind_param('i', $delivery_id); $dq->execute();
        $ddata = $dq->get_result()->fetch_assoc(); $dq->close();
        if ($ddata) {
            $new_delivery_paid = floatval($ddata['paid_amount']) + $amount;
            $delivery_pay_status = $new_delivery_paid >= floatval($ddata['total_amount']) ? 'paid' : 'partial';
            $upd_delivery = $conn->prepare("UPDATE deliveries SET paid_amount=?, payment_status=? WHERE id=?");
            $upd_delivery->bind_param('dsi', $new_delivery_paid, $delivery_pay_status, $delivery_id);
            $upd_delivery->execute(); $upd_delivery->close();
        }
    }

// 3. 如需生成收据，直接INSERT
    if ($generate_receipt) {
        $datePart = date('Ymd');
        $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM receipts WHERE DATE(created_at)=CURDATE()")->fetch_assoc();
        $seq = str_pad((int)($cntRes['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
        $receipt_no = "R{$datePart}-{$seq}";
        $uid = (int)$_SESSION['user_id'];
        $walkin_name = $ar['customer_id'] ? '' : '散客';
        $rst = $conn->prepare("INSERT INTO receipts (receipt_no, customer_id, walkin_name, related_type, related_id, subtotal, tax_amount, total_amount, paid_amount, payment_method, note, created_by, user_id) VALUES (?,?,?,'receivable',?,?,0,?,?,?,?,?,?)");
        $rst->bind_param('sisidddssii', $receipt_no, $ar['customer_id'], $walkin_name, $ar_id, $amount, $amount, $amount, $payment_method, $notes, $uid, $uid);
        $rst->execute();
        $rst->close();
    }
    $conn->commit();
    $conn->close();
    header('Location: finance.php?tab=receivable&msg=paid');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">保存失败：'.$e->getMessage().'<br><a href="javascript:history.back()">返回</a></div>');
}
?>
