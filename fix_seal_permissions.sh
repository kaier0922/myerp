#!/bin/bash
# 修复公章上传目录权限

echo "🔧 修复公章上传目录权限..."
echo ""

WEB_DIR="/www/wwwroot/192.168.2.244"
UPLOAD_DIR="${WEB_DIR}/uploads/seals"

# 1. 创建目录（如果不存在）
if [ ! -d "$UPLOAD_DIR" ]; then
    echo "创建目录: $UPLOAD_DIR"
    mkdir -p "$UPLOAD_DIR"
fi

# 2. 设置正确的权限
echo "设置目录权限..."
chmod 755 "$UPLOAD_DIR"
chown www:www "$UPLOAD_DIR"  # 或者 chown nginx:nginx 或 apache:apache

# 3. 设置父目录权限
chmod 755 "${WEB_DIR}/uploads"
chown www:www "${WEB_DIR}/uploads"

# 4. 验证
echo ""
echo "验证结果:"
ls -ld "$UPLOAD_DIR"
ls -ld "${WEB_DIR}/uploads"

echo ""
echo "✅ 权限修复完成！"
echo ""
echo "现在可以重新上传公章了"