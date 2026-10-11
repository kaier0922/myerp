<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 顶层异常捕获，确保任何错误都返回 JSON 而非空响应
set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$user_id     = $_SESSION['user_id'];
$delivery_id = intval($_POST['id'] ?? 0);

if (!$delivery_id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$conn = getDBConnection();

// ── 获取原送货单 ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$delivery) {
    echo json_encode(['success' => false, 'message' => '送货单不存在']);
    exit;
}

// ── 获取原送货明细 ────────────────────────────────────────
$items_stmt = $conn->prepare("SELECT * FROM delivery_items WHERE delivery_id = ?");
$items_stmt->bind_param('i', $delivery_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$conn->begin_transaction();

try {
    // ── 生成新送货单号 ────────────────────────────────────
    $prefix = 'SH' . date('Ymd');
    $cnt_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM deliveries WHERE delivery_no LIKE ?");
    $like = $prefix . '%';
    $cnt_stmt->bind_param('s', $like);
    $cnt_stmt->execute();
    $seq = $cnt_stmt->get_result()->fetch_assoc()['cnt'] + 1;
    $cnt_stmt->close();
    $new_no = $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

    // ── 提取变量（处理 NULL，bind_param 需要变量引用） ───
    // nullable int 字段：NULL 转 0 避免 "No data supplied" 错误
    $v_new_no          = $new_no;
    $v_quote_id        = isset($delivery['quote_id'])    ? intval($delivery['quote_id'])    : 0;
    $v_customer_id     = isset($delivery['customer_id']) ? intval($delivery['customer_id']) : 0;
    $v_customer_name   = (string)($delivery['customer_name']   ?? '');
    $v_contact_name    = (string)($delivery['contact_name']    ?? '');
    $v_contact_phone   = (string)($delivery['contact_phone']   ?? '');
    $v_delivery_address= (string)($delivery['delivery_address']?? '');
    $v_delivery_date   = date('Y-m-d'); // 复制时日期用当天
    $v_delivery_time   = (string)($delivery['delivery_time']   ?? '');
    $v_delivery_person = (string)($delivery['delivery_person'] ?? '');
    $v_vehicle_no      = (string)($delivery['vehicle_no']      ?? '');
    $v_goods_amount    = floatval($delivery['goods_amount']    ?? 0);
    $v_freight_fee     = floatval($delivery['freight_fee']     ?? 0);
    $v_total_amount    = floatval($delivery['total_amount']    ?? 0);
    $v_payment_method  = (string)($delivery['payment_method'] ?? '');
    $v_cod             = intval($delivery['collect_on_delivery'] ?? 0);
    $v_notes           = (string)($delivery['notes']           ?? '');
    $v_user_id         = intval($user_id);

    // ── 插入新送货单 ──────────────────────────────────────
    // 18 个 ? → 格式串 18 位：siissssssssdddsisi
    // s:new_no  i:quote_id  i:customer_id
    // s:customer_name  s:contact_name  s:contact_phone
    // s:delivery_address  s:delivery_date  s:delivery_time
    // s:delivery_person  s:vehicle_no
    // d:goods_amount  d:freight_fee  d:total_amount
    // s:payment_method  i:collect_on_delivery  s:notes  i:user_id
    $insert_sql = "INSERT INTO deliveries
        (user_id, delivery_no, quote_id, customer_id, customer_name, contact_name, contact_phone,
         delivery_address, delivery_date, delivery_time, delivery_person, vehicle_no,
         status, goods_amount, freight_fee, total_amount, payment_method,
         paid_amount, payment_status, collect_on_delivery, notes, created_by, created_at)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, 0, 'unpaid', ?, ?, ?, NOW())";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param(
        'isiissssssssdddsisi',
        $v_user_id,
        $v_new_no,
        $v_quote_id,
        $v_customer_id,
        $v_customer_name,
        $v_contact_name,
        $v_contact_phone,
        $v_delivery_address,
        $v_delivery_date,
        $v_delivery_time,
        $v_delivery_person,
        $v_vehicle_no,
        $v_goods_amount,
        $v_freight_fee,
        $v_total_amount,
        $v_payment_method,
        $v_cod,
        $v_notes,
        $v_user_id
    );
    $stmt->execute();
    $new_delivery_id = $conn->insert_id;
    $stmt->close();

    // ── 复制送货明细 ──────────────────────────────────────
    // 10 个 ? → 格式串 10 位：issisddiss
    // i:delivery_id  s:product_name  s:product_spec
    // i:quantity  s:unit  d:unit_price  d:subtotal
    // i:actual_quantity  s:serial_number  s:notes
    if (!empty($items)) {
        $item_sql = "INSERT INTO delivery_items
            (delivery_id, product_id, product_name, product_spec, quantity, unit,
             unit_price, subtotal, actual_quantity, serial_number, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $item_stmt = $conn->prepare($item_sql);

        foreach ($items as $item) {
            $iv_delivery_id  = $new_delivery_id;
            $iv_product_id   = intval($item['product_id'] ?? 0);
            $iv_product_name = (string)($item['product_name'] ?? '');
            $iv_product_spec = (string)($item['product_spec'] ?? '');
            $iv_quantity     = intval($item['quantity']   ?? 1);
            $iv_unit         = (string)($item['unit']     ?? '件');
            $iv_unit_price   = floatval($item['unit_price']  ?? 0);
            $iv_subtotal     = floatval($item['subtotal']    ?? 0);
            $iv_actual_qty   = null;
            $iv_serial       = (string)($item['serial_number'] ?? '');
            $iv_notes        = (string)($item['notes']         ?? '');
            $item_stmt->bind_param(
                'iissisddiss',
                $iv_delivery_id,
                $iv_product_id,
                $iv_product_name,
                $iv_product_spec,
                $iv_quantity,
                $iv_unit,
                $iv_unit_price,
                $iv_subtotal,
                $iv_actual_qty,
                $iv_serial,
                $iv_notes
            );
            $item_stmt->execute();
        }
        $item_stmt->close();
    }

    $conn->commit();

    writeLog($conn, 'deliveries', 'create', '复制送货单');
    echo json_encode([
        'success' => true,
        'message' => '复制成功',
        'new_id'  => $new_delivery_id,
        'new_no'  => $new_no
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => '复制失败：' . $e->getMessage()]);
}

$conn->close();
