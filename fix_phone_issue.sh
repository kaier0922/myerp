#!/bin/bash
# 一键修复 phone 重复错误

echo "================================================"
echo "   修复 Phone 字段重复错误"
echo "================================================"
echo ""

# 配置
DB_USER="bjd"
DB_PASS="Sz28960660"
DB_NAME="bjd"
WEB_DIR="/www/wwwroot/192.168.2.244"

# 1. 检查是否有空phone的用户
echo "步骤 1/4: 检查数据库中的空手机号..."
EMPTY_PHONE_COUNT=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -sN -e "SELECT COUNT(*) FROM users WHERE phone = '' OR phone IS NULL;")

if [ "$EMPTY_PHONE_COUNT" -gt 0 ]; then
    echo "⚠️  发现 $EMPTY_PHONE_COUNT 个用户的手机号为空"
    echo ""
    
    # 显示这些用户
    echo "空手机号用户列表："
    mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT id, username, nickname, phone FROM users WHERE phone = '' OR phone IS NULL;"
    echo ""
    
    # 询问是否更新
    read -p "是否自动为这些用户生成手机号? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "正在更新空手机号..."
        mysql -u $DB_USER -p$DB_PASS $DB_NAME << 'EOF'
UPDATE users 
SET phone = CONCAT('138000000', LPAD(id, 2, '0'))
WHERE phone = '' OR phone IS NULL;
EOF
        echo "✓ 手机号已更新"
    else
        echo "⚠️  跳过更新，请手动处理空手机号"
    fi
else
    echo "✓ 没有空手机号用户"
fi
echo ""

# 2. 备份现有文件
echo "步骤 2/4: 备份现有PHP文件..."
BACKUP_DIR="${WEB_DIR}/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp "${WEB_DIR}/user_add.php" "$BACKUP_DIR/" 2>/dev/null
cp "${WEB_DIR}/user_edit.php" "$BACKUP_DIR/" 2>/dev/null
echo "✓ 备份已保存到: $BACKUP_DIR"
echo ""

# 3. 复制新文件
echo "步骤 3/4: 更新PHP文件（phone必填版本）..."
CURRENT_DIR=$(dirname "$0")
cp "${CURRENT_DIR}/user_add_phone_required.php" "${WEB_DIR}/user_add.php"
cp "${CURRENT_DIR}/user_edit_phone_required.php" "${WEB_DIR}/user_edit.php"
chmod 644 "${WEB_DIR}/user_add.php"
chmod 644 "${WEB_DIR}/user_edit.php"
echo "✓ PHP文件已更新"
echo ""

# 4. 验证
echo "步骤 4/4: 验证修复..."
echo "检查当前所有用户的手机号："
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT id, username, nickname, phone FROM users ORDER BY id;"
echo ""

echo "================================================"
echo "   修复完成！"
echo "================================================"
echo "修改内容："
echo "  ✓ 空手机号已更新（如果有）"
echo "  ✓ user_add.php - 手机号现在是必填项"
echo "  ✓ user_edit.php - 手机号现在是必填项"
echo ""
echo "请测试："
echo "  1. 打开浏览器访问用户管理"
echo "  2. 尝试添加新用户"
echo "  3. 手机号应该标记为必填（红色*）"
echo "  4. 必须输入有效的11位手机号"
echo ""
echo "备份位置: $BACKUP_DIR"
echo "================================================"
