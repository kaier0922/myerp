<?php
/**
 * 文件名: get_products_v2.php
 * 版本: v2.4 - 性能优化（简化ORDER BY，去掉全表REGEXP扫描）
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
require_once 'auth_helper.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDBConnection();

    $type       = $_GET['type']     ?? 'all';
    $categoryId = $_GET['category'] ?? '';

    $sql = "SELECT
                p.id,
                p.name,
                p.category_id,
                p.product_type,
                p.sku,
                p.spec,
                p.unit,
                p.supplier_name,
                p.cost_price,
                p.default_price,
                p.platform_tags,
                p.platform_data,
                p.stock_quantity,
                p.min_stock,
                c.name AS category_name,
                c.parent_id
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            AND (p.user_id = 0 OR p.user_id = ?)";

    $uid    = (int)$_SESSION['user_id'];
    $params = [$uid];
    $types  = 'i';

    if ($type !== 'all') {
        $sql .= " AND p.product_type = ?";
        $params[] = $type;
        $types   .= 's';
    }

    if (!empty($categoryId)) {
        $categories   = explode(',', $categoryId);
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $sql .= " AND p.category_id IN ($placeholders)";
        foreach ($categories as $cat) {
            $params[] = (int)$cat;
            $types   .= 'i';
        }
    }

    // 简化排序：按分类排序权重、分类ID、产品名称
    // 原来几十行 CASE WHEN REGEXP 全部移除，改为简单三列排序
    // CPU处理器(category_id=11)内部顺序由前端关键字过滤代替
    $sql .= " ORDER BY
        IFNULL(c.sort_order, 999),
        p.category_id,
        p.name";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('SQL预处理失败: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception('SQL执行失败: ' . $stmt->error);
    }

    $result   = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $brand = '';
        if (!empty($row['supplier_name'])) {
            $brand = $row['supplier_name'];
        } elseif (!empty($row['name'])) {
            $nameParts = explode(' ', trim($row['name']));
            $brand     = $nameParts[0] ?? '';
        }

        $products[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name']          ?? '',
            'category_id'    => (int)$row['category_id'],
            'category_name'  => $row['category_name'] ?? '',
            'parent_id'      => isset($row['parent_id']) ? (int)$row['parent_id'] : null,
            'product_type'   => $row['product_type']  ?? '',
            'sku'            => $row['sku']            ?? '',
            'spec'           => $row['spec']           ?? '',
            'unit'           => $row['unit']           ?? '',
            'supplier_name'  => $row['supplier_name']  ?? '',
            'cost_price'     => (float)$row['cost_price'],
            'default_price'  => (float)$row['default_price'],
            'stock_quantity' => (int)$row['stock_quantity'],
            'min_stock'      => (int)$row['min_stock'],
            'platform_tags'  => $row['platform_tags'],
            'platform_data'  => $row['platform_data'],
            'brand'          => $brand,
        ];
    }

    echo json_encode([
        'success'  => true,
        'products' => $products,
        'count'    => count($products),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
