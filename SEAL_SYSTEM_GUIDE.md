# 公章管理系统 - 完整使用指南

## 🎯 系统概述

一套完整的公章管理解决方案，可以：
- ✅ 上传和管理多个公章
- ✅ 在报价单/送货单上可视化盖章
- ✅ 拖拽调整公章位置和大小
- ✅ 打印和导出带公章的PDF
- ✅ 保存每个文档的公章配置

---

## 📦 文件清单

### 核心文件（必需）
1. ✅ **seal_management.php** - 公章管理页面
2. ✅ **seals_table.sql** - 数据库表结构
3. ✅ **seal-picker.js** - 可视化盖章组件
4. ✅ **seal-picker.css** - 样式文件
5. ✅ **ajax_seals.php** - AJAX处理

### 示例文件
6. ✅ **quotation_with_seal_demo.html** - 报价单示例
7. ✅ **SEAL_SYSTEM_GUIDE.md** - 本文档

---

## 🚀 快速部署（5步）

### 步骤1：创建数据库表
```bash
mysql -u bjd -pSz28960660 bjd < seals_table.sql
```

### 步骤2：创建公章目录
```bash
cd /www/wwwroot/192.168.2.244
mkdir -p uploads/seals
chmod 755 uploads/seals
```

### 步骤3：上传文件
```bash
# 上传所有PHP、JS、CSS文件到网站根目录
cp seal_management.php /www/wwwroot/192.168.2.244/
cp ajax_seals.php /www/wwwroot/192.168.2.244/
cp seal-picker.js /www/wwwroot/192.168.2.244/
cp seal-picker.css /www/wwwroot/192.168.2.244/

# 设置权限
chmod 644 *.php *.js *.css
```

### 步骤4：上传第一个公章
1. 访问：http://your-domain/seal_management.php
2. 点击"➕ 上传新公章"
3. 上传您的公章图片（对公帐号666.png）

### 步骤5：优化公章图片（建议）
您上传的公章需要优化一下：
- 去除白色背景 → 透明PNG
- 调整颜色 → 标准印章红

---

## 🎨 公章图片要求

### 最佳规格
```
格式：PNG（必须）
尺寸：800x800px 或更大
背景：完全透明
颜色：印章红 RGB(197, 48, 48) 或 #C53030
分辨率：300 DPI（打印用）
```

### 当前公章问题
您上传的"对公帐号666.png"：
- ❌ 有白色背景（需要去除）
- ❌ 颜色偏淡（需要加深）
- ✅ 尺寸可以（但可以更大）

### 优化方法

**方法1：使用在线工具**
1. 去背景：https://www.remove.bg/zh
2. 调色：用Photoshop或在线PS

**方法2：我帮您处理**
如果需要，我可以用Python帮您处理这个公章图片

---

## 💡 使用教程

### 一、上传公章

1. **访问公章管理页面**
   ```
   http://your-domain/seal_management.php
   ```

2. **点击"上传新公章"**
   - 填写公章名称：深圳市凯尔耐特科技有限公司公章
   - 选择类型：公章
   - 上传图片：选择PNG文件
   - 添加备注：（可选）

3. **设置默认公章**
   - 点击某个公章的"设为默认"按钮
   - 默认公章会自动用于新文档

---

### 二、在报价单上盖章

#### 步骤1：在报价单页面引入组件

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="seal-picker.css">
</head>
<body>
    <!-- 工具栏 -->
    <div class="toolbar">
        <!-- 公章选择器容器 -->
        <div class="seal-picker-container"></div>
        <button onclick="window.print()">打印</button>
    </div>

    <!-- 报价单内容 -->
    <div class="print-area" id="printArea">
        <!-- 报价单内容... -->
    </div>

    <script src="seal-picker.js"></script>
</body>
</html>
```

#### 步骤2：点击"盖章"按钮
1. 页面上会出现"🖊️ 盖章"按钮
2. 点击按钮，弹出公章选择器
3. 选择要使用的公章

#### 步骤3：调整公章位置
1. 公章会出现在文档上
2. 拖动公章到合适位置
3. 使用 + / - 按钮调整大小
4. 点击 × 删除公章

#### 步骤4：打印或导出
1. 调整好后，点击"打印"按钮
2. 或点击"导出PDF"
3. 公章会包含在打印/PDF中

---

### 三、集成到现有页面

#### 在报价单页面（quotation_view.php）

```php
<!DOCTYPE html>
<html>
<head>
    <!-- 添加样式 -->
    <link rel="stylesheet" href="seal-picker.css">
</head>
<body>
    <!-- 工具栏 -->
    <div class="toolbar">
        <!-- 添加公章选择器 -->
        <div class="seal-picker-container"></div>
        
        <!-- 原有的按钮 -->
        <button onclick="window.print()">打印</button>
        <button onclick="exportPDF()">导出PDF</button>
    </div>

    <!-- 报价单内容（添加class="print-area"） -->
    <div class="print-area">
        <!-- 原有的报价单HTML -->
    </div>

    <!-- 在页面底部添加脚本 -->
    <script src="seal-picker.js"></script>
