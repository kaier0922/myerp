<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
header('Content-Type: application/json; charset=utf-8');
$conn = getDBConnection();
$cat_id = intval($_GET['cat_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);
if ($cat_id <= 0) { echo json_encode([]); exit; }
$res = $conn->query("SELECT p.id,p.name,p.spec,p.unit,p.default_price,p.cost_price,p.stock_quantity,p.category_id,p.product_type,p.supplier_name,p.platform_data,p.platform_tags FROM products p WHERE p.is_active=1 AND p.category_id={$cat_id} AND (p.user_id=0 OR p.user_id={$user_id}) ORDER BY p.name");
$products = [];
while ($p = $res->fetch_assoc()) {
    if ($p['platform_data']) $p['platform_data'] = json_decode($p['platform_data'], true) ?: null;
    if ($p['platform_tags']) $p['platform_tags'] = json_decode($p['platform_tags'], true) ?: null;
    $products[] = $p;
}
echo json_encode($products, JSON_UNESCAPED_UNICODE);
$conn->close();
