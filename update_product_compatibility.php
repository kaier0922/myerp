<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("数据库连接失败！");
}

echo "<h2>🔧 更新产品兼容性数据</h2><hr>";

// 定义兼容性规则
$compatibility_data = [
    // Intel 12/13/14代平台 (LGA1700 + DDR5)
    ['sku' => 'CPU-001', 'tags' => ['Intel', 'LGA1700', 'DDR5'], 'name' => 'Intel i7-14700K'],
    
    // AMD Ryzen 7000系列 (AM5 + DDR5)
    ['sku' => 'CPU-002', 'tags' => ['AMD', 'AM5', 'DDR5'], 'name' => 'AMD Ryzen 7 7800X3D'],
    
    // 主板
    ['sku' => 'MB-001', 'tags' => ['Intel', 'LGA1700', 'DDR5'], 'name' => '华硕 ROG STRIX Z790-E'],
    
    // 内存
    ['sku' => 'RAM-001', 'tags' => ['DDR5'], 'name' => '金士顿 DDR5 32GB'],
];

// 添加更多测试产品
$new_products = [
    // Intel 10/11代平台产品
    ['cat' => 11, 'type' => 'hardware', 'sku' => 'CPU-003', 'name' => 'Intel i5-11400F', 'spec' => '6核12线程，LGA1200', 'tags' => ['Intel', 'LGA1200', 'DDR4'], 'unit' => '个', 'cost' => 899, 'price' => 999],
    ['cat' => 12, 'type' => 'hardware', 'sku' => 'MB-002', 'name' => '华硕 B560M', 'spec' => 'Micro-ATX，LGA1200', 'tags' => ['Intel', 'LGA1200', 'DDR4'], 'unit' => '块', 'cost' => 599, 'price' => 699],
    ['cat' => 13, 'type' => 'hardware', 'sku' => 'RAM-002', 'name' => '威刚 DDR4 16GB', 'spec' => '3200MHz，8GB×2', 'tags' => ['DDR4'], 'unit' => '套', 'cost' => 299, 'price' => 359],
    
    // AMD AM4平台产品
    ['cat' => 11, 'type' => 'hardware', 'sku' => 'CPU-004', 'name' => 'AMD Ryzen 5 5600X', 'spec' => '6核12线程，AM4接口', 'tags' => ['AMD', 'AM4', 'DDR4'], 'unit' => '个', 'cost' => 899, 'price' => 999],
    ['cat' => 12, 'type' => 'hardware', 'sku' => 'MB-003', 'name' => '微星 B550M', 'spec' => 'Micro-ATX，AM4', 'tags' => ['AMD', 'AM4', 'DDR4'], 'unit' => '块', 'cost' => 599, 'price' => 699],
    
    // AMD AM5平台产品
    ['cat' => 11, 'type' => 'hardware', 'sku' => 'CPU-005', 'name' => 'AMD Ryzen 9 7950X', 'spec' => '16核32线程，AM5接口', 'tags' => ['AMD', 'AM5', 'DDR5'], 'unit' => '个', 'cost' => 3899, 'price' => 4299],
    ['cat' => 12, 'type' => 'hardware', 'sku' => 'MB-004', 'name' => '华硕 X670E', 'spec' => 'ATX，AM5，PCIe 5.0', 'tags' => ['AMD', 'AM5', 'DDR5'], 'unit' => '块', 'cost' => 1899, 'price' => 2199],
];

echo "<h3>1. 添加新的测试产品</h3>";
$count = 0;
foreach ($new_products as $p) {
    // 检查是否已存在
    $check = $conn->query("SELECT id FROM products WHERE sku = '{$p['sku']}'")->fetch_assoc();
    if ($check) {
        echo "<p style='color: orange;'>⚠ {$p['name']} 已存在，跳过</p>";
        continue;
    }
    
    $tags_json = json_encode($p['tags']);
    $stmt = $conn->prepare("
        INSERT INTO products 
        (category_id, product_type, sku, name, spec, unit, cost_price, default_price, 
         stock_quantity, platform_tags, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 20, ?, 1)
    ");
    $stmt->bind_param("isssssdds", 
        $p['cat'], $p['type'], $p['sku'], $p['name'], $p['spec'], 
        $p['unit'], $p['cost'], $p['price'], $tags_json
    );
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ 添加产品：{$p['name']}</p>";
        $count++;
    }
}
echo "<p><strong>成功添加 $count 个新产品</strong></p>";

echo "<h3>2. 更新现有产品的兼容性标签</h3>";
$updated = 0;
foreach ($compatibility_data as $item) {
    $tags_json = json_encode($item['tags']);
    $stmt = $conn->prepare("UPDATE products SET platform_tags = ? WHERE sku = ?");
    $stmt->bind_param("ss", $tags_json, $item['sku']);
    
    if ($stmt->execute() && $conn->affected_rows > 0) {
        echo "<p style='color: green;'>✓ 更新：{$item['name']} → " . implode(', ', $item['tags']) . "</p>";
        $updated++;
    }
}
echo "<p><strong>成功更新 $updated 个产品</strong></p>";

echo "<h3>3. 当前兼容性配置汇总</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; margin-top: 10px;'>";
echo "<tr style='background: #f0f0f0;'>
    <th>SKU</th>
    <th>产品名称</th>
    <th>分类</th>
    <th>兼容性标签</th>
</tr>";

$products = $conn->query("
    SELECT p.sku, p.name, p.platform_tags, pc.name as cat_name
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.category_id IN (11, 12, 13) AND p.is_active = 1
    ORDER BY p.category_id, p.sku
");

while ($row = $products->fetch_assoc()) {
    $tags = json_decode($row['platform_tags'], true) ?? [];
    $tags_str = empty($tags) ? '<span style="color: #999;">未设置</span>' : implode(', ', $tags);
    
    echo "<tr>";
    echo "<td><code>{$row['sku']}</code></td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['cat_name']}</td>";
    echo "<td>{$tags_str}</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();

echo "<hr>";
echo "<h3>✅ 兼容性数据更新完成！</h3>";
echo "<p><strong>兼容性平台说明：</strong></p>";
echo "<ul>";
echo "<li><strong>Intel LGA1700 + DDR5</strong>：第12/13/14代酷睿处理器（i5-12400、i7-14700K等）</li>";
echo "<li><strong>Intel LGA1200 + DDR4</strong>：第10/11代酷睿处理器（i5-10400、i5-11400F等）</li>";
echo "<li><strong>AMD AM5 + DDR5</strong>：Ryzen 7000系列处理器（7800X3D、7950X等）</li>";
echo "<li><strong>AMD AM4 + DDR4</strong>：Ryzen 5000系列处理器（5600X、5800X等）</li>";
echo "</ul>";
echo "<p><a href='quote_create.php?template=assembled_pc' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin-right: 10px;'>创建组装机报价单</a>";
echo "<a href='products.php' style='display: inline-block; padding: 10px 20px; background: #f7fafc; border: 1px solid #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px;'>产品管理</a></p>";
?>