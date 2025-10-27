<?php
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>数据库结构检查</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<?php
$conn = getDBConnection();

echo "<h1>📊 数据库结构完整检查</h1>";

// ==================== 检查1：quotes 表结构 ====================
echo "<div class='section'>";
echo "<h2>1. quotes 表结构</h2>";

$result = $conn->query("DESCRIBE quotes");
if ($result) {
    echo "<table>";
    echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>";
    $quotes_fields = [];
    while ($row = $result->fetch_assoc()) {
        $quotes_fields[] = $row['Field'];
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>" . ($row['Null'] == 'NO' ? '<span class="error">NOT NULL</span>' : 'YES') . "</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>字段清单：</h3>";
    echo "<pre>" . implode(', ', $quotes_fields) . "</pre>";
    
    // 检查必需字段
    $required_fields = ['id', 'user_id', 'customer_id', 'quote_no', 'quote_date', 'status', 'final_amount', 'created_at'];
    $missing_fields = array_diff($required_fields, $quotes_fields);
    
    if (empty($missing_fields)) {
        echo "<p class='ok'>✅ 所有必需字段都存在</p>";
    } else {
        echo "<p class='error'>❌ 缺少字段: " . implode(', ', $missing_fields) . "</p>";
    }
}
echo "</div>";

// ==================== 检查2：quote_items 表结构 ====================
echo "<div class='section'>";
echo "<h2>2. quote_items 表结构</h2>";

$result = $conn->query("DESCRIBE quote_items");
if ($result) {
    echo "<table>";
    echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>";
    $items_fields = [];
    while ($row = $result->fetch_assoc()) {
        $items_fields[] = $row['Field'];
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>" . ($row['Null'] == 'NO' ? '<span class="error">NOT NULL</span>' : 'YES') . "</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>字段清单：</h3>";
    echo "<pre>" . implode(', ', $items_fields) . "</pre>";
}
echo "</div>";

// ==================== 检查3：外键约束 ====================
echo "<div class='section'>";
echo "<h2>3. 外键约束检查</h2>";

$result = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN ('quotes', 'quote_items')
      AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>表名</th><th>字段</th><th>约束名</th><th>引用表</th><th>引用字段</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['TABLE_NAME']}</td>";
        echo "<td>{$row['COLUMN_NAME']}</td>";
        echo "<td>{$row['CONSTRAINT_NAME']}</td>";
        echo "<td>{$row['REFERENCED_TABLE_NAME']}</td>";
        echo "<td>{$row['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='warning'>⚠️ 存在外键约束，保存时需要确保关联数据存在</p>";
} else {
    echo "<p class='ok'>✅ 没有外键约束</p>";
}
echo "</div>";

// ==================== 检查4：生成对应的 SQL ====================
echo "<div class='section'>";
echo "<h2>4. 推荐的插入语句</h2>";

echo "<h3>quotes 表插入语句：</h3>";
echo "<pre>";
echo "INSERT INTO quotes (\n    ";
$insert_fields = array_filter($quotes_fields, function($f) { 
    return !in_array($f, ['id', 'created_at']); 
});
echo implode(",\n    ", $insert_fields);
echo "\n) VALUES (\n    ";
echo str_repeat("?, ", count($insert_fields) - 1) . "?, NOW()";
echo "\n)";
echo "</pre>";

echo "<h3>参数类型字符串：</h3>";
$types = [];
foreach ($insert_fields as $field) {
    if (in_array($field, ['user_id', 'customer_id', 'valid_days'])) {
        $types[] = 'i';
    } elseif (in_array($field, ['discount', 'final_amount'])) {
        $types[] = 'd';
    } else {
        $types[] = 's';
    }
}
echo "<pre>'" . implode('', $types) . "'</pre>";
echo "<p>共 " . count($types) . " 个参数</p>";

echo "<h3>quote_items 表插入语句：</h3>";
echo "<pre>";
echo "INSERT INTO quote_items (\n    ";
$insert_items_fields = array_filter($items_fields, function($f) { 
    return !in_array($f, ['id']); 
});
echo implode(",\n    ", $insert_items_fields);
echo "\n) VALUES (\n    ";
echo str_repeat("?, ", count($insert_items_fields) - 1) . "?";
echo "\n)";
echo "</pre>";

