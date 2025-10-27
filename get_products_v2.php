<?php
/**
 * 文件名: get_products_v2.php
 * 版本: v2.1
 * 说明: 获取产品数据API，支持按分类和类型筛选
 * 作者: System
 * 日期: 2025-10-12
 * 更新: v2.1 - 修复字段名错误，与实际数据库结构匹配
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// 检查登录状态
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDBConnection();
    
    $type = $_GET['type'] ?? 'all';
    $categoryId = $_GET['category'] ?? '';
    
    // 构建查询 - 使用实际存在的字段
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
                p.stock_quantity,
                p.min_stock,
                c.name as category_name,
                c.parent_id
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.id
            WHERE p.is_active = 1";
    
    $params = [];
    $types = '';
    
    // 根据产品类型过滤
    if ($type !== 'all') {
        $sql .= " AND p.product_type = ?";
        $params[] = $type;
        $types .= 's';
    }
    
    // 根据分类ID过滤
    if (!empty($categoryId)) {
        $categories = explode(',', $categoryId);
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        $sql .= " AND p.category_id IN ($placeholders)";
        
        foreach ($categories as $cat) {
            $params[] = intval($cat);
            $types .= 'i';
        }
    }
    
    $sql .= " ORDER BY p.category_id, p.name";
    
    $stmt = $conn->prepare($sql);
    
    // 绑定参数
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // 处理 platform_tags
        $tags = '';
        if (!empty($row['platform_tags'])) {
            if (is_string($row['platform_tags']) && $row['platform_tags'][0] == '[') {
                // JSON 数组格式
                $tags_array = json_decode($row['platform_tags'], true);
                if ($tags_array && is_array($tags_array)) {
                    $tags = implode(',', $tags_array);
                }
            } else {
                $tags = $row['platform_tags'];
            }
        }
        
        // 从 spec 字段中尝试提取品牌信息（如果有的话）
        $brand = '';
        if (!empty($row['supplier_name'])) {
            $brand = $row['supplier_name'];
        } elseif (!empty($row['spec'])) {
            // 尝试从规格中提取第一个词作为品牌
            $spec_parts = explode(' ', trim($row['spec']));
            if (!empty($spec_parts[0])) {
                $brand = $spec_parts[0];
            }
        }
        
        $products[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'parent_id' => $row['parent_id'],
            'product_type' => $row['product_type'],
            'sku' => $row['sku'],
            'spec' => $row['spec'] ?? '',
            'unit' => $row['unit'],
            'supplier_name' => $row['supplier_name'] ?? '',
            'cost_price' => $row['cost_price'],
            'default_price' => $row['default_price'],
            'stock_quantity' => $row['stock_quantity'],
            'min_stock' => $row['min_stock'],
            'tags' => $tags,
            'brand' => $brand  // 兼容前端代码
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>