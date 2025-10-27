#!/bin/bash
# 应付账款页面快速部署脚本

echo "================================================"
echo "   应付账款页面 - 快速部署"
echo "================================================"
echo ""

# 配置
WEB_DIR="/www/wwwroot/192.168.2.244"
CURRENT_DIR=$(dirname "$0")

# 检查是否以root运行
if [ "$EUID" -ne 0 ] && [ ! -w "$WEB_DIR" ]; then 
    echo "⚠️  警告：可能需要sudo权限"
    echo ""
fi

# 1. 备份现有文件
echo "步骤 1/4: 备份现有文件..."
BACKUP_DIR="${WEB_DIR}/backup_payment_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ -f "${WEB_DIR}/payment_add_payable.php" ]; then
    cp "${WEB_DIR}/payment_add_payable.php" "$BACKUP_DIR/"
    echo "✓ 已备份 payment_add_payable.php"
fi

if [ -f "${WEB_DIR}/ajax_supplier.php" ]; then
    cp "${WEB_DIR}/ajax_supplier.php" "$BACKUP_DIR/"
    echo "✓ 已备份 ajax_supplier.php"
fi

echo "✓ 备份保存到: $BACKUP_DIR"
echo ""

# 2. 复制新文件
echo "步骤 2/4: 部署新文件..."

if [ -f "${CURRENT_DIR}/payment_add_payable.php" ]; then
    cp "${CURRENT_DIR}/payment_add_payable.php" "$WEB_DIR/"
    echo "✓ 已部署 payment_add_payable.php"
else
    echo "❌ 找不到 payment_add_payable.php"
    exit 1
fi

if [ -f "${CURRENT_DIR}/ajax_supplier.php" ]; then
    cp "${CURRENT_DIR}/ajax_supplier.php" "$WEB_DIR/"
    echo "✓ 已部署 ajax_supplier.php"
else
    echo "❌ 找不到 ajax_supplier.php"
    exit 1
fi

echo ""

# 3. 设置权限
echo "步骤 3/4: 设置文件权限..."
chmod 644 "${WEB_DIR}/payment_add_payable.php"
chmod 644 "${WEB_DIR}/ajax_supplier.php"
echo "✓ 权限已设置为 644"
echo ""

# 4. 验证部署
echo "步骤 4/4: 验证部署..."

if [ -f "${WEB_DIR}/payment_add_payable.php" ] && [ -f "${WEB_DIR}/ajax_supplier.php" ]; then
    echo "✓ 文件部署成功"
    
    # 检查文件大小
    SIZE1=$(stat -f%z "${WEB_DIR}/payment_add_payable.php" 2>/dev/null || stat -c%s "${WEB_DIR}/payment_add_payable.php")
    SIZE2=$(stat -f%z "${WEB_DIR}/ajax_supplier.php" 2>/dev/null || stat -c%s "${WEB_DIR}/ajax_supplier.php")
    
    echo "  - payment_add_payable.php: ${SIZE1} bytes"
    echo "  - ajax_supplier.php: ${SIZE2} bytes"
else
    echo "❌ 文件部署失败"
    exit 1
fi

echo ""
echo "================================================"
echo "   部署完成！"
echo "================================================"
echo ""
echo "📁 文件位置："
echo "  ${WEB_DIR}/payment_add_payable.php"
echo "  ${WEB_DIR}/ajax_supplier.php"
echo ""
echo "📦 备份位置："
echo "  ${BACKUP_DIR}"
echo ""
echo "🌐 访问地址："
echo "  http://your-domain/payment_add_payable.php"
echo ""
echo "✨ 新功能："
echo "  ✓ 智能供应商搜索"
echo "  ✓ 快速新建供应商"
echo "  ✓ 自动计算到期日期"
echo "  ✓ 现代化UI设计"
echo ""
echo "📚 使用说明："
echo "  查看 PAYMENT_USAGE_GUIDE.md"
echo ""
echo "🔍 测试建议："
echo "  1. 测试供应商搜索功能"
echo "  2. 测试新建供应商功能"
echo "  3. 提交应付账款测试"
echo ""
echo "================================================"
