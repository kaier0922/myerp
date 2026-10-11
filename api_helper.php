<?php
require_once 'config.php';
header('Content-Type: application/json');
$conn = getDBConnection();

$action = $_GET['action'] ?? '';
if (empty($action)) {
    echo json_encode(['error' => '缺少action参数']);
    exit;
}

if ($action === 'get_categories') {
    // 这里要确保 product_type 是有效的
    $type = $_GET['type'] ?? '';
    if (empty($type)) {
        echo json_encode(['error' => '缺少产品大类类型']);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE product_type = ? ORDER BY name ASC");
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $data = [];
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);  // 返回数据
    } else {
        echo json_encode(['error' => '无法获取分类数据']);
    }
} elseif ($action === 'get_products') {
    // 获取指定小类下的产品型号
    $cat_id = intval($_GET['cat_id'] ?? 0);
    if ($cat_id <= 0) {
        echo json_encode(['error' => '无效的分类ID']);
        exit;
    }
    session_start();
    $api_uid = intval($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, name, sku FROM products WHERE category_id = ? AND is_active = 1 AND (user_id = 0 OR user_id = $api_uid)");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
}