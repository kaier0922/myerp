<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$quote_id = isset($_GET['id']) ? intval($_GET['id']) : 1;
$conn = getDBConnection();

// 查询报价单
$quote = $conn->query("SELECT * FROM quotes WHERE id = {$quote_id}")->fetch_assoc();

// 查询明细
$items_result = $conn->query("SELECT * FROM quote_items WHERE quote_id = {$quote_id} ORDER BY seq");
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'quote' => $quote,
    'items' => $items,
    'items_count' => count($items)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>