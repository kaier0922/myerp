<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$conn = getDBConnection();

echo "========================================\n";
echo "quotes 表结构检查\n";
echo "========================================\n\n";

// 检查 quotes 表结构
$result = $conn->query("DESCRIBE quotes");

echo "当前字段列表：\n";
echo str_pad("字段名", 25) . str_pad("类型", 25) . str_pad("允许NULL", 10) . "默认值\n";
echo str_repeat("-", 80) . "\n";

$fields = [];
while ($row = $result->fetch_assoc()) {
    echo str_pad($row['Field'], 25) . 
         str_pad($row['Type'], 25) . 
         str_pad($row['Null'], 10) . 
         ($row['Default'] ?? 'NULL') . "\n";
    
    $fields[] = $row['Field'];
}

echo "\n========================================\n";
echo "字段检查：\n";
echo "========================================\n";

$required_fields = ['created_by', 'terms', 'discount', 'final_amount'];
foreach ($required_fields as $field) {
    if (in_array($field, $fields)) {
        echo "✅ {$field} - 存在\n";
    } else {
        echo "❌ {$field} - 不存在\n";
    }
}

echo "\n========================================\n";
echo "完整字段列表：\n";
echo implode(', ', $fields) . "\n";
echo "========================================\n";

$conn->close();
?>