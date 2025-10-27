<?php
/**
 * 文件名: fix_mechanical_hdd.php
 * 版本: v1.0
 * 说明: 一键修复机械硬盘分类问题
 */

echo "========================================\n";
echo "开始修复机械硬盘分类问题\n";
echo "========================================\n\n";

require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // 第一步：清理数据库
    echo "[1/3] 清理数据库重复分类...\n";
    
    $conn->query("UPDATE products SET category_id = 76 WHERE category_id IN (77, 78)");
    echo "  ✓ 已统一产品分类到ID=76\n";
    
    $conn->query("DELETE FROM product_categories WHERE id IN (77, 78) AND name = '机械硬盘'");
    echo "  ✓ 已删除重复分类\n";
    
    $conn->query("UPDATE product_categories SET sort_order = 5 WHERE id = 76");
    echo "  ✓ 已更新排序\n";
    
    // 验证
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM products 
        WHERE category_id = 76
    ");
    $row = $result->fetch_assoc();
    echo "  ✓ 机械硬盘产品数量: {$row['count']}\n\n";
    
    // 第二步：修改PHP文件
    echo "[2/3] 更新quote_create_v3.php...\n";
    
    $phpFile = 'quote_create_v3.php';
    
    if (!file_exists($phpFile)) {
        die("  ✗ 错误：找不到文件 $phpFile\n");
    }
    
    // 备份原文件
    $backupFile = $phpFile . '.backup.' . date('Ymd_His');
    copy($phpFile, $backupFile);
    echo "  ✓ 已备份到: $backupFile\n";
    
    // 读取文件
    $content = file_get_contents($phpFile);
    
    // 修改 categoryMap
    $oldMap = <<<'EOD'
const categoryMap = {
    11: 'CPU处理器',
    12: '主板',
    13: '内存',
    14: '硬盘/SSD',
    15: '显卡',
    16: '电源',
    17: '机箱',
    18: '散热器',
    41: '显示器',
    45: '键鼠套装'
};
EOD;

    $newMap = <<<'EOD'
const categoryMap = {
    11: 'CPU处理器',
    12: '主板',
    13: '内存',
    14: '硬盘/SSD',
    15: '显卡',
    16: '电源',
    17: '机箱',
    18: '散热器',
    19: '其他配件',
    76: '机械硬盘',        // 新增：机械硬盘分类
    41: '显示器',
    45: '键鼠套装'
};
EOD;

    if (strpos($content, '76: \'机械硬盘\'') === false) {
        $content = str_replace($oldMap, $newMap, $content);
        echo "  ✓ 已更新categoryMap\n";
    } else {
        echo "  ⊙ categoryMap已包含机械硬盘，跳过\n";
    }
    
    // 修改 defaultAssembledPCConfig（在硬盘/SSD后添加机械硬盘）
    if (strpos($content, "category_id: 76") === false) {
        $pattern = "/(category: '硬盘\/SSD', category_id: 14[^}]+},)/";
        $replacement = "$1\n    { category: '机械硬盘', category_id: 76, unit: '个', quantity: 1 },  // 新增";
        $content = preg_replace($pattern, $replacement, $content);
        echo "  ✓ 已更新defaultAssembledPCConfig\n";
    } else {
        echo "  ⊙ defaultAssembledPCConfig已包含机械硬盘，跳过\n";
    }
    
    // 写入文件
    file_put_contents($phpFile, $content);
    echo "  ✓ 文件已保存\n\n";
    
    // 第三步：验证
    echo "[3/3] 验证修复结果...\n";
    
    $result = $conn->query("
        SELECT 
            pc.id,
            pc.name,
            COUNT(p.id) as product_count
        FROM product_categories pc
        LEFT JOIN products p ON pc.id = p.category_id
        WHERE pc.parent_id = 1
        GROUP BY pc.id, pc.name
        ORDER BY pc.sort_order
    ");
    
    echo "\n分类列表：\n";
    echo str_pad("ID", 6) . str_pad("分类名称", 20) . "产品数量\n";
    echo str_repeat("-", 36) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['id'], 6) . 
             str_pad($row['name'], 20) . 
             $row['product_count'] . "\n";
    }
    
    $conn->close();
    
    echo "\n========================================\n";
    echo "✓ 修复完成！\n";
    echo "========================================\n";
    echo "\n请执行以下步骤：\n";
    echo "1. 清除浏览器缓存（Ctrl+F5）\n";
    echo "2. 打开报价单创建页面\n";
    echo "3. 在'配件名称'下拉框应该能看到'机械硬盘'\n";
    echo "4. 选择后应该能看到22款机械硬盘产品\n\n";
    
} catch (Exception $e) {
    echo "\n✗ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
?>