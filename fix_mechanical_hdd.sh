#!/bin/bash
# ========================================
# 文件名: fix_mechanical_hdd.sh
# 版本: v1.0
# 说明: 一键修复机械硬盘分类问题
# ========================================

echo "=========================================="
echo "开始修复机械硬盘分类问题"
echo "=========================================="

# 数据库配置
DB_USER="bjd"
DB_NAME="bjd"

# 第一步：清理数据库
echo ""
echo "[1/3] 清理数据库重复分类..."
mysql -u $DB_USER -p $DB_NAME << EOF
UPDATE products SET category_id = 76 WHERE category_id IN (77, 78);
DELETE FROM product_categories WHERE id IN (77, 78) AND name = '机械硬盘';
UPDATE product_categories SET sort_order = 5 WHERE id = 76;
SELECT '数据库清理完成' as 状态;
EOF

# 第二步：备份原文件
echo ""
echo "[2/3] 备份原文件..."
if [ -f "quote_create_v3.php" ]; then
    cp quote_create_v3.php quote_create_v3.php.backup.$(date +%Y%m%d_%H%M%S)
    echo "已备份到: quote_create_v3.php.backup.$(date +%Y%m%d_%H%M%S)"
fi

# 第三步：修改PHP文件
echo ""
echo "[3/3] 更新PHP文件..."
sed -i.bak '
/const categoryMap = {/,/};/ {
    /};/i\    76: '\''机械硬盘'\'',        // 新增：机械硬盘分类
}
/const defaultAssembledPCConfig = \[/,/\];/ {
    /{ category: '\''硬盘\/SSD'\''/a\    { category: '\''机械硬盘'\'', category_id: 76, unit: '\''个'\'', quantity: 1 },  // 新增
}
' quote_create_v3.php

echo ""
echo "=========================================="
echo "修复完成！请执行以下步骤："
echo "1. 刷新浏览器缓存（Ctrl+F5）"
echo "2. 打开报价单创建页面测试"
echo "=========================================="