echo "<h3>参数类型字符串：</h3>";
$items_types = [];
foreach ($insert_items_fields as $field) {
    if (in_array($field, ['quote_id', 'seq', 'product_id', 'quantity'])) {
        $items_types[] = 'i';
    } elseif (in_array($field, ['price', 'cost', 'subtotal', 'cost_subtotal'])) {
        $items_types[] = 'd';
    } else {
        $items_types[] = 's';
    }
}
echo "<pre>'" . implode('', $items_types) . "'</pre>";
echo "<p>共 " . count($items_types) . " 个参数</p>";
echo "</div>";

// ==================== 检查5：测试插入 ====================
echo "<div class='section'>";
echo "<h2>5. 测试插入（会自动回滚）</h2>";

$conn->begin_transaction();

try {
    // 测试插入 quotes
    $test_quote_sql = "
        INSERT INTO quotes 
        (user_id, customer_id, quote_no, template_type, quote_date, valid_days, status, final_amount, discount, created_at)
        VALUES (1, 1, 'TEST-" . time() . "', 'assembled_pc', CURDATE(), 15, '草稿', 100.00, 0, NOW())
    ";
    
    if ($conn->query($test_quote_sql)) {
        $test_quote_id = $conn->insert_id;
        echo "<p class='ok'>✅ quotes 表插入测试成功 (ID: {$test_quote_id})</p>";
        
        // 测试插入 quote_items
        $test_item_sql = "
            INSERT INTO quote_items 
            (quote_id, seq, category, product_name, unit, quantity, price, cost, subtotal, cost_subtotal)
            VALUES ({$test_quote_id}, 1, '测试', '测试产品', '个', 1, 100.00, 85.00, 100.00, 85.00)
        ";
        
        if ($conn->query($test_item_sql)) {
            echo "<p class='ok'>✅ quote_items 表插入测试成功</p>";
        } else {
            echo "<p class='error'>❌ quote_items 插入失败: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='error'>❌ quotes 插入失败: " . $conn->error . "</p>";
    }
    
    $conn->rollback();
    echo "<p class='warning'>⚠️ 测试数据已回滚</p>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<p class='error'>❌ 测试失败: " . $e->getMessage() . "</p>";
}

echo "</div>";

// ==================== 检查6：关联表检查 ====================
echo "<div class='section'>";
echo "<h2>6. 关联表检查</h2>";

// 检查 customers 表
$cust_count = $conn->query("SELECT COUNT(*) as cnt FROM customers")->fetch_assoc()['cnt'];
echo "<p>customers 表: <strong>{$cust_count}</strong> 条记录 " . ($cust_count > 0 ? '<span class="ok">✅</span>' : '<span class="error">❌ 没有客户数据</span>') . "</p>";

// 检查 products 表
$prod_count = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1")->fetch_assoc()['cnt'];
echo "<p>products 表: <strong>{$prod_count}</strong> 个活跃产品 " . ($prod_count > 0 ? '<span class="ok">✅</span>' : '<span class="error">❌ 没有产品数据</span>') . "</p>";

// 检查 users 表
$user_count = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
echo "<p>users 表: <strong>{$user_count}</strong> 个用户 " . ($user_count > 0 ? '<span class="ok">✅</span>' : '<span class="error">❌ 没有用户数据</span>') . "</p>";

echo "</div>";

$conn->close();
?>

<div class='section'>
    <h2>7. 下一步操作</h2>
    <ul>
        <li>如果发现字段不匹配，需要修改 SQL 语句</li>
        <li>如果发现 NOT NULL 字段，需要在代码中提供默认值</li>
        <li>如果有外键约束，需要确保关联数据存在</li>
        <li>如果测试插入失败，查看错误信息修复问题</li>
    </ul>
    
    <h3>常见问题：</h3>
    <ul>
        <li><strong>字段不存在</strong>：修改 SQL 删除该字段</li>
        <li><strong>NOT NULL 约束</strong>：代码中提供默认值或允许 NULL</li>
        <li><strong>类型不匹配</strong>：检查 bind_param 的类型字符串</li>
        <li><strong>外键约束</strong>：先插入关联表数据</li>
    </ul>
</div>

</body>
</html>