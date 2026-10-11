<?php
/**
 * =====================================================
 * 文件名：api_get_approved_quote.php
 * 功能：获取已审核报价单的数据
 * 描述：用于在创建送货单时加载报价单数据
 * =====================================================
 */

session_start();
require_once 'config.php';
require_once 'auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$quote_id = intval($_GET['quote_id'] ?? 0);

if ($quote_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的报价单ID']);
    exit;
}

$conn = getDBConnection();

// 获取报价单基本信息
$quote_sql = "
    SELECT q.*, COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name, c.phone, c.address
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE " . tenantWhere("q") . "
    AND q.id = ? AND q.status IN ('已确认','已成交')
";

$stmt = $conn->prepare($quote_sql);
$stmt->bind_param("i", $quote_id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();

if (!$quote) {
    echo json_encode(['success' => false, 'message' => '报价单不存在或未审核']);
    $conn->close();
    exit;
}

// 获取报价单明细
$items_sql = "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY seq ASC";
$stmt2 = $conn->prepare($items_sql);
$stmt2->bind_param("i", $quote_id);
$stmt2->execute();
$items_result = $stmt2->get_result();

$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

$conn->close();

// 返回数据
echo json_encode([
    'success' => true,
    'quote' => $quote,
    'items' => $items
]);
?>