</body>
</html>
```

#### 在送货单页面（delivery_view.php）
同样的方法，只需要：
1. 引入 CSS 和 JS
2. 添加 `seal-picker-container` div
3. 给文档容器添加 `print-area` class

---

## 🔧 高级功能

### 保存公章配置

如果希望为每个报价单保存公章位置，在导出PDF时使用：

```javascript
// 获取公章位置
const sealConfig = sealPicker.getSealPosition();

// 保存到数据库
fetch('ajax_seals.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'save_document_seal',
        document_type: 'quotation',  // 或 'delivery'
        document_id: 123,  // 报价单ID
        seal_id: sealConfig.seal_id,
        position_x: sealConfig.x,
        position_y: sealConfig.y,
        seal_size: sealConfig.size
    })
});
```

### 自动加载已保存的公章

```javascript
// 页面加载时恢复公章
fetch(`ajax_seals.php?action=get_document_seal&document_type=quotation&document_id=123`)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // 恢复公章位置
            sealPicker.restoreSeal(data.config);
        }
    });
```

---

## 📄 服务器端PDF生成（可选）

如果需要在服务器端生成带公章的PDF，可以使用TCPDF：

### 安装TCPDF
```bash
cd /www/wwwroot/192.168.2.244
composer require tecnickcom/tcpdf
```

### 生成PDF示例
```php
<?php
require_once('vendor/autoload.php');

// 创建PDF
$pdf = new TCPDF();
$pdf->AddPage();

// 添加报价单内容
$html = '<h1>报价单</h1>...';
$pdf->writeHTML($html);

// 添加公章（从数据库读取配置）
$seal_config = getSealConfig('quotation', $quotation_id);
if ($seal_config) {
    $pdf->Image(
        'uploads/seals/' . $seal_config['file_path'],
        $seal_config['position_x'],
        $seal_config['position_y'],
        $seal_config['seal_size'],
        $seal_config['seal_size'],
        'PNG'
    );
}

// 输出PDF
$pdf->Output('quotation_' . $quotation_id . '.pdf', 'D');
?>
```

---

## 🎯 最佳实践

### 1. 公章尺寸建议
```
报价单/送货单：80-120px
合同：100-150px
发票：60-80px
```

### 2. 公章位置建议
```
右下角：距右边 80-100px，距底部 80-100px
签字处：在"（公章）"文字上方
骑缝章：跨页边界
```

### 3. 打印建议
- 使用"打印"功能时，确保选择"背景图形"
- PDF导出使用浏览器的"另存为PDF"
- 正式文档建议服务器端生成PDF

### 4. 安全建议
- 限制公章上传权限（只有管理员）
- 定期备份公章文件
- 在数据库中记录盖章操作日志

---

## 🛠️ 故障排查

### Q1: 上传公章后看不到？
```bash
# 检查目录权限
ls -la /www/wwwroot/192.168.2.244/uploads/seals/

# 设置权限
chmod 755 uploads/seals
```

### Q2: 公章显示不正常？
- 确保是PNG格式
- 检查图片是否透明背景
- 文件大小建议 < 500KB

### Q3: 拖拽不工作？
- 确保引入了 seal-picker.js
- 检查浏览器控制台错误
- 确保文档容器有 `print-area` class

### Q4: 打印时公章丢失？
- 检查打印设置中"背景图形"已勾选
- 确保公章在打印区域内
- CSS中不要隐藏 .document-seal

---

## 📊 功能对比

| 功能 | 前端方案 | 服务器端方案 |
|------|----------|--------------|
| 可视化调整 | ✅ 支持 | ❌ 不支持 |
| 用户体验 | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| PDF质量 | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| 批量处理 | ❌ 不适合 | ✅ 适合 |
| 依赖库 | 无 | 需要TCPDF |

**推荐方案**：混合使用
- 前端：用于可视化调整和预览
- 服务器：用于生成正式PDF文档

---

## 📞 技术支持

### 需要帮助？
1. 查看本文档
2. 检查浏览器控制台（F12）
3. 查看PHP错误日志

### 扩展功能
如需要以下功能，请联系开发：
- [ ] 批量盖章
- [ ] 公章水印
- [ ] 公章防伪
- [ ] 骑缝章
- [ ] 电子签名

---

## 🎉 使用示例

### 示例1：简单报价单
```html
<!-- 查看 quotation_with_seal_demo.html -->
最基础的集成示例，适合快速上手
```

### 示例2：复杂多页文档
```html
<!-- 需要在每页都添加公章容器 -->
适合合同等长文档
```

### 示例3：批量文档
```php
<!-- 使用服务器端方案 -->
适合批量生成PDF
```

---

**版本**: 1.0
**更新**: 2025-10-23
**下一步**: 优化您的公章图片，开始使用！🚀
