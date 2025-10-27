#!/bin/bash
# =====================================================
# 公章管理系统 - 快速部署脚本
# =====================================================

echo "================================================"
echo "   公章管理系统 - 快速部署"
echo "================================================"
echo ""

# 配置
WEB_DIR="/www/wwwroot/192.168.2.244"
UPLOAD_DIR="${WEB_DIR}/uploads/seals"
CURRENT_DIR=$(dirname "$0")

# 颜色
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 检查权限
if [ "$EUID" -ne 0 ] && [ ! -w "$WEB_DIR" ]; then 
    echo -e "${YELLOW}⚠️  警告：可能需要sudo权限${NC}"
    echo ""
fi

# 步骤1：创建数据库表
echo -e "${GREEN}步骤 1/5: 创建数据库表...${NC}"
if [ -f "${CURRENT_DIR}/seals_table.sql" ]; then
    mysql -u bjd -pSz28960660 bjd < "${CURRENT_DIR}/seals_table.sql" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✓ 数据库表创建成功"
    else
        echo -e "${RED}✗ 数据库表创建失败${NC}"
        echo "  请手动执行: mysql -u bjd -pSz28960660 bjd < seals_table.sql"
    fi
else
    echo -e "${YELLOW}⚠  找不到 seals_table.sql${NC}"
fi
echo ""

# 步骤2：创建上传目录
echo -e "${GREEN}步骤 2/5: 创建上传目录...${NC}"
if [ ! -d "$UPLOAD_DIR" ]; then
    mkdir -p "$UPLOAD_DIR"
    chmod 755 "$UPLOAD_DIR"
    echo "✓ 创建目录: $UPLOAD_DIR"
else
    echo "✓ 目录已存在: $UPLOAD_DIR"
fi
echo ""

# 步骤3：复制文件
echo -e "${GREEN}步骤 3/5: 部署文件...${NC}"

FILES=(
    "seal_management.php"
    "ajax_seals.php"
    "seal-picker.js"
    "seal-picker.css"
)

for file in "${FILES[@]}"; do
    if [ -f "${CURRENT_DIR}/${file}" ]; then
        cp "${CURRENT_DIR}/${file}" "$WEB_DIR/"
        chmod 644 "${WEB_DIR}/${file}"
        echo "✓ 部署 ${file}"
    else
        echo -e "${RED}✗ 找不到 ${file}${NC}"
    fi
done
echo ""

# 步骤4：检查示例文件
echo -e "${GREEN}步骤 4/5: 检查示例文件...${NC}"
if [ -f "${CURRENT_DIR}/quotation_with_seal_demo.html" ]; then
    cp "${CURRENT_DIR}/quotation_with_seal_demo.html" "$WEB_DIR/"
    echo "✓ 部署示例页面"
else
    echo -e "${YELLOW}⚠  示例文件未找到（可选）${NC}"
fi
echo ""

# 步骤5：验证部署
echo -e "${GREEN}步骤 5/5: 验证部署...${NC}"

REQUIRED_FILES=(
    "seal_management.php"
    "ajax_seals.php"
    "seal-picker.js"
    "seal-picker.css"
)

ALL_OK=true
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "${WEB_DIR}/${file}" ]; then
        SIZE=$(stat -f%z "${WEB_DIR}/${file}" 2>/dev/null || stat -c%s "${WEB_DIR}/${file}")
        echo "✓ ${file} (${SIZE} bytes)"
    else
        echo -e "${RED}✗ ${file} 缺失${NC}"
        ALL_OK=false
    fi
done

if [ -d "$UPLOAD_DIR" ] && [ -w "$UPLOAD_DIR" ]; then
    echo "✓ uploads/seals 目录可写"
else
    echo -e "${RED}✗ uploads/seals 目录权限问题${NC}"
    ALL_OK=false
fi
echo ""

# 总结
echo "================================================"
if [ "$ALL_OK" = true ]; then
    echo -e "${GREEN}   ✅ 部署成功！${NC}"
else
    echo -e "${YELLOW}   ⚠️  部署完成但有警告${NC}"
fi
echo "================================================"
echo ""

echo "📁 文件位置："
echo "  ${WEB_DIR}/seal_management.php"
echo "  ${WEB_DIR}/uploads/seals/"
echo ""

echo "🌐 访问地址："
echo "  http://your-domain/seal_management.php"
echo ""

echo "📝 下一步："
echo "  1. 访问公章管理页面"
echo "  2. 上传第一个公章"
echo "  3. 在报价单/送货单中集成"
echo ""

echo "💡 使用建议："
echo "  1. 优化公章图片（去背景、调色）"
echo "  2. 设置默认公章"
echo "  3. 查看使用文档：SEAL_SYSTEM_GUIDE.md"
echo ""

echo "🔧 如需优化公章图片："
echo "  python3 optimize_seal.py 对公帐号666.png"
echo ""

echo "================================================"
