<?php
/**
 * 文件名: quote_create_v3.php
 * 版本: v3.2
 * 说明: 创建报价单页面，支持智能配件兼容性检测、行拖拽排序、机械硬盘
 * 作者: System
 * 日期: 2025-10-12
 * 更新:
 *   - v3.2: 修复机械硬盘分类显示问题（ID=76）
 *   - v3.1: 增加行拖拽排序功能，支持在任意位置插入新行
 *   - v3.0: 增加智能兼容性检测功能
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$template_type = $_GET['template'] ?? 'assembled_pc';

$conn = getDBConnection();

// 获取客户列表
$customers = $conn->query("SELECT id, company_name, contact_name FROM customers ORDER BY company_name");

// 获取模板配置
$template = $conn->query("SELECT * FROM quote_templates WHERE template_code = '$template_type'")->fetch_assoc();

$conn->close();

$template_names = [
    'assembled_pc' => '组装电脑报价单',
    'brand_pc' => '品牌整机报价单',
    'weak_current' => '弱电工程报价单',
    'strong_current' => '强电工程报价单'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建报价单 - <?php echo $template_names[$template_type]; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-brand {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }

        .navbar-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-secondary {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #718096;
            font-size: 14px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-grid.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .form-label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-input, .form-select, .form-textarea {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* 产品列表表格 */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background: #f7fafc;
            padding: 12px 8px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .items-table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .items-table input, .items-table select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .category-select {
            background: #f0f9ff;
            font-weight: 500;
        }

        .items-table tr.incompatible {
            background: #fee2e2 !important;
        }

        .items-table tr.compatible {
            background: #d1fae5 !important;
        }

        .btn-add-row {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-add-row:hover {
            background: #059669;
        }

        .btn-delete-row {
            padding: 4px 8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-delete-row:hover {
            background: #dc2626;
        }

        .summary-box {
            background: #f7fafc;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            color: #1a202c;
            padding-top: 12px;
            border-top: 2px solid #e2e8f0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .hidden {
            display: none;
        }

        /* 拖拽相关样式 */
        .drag-handle {
            cursor: move;
            padding: 8px;
            text-align: center;
            color: #94a3b8;
            font-size: 18px;
            user-select: none;
        }
        
        .drag-handle:hover {
            color: #667eea;
            background: #f7fafc;
        }
        
        .dragging {
            opacity: 0.5;
            background: #f0f9ff;
            border: 2px dashed #667eea;
        }
        
        .drag-over {
            border-top: 3px solid #667eea;
        }
        
        /* 插入按钮 */
        .btn-insert {
            padding: 2px 8px;
            background: #f0f9ff;
            color: #667eea;
            border: 1px solid #667eea;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-right: 4px;
        }
        
        .btn-insert:hover {
            background: #667eea;
            color: white;
        }
        
        /* 操作按钮组 */
        .action-buttons {
            display: flex;
            gap: 4px;
            align-items: center;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><?php echo $template_names[$template_type]; ?></div>
        <div class="navbar-actions">
            <a href="quotes.php" class="btn btn-secondary">取消</a>
            <button class="btn btn-primary" onclick="saveQuote('草稿')">保存草稿</button>
            <button class="btn btn-primary" onclick="saveQuote('已发送')">保存并发送</button>
        </div>
    </nav>

    <main class="main-content">
        <form id="quoteForm">
            <input type="hidden" name="template_type" value="<?php echo $template_type; ?>">
            
            <!-- 基本信息 -->
            <div class="form-card">
                <h2 class="section-title">基本信息</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">客户名称</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">请选择客户</option>
                            <?php 
                            if ($customers) {
                                $customers->data_seek(0);
                                while ($customer = $customers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['company_name']); ?>
                                    (<?php echo htmlspecialchars($customer['contact_name']); ?>)
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">报价日期</label>
                        <input type="date" name="quote_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">有效期(天)</label>
                        <input type="number" name="valid_days" class="form-input" value="15">
                    </div>

                    <div class="form-group">
                        <label class="form-label">报价单号</label>
                        <input type="text" name="quote_no" class="form-input" placeholder="留空自动生成">
                    </div>
                </div>
            </div>

            <!-- 产品明细 -->
            <div class="form-card">
                <h2 class="section-title">
                    <?php 
                    if ($template_type == 'assembled_pc') {
                        echo '配件清单';
                    } else {
                        echo '产品清单';
                    }
                    ?>
                </h2>

                <!-- 兼容性提示 -->
                <?php if ($template_type == 'assembled_pc'): ?>
                <div class="alert alert-info">
                    <strong>💡 智能兼容性检测：</strong>
                    <br>• 第一步：点击"配件名称"下拉选择配件类型（CPU、主板、内存等）
                    <br>• 第二步：点击"品牌型号"下拉选择具体型号
                    <br>• 🎯 <strong>拖拽排序</strong>：按住 ⋮⋮ 图标可拖动行调整顺序
                    <br>• ➕ <strong>插入行</strong>：点击"+ 插入"可在当前行下方添加新行
                </div>
                <div id="compatibilityStatus" class="hidden"></div>
                <?php endif; ?>

                <button type="button" class="btn-add-row" onclick="addRow()">+ 添加行</button>

                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr id="tableHeader">
                            <!-- 动态生成表头 -->
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- 动态生成行 -->
                    </tbody>
                </table>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>小计:</span>
                        <span id="subtotal">¥0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>折扣金额:</span>
                        <input type="number" name="discount" class="form-input" style="width: 150px; text-align: right;" 
                               value="0" step="0.01" onchange="calculateTotal()">
                    </div>
                    <div class="summary-row total">
                        <span>合计:</span>
                        <span id="totalAmount">¥0.00</span>
                    </div>
                </div>
            </div>

            <!-- 条款说明 -->
            <div class="form-card">
                <h2 class="section-title">条款说明</h2>
                <div class="form-group">
                    <textarea name="terms" class="form-textarea" rows="6"><?php echo htmlspecialchars($template['default_terms'] ?? ''); ?></textarea>
                </div>
            </div>
        </form>
    </main>

    <script>
        const templateType = '<?php echo $template_type; ?>';
        let rowIndex = 0;
        let allProducts = {};
        let selectedCPU = null;
        let selectedMotherboard = null;
        let draggedRow = null;

        // 不同模板的表头配置
        const tableHeaders = {
            'assembled_pc': ['⋮⋮', '序号', '配件名称', '品牌型号', '规格参数', '单位', '数量', '单价', '小计', '操作'],
            'brand_pc': ['⋮⋮', '序号', '产品名称', '品牌', '型号', '单位', '质保', '数量', '单价', '小计', '操作'],
            'weak_current': ['⋮⋮', '序号', '项目名称', '规格/说明', '单位', '数量', '单价', '小计', '备注', '操作'],
            'strong_current': ['⋮⋮', '序号', '项目名称', '规格/说明', '单位', '数量', '单价', '小计', '备注', '操作']
        };

        // ========== 关键修复：配件分类映射 ==========
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
            76: '机械硬盘',        // ← 新增：机械硬盘（ID=76）
            41: '显示器',
            45: '键鼠套装'
        };

        // ========== 关键修复：默认配置模板 ==========
        const defaultAssembledPCConfig = [
            { category: 'CPU处理器', category_id: 11, unit: '个', quantity: 1 },
            { category: '主板', category_id: 12, unit: '个', quantity: 1 },
            { category: '内存', category_id: 13, unit: '条', quantity: 2 },
            { category: '硬盘/SSD', category_id: 14, unit: '个', quantity: 1 },
            { category: '机械硬盘', category_id: 76, unit: '个', quantity: 1 },  // ← 新增
            { category: '显卡', category_id: 15, unit: '个', quantity: 1 },
            { category: '电源', category_id: 16, unit: '个', quantity: 1 },
            { category: '机箱', category_id: 17, unit: '个', quantity: 1 },
            { category: '散热器', category_id: 18, unit: '个', quantity: 1 },
            { category: '显示器', category_id: 41, unit: '台', quantity: 1 },
            { category: '键鼠套装', category_id: 45, unit: '套', quantity: 1 }
        ];

        // 加载产品数据
        async function loadProducts() {
            try {
                console.log('正在加载产品数据...');
                const response = await fetch('get_products_v2.php?type=all');
                
                if (!response.ok) {
                    throw new Error('HTTP错误: ' + response.status);
                }
                
                const data = await response.json();
                console.log('服务器响应:', data);
                
                if (data.success) {
                    allProducts = data.products;
                    console.log('✅ 加载产品数据成功:', Object.keys(allProducts).length, '个产品');
                    
                    // 显示各分类的产品数量
                    const categoryCounts = {};
                    for (let id in allProducts) {
                        const catId = allProducts[id].category_id;
                        const catName = categoryMap[catId] || allProducts[id].category_name || catId;
                        categoryCounts[catName] = (categoryCounts[catName] || 0) + 1;
                    }
                    console.log('各分类产品数量:', categoryCounts);
                } else {
                    console.error('❌ 加载产品失败:', data.message);
                    alert('加载产品失败: ' + data.message);
                }
            } catch (error) {
                console.error('❌ 加载产品异常:', error);
                alert('加载产品出错: ' + error.message);
            }
        }

        // 初始化表格
        function initTable() {
            const headers = tableHeaders[templateType];
            const headerRow = document.getElementById('tableHeader');
            headerRow.innerHTML = headers.map(h => `<th>${h}</th>`).join('');
            
            if (templateType === 'assembled_pc') {
                loadDefaultConfig();
            } else {
                addRow();
            }
        }

        // 加载默认配置
        function loadDefaultConfig() {
            console.log('加载默认配置...');
            defaultAssembledPCConfig.forEach(config => {
                addRow(config);
            });
            
            showStatus('已加载默认配置，请选择具体型号', 'success');
        }

        // 显示状态消息
        function showStatus(message, type = 'info') {
            const statusDiv = document.getElementById('compatibilityStatus');
            if (!statusDiv) return;
            
            statusDiv.className = `alert alert-${type}`;
            statusDiv.innerHTML = message;
            statusDiv.classList.remove('hidden');
            
            if (type === 'success') {
                setTimeout(() => {
                    statusDiv.classList.add('hidden');
                }, 3000);
            }
        }

        // 检查兼容性
        function checkCompatibility() {
            if (templateType !== 'assembled_pc') return;
            
            const rows = document.querySelectorAll('#itemsBody tr');
            let warnings = [];
            
            selectedCPU = null;
            selectedMotherboard = null;
            
            rows.forEach(row => {
                const productSelect = row.querySelector('select[name*="[product_id]"]');
                if (productSelect && productSelect.value) {
                    const product = allProducts[productSelect.value];
                    if (product && product.category_id == 11) {
                        selectedCPU = product;
                    }
                    if (product && product.category_id == 12) {
                        selectedMotherboard = product;
                    }
                }
            });
            
            if (!selectedCPU) {
                showStatus('⚠️ 请先选择CPU，系统将自动过滤兼容的主板和内存', 'warning');
                return;
            }
            
            rows.forEach(row => {
                const productSelect = row.querySelector('select[name*="[product_id]"]');
                if (productSelect && productSelect.value) {
                    const product = allProducts[productSelect.value];
                    if (product) {
                        row.classList.remove('incompatible', 'compatible');
                        
                        if (product.category_id == 12) {
                            const compatible = isCompatible(selectedCPU, product);
                            if (!compatible) {
                                warnings.push(`主板 "${product.name}" 与所选CPU不兼容`);
                                row.classList.add('incompatible');
                            } else {
                                row.classList.add('compatible');
                            }
                        }
                        
                        if (product.category_id == 13) {
                            const compatible = isCompatible(selectedCPU, product);
                            if (!compatible) {
                                warnings.push(`内存 "${product.name}" 与所选CPU不兼容`);
                                row.classList.add('incompatible');
                            } else {
                                row.classList.add('compatible');
                            }
                        }
                    }
                }
            });
            
            if (warnings.length > 0) {
                showStatus('⚠️ 检测到兼容性问题：<br>' + warnings.join('<br>'), 'warning');
            } else if (selectedCPU) {
                showStatus('✅ 所有配件兼容性检查通过', 'success');
            }
        }

        // 检查两个产品是否兼容（基于tags）
        function isCompatible(cpu, product) {
            if (!cpu || !product || !cpu.tags || !product.tags) return true;
            
            const cpuTags = cpu.tags.split(',').map(t => t.trim());
            const productTags = product.tags.split(',').map(t => t.trim());
            
            return cpuTags.some(tag => productTags.includes(tag));
        }

        // 过滤兼容的产品（根据分类ID）
        function getCompatibleProducts(categoryId) {
            const filtered = {};
            
            for (let id in allProducts) {
                const product = allProducts[id];
                
                if (product.category_id != categoryId) continue;
                
                if ((categoryId == 12 || categoryId == 13) && selectedCPU) {
                    if (isCompatible(selectedCPU, product)) {
                        filtered[id] = product;
                    }
                } else {
                    filtered[id] = product;
                }
            }
            
            return filtered;
        }

        // 构建分类选择下拉框
        function buildCategorySelect(defaultCategoryId = 0) {
            let options = '<option value="">请选择配件类型</option>';
            for (let catId in categoryMap) {
                const selected = defaultCategoryId == catId ? 'selected' : '';
                options += `<option value="${catId}" ${selected}>${categoryMap[catId]}</option>`;
            }
            return options;
        }

        // 构建产品选择下拉框（根据分类）
        function buildProductSelect(categoryId, rowId) {
            if (!categoryId) {
                return '<option value="">请先选择配件类型</option>';
            }
            
            const products = getCompatibleProducts(categoryId);
            const count = Object.keys(products).length;
            
            let options = `<option value="">请选择具体型号 (${count}个可选)</option>`;
            
            for (let id in products) {
                const p = products[id];
                let stockInfo = '';
                if (p.stock_quantity !== undefined) {
                    if (p.stock_quantity == 0) {
                        stockInfo = ' [缺货]';
                    } else if (p.stock_quantity < 10) {
                        stockInfo = ` [库存:${p.stock_quantity}]`;
                    }
                }
                
                options += `<option value="${id}" 
                    data-name="${p.name}" 
                    data-spec="${p.spec || ''}" 
                    data-price="${p.default_price || 0}">
                    ${p.name}${stockInfo} - ¥${p.default_price || 0}
                </option>`;
            }
            
            return options;
        }

        // 添加行
        function addRow(config = null, insertAfterRowId = null) {
            rowIndex++;
            const tbody = document.getElementById('itemsBody');
            const row = tbody.insertRow(insertAfterRowId ? -1 : -1);
            row.id = 'row_' + rowIndex;
            
            row.draggable = true;
            row.addEventListener('dragstart', handleDragStart);
            row.addEventListener('dragover', handleDragOver);
            row.addEventListener('drop', handleDrop);
            row.addEventListener('dragend', handleDragEnd);
            row.addEventListener('dragenter', handleDragEnter);
            row.addEventListener('dragleave', handleDragLeave);

            let cells = '';
            
            if (templateType === 'assembled_pc') {
                const categoryName = config ? config.category : '';
                const categoryId = config ? config.category_id : 0;
                const quantity = config ? config.quantity : 1;
                const unit = config ? config.unit : '个';
                
                const categoryOptions = buildCategorySelect(categoryId);
                const productOptions = categoryId ? buildProductSelect(categoryId, rowIndex) : '<option value="">请先选择配件类型</option>';
                
                cells = `
                    <td class="drag-handle" title="拖动排序">⋮⋮</td>
                    <td style="text-align: center;">${rowIndex}</td>
                    <td style="width: 180px;">
                        <select name="items[${rowIndex}][category_id]" class="category-select" onchange="onCategoryChange(${rowIndex}, this)" style="width: 100%;">
                            ${categoryOptions}
                        </select>
                        <input type="hidden" name="items[${rowIndex}][category]" value="${categoryName}">
                    </td>
                    <td style="width: 300px;">
                        <select name="items[${rowIndex}][product_id]" id="product_select_${rowIndex}" onchange="selectProduct(${rowIndex}, this)" style="width: 100%;">
                            ${productOptions}
                        </select>
                        <input type="hidden" name="items[${rowIndex}][product_name]">
                    </td>
                    <td><input type="text" name="items[${rowIndex}][spec]" readonly placeholder="自动填充"></td>
                    <td><input type="text" name="items[${rowIndex}][unit]" value="${unit}" style="width: 60px;" readonly></td>
                    <td><input type="number" name="items[${rowIndex}][quantity]" value="${quantity}" onchange="calculateRow(${rowIndex})" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})" style="width: 100px;"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn-insert" onclick="insertRowAfter(${rowIndex})" title="在下方插入行">+ 插入</button>
                            <button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button>
                        </div>
                    </td>
                `;
            } else if (templateType === 'brand_pc') {
                cells = `
                    <td class="drag-handle" title="拖动排序">⋮⋮</td>
                    <td style="text-align: center;">${rowIndex}</td>
                    <td><input type="text" name="items[${rowIndex}][product_name]" required></td>
                    <td><input type="text" name="items[${rowIndex}][brand]"></td>
                    <td><input type="text" name="items[${rowIndex}][model]"></td>
                    <td><input type="text" name="items[${rowIndex}][unit]" value="台" style="width: 60px;"></td>
                    <td><input type="text" name="items[${rowIndex}][warranty]" value="1年" style="width: 80px;"></td>
                    <td><input type="number" name="items[${rowIndex}][quantity]" value="1" onchange="calculateRow(${rowIndex})" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})" style="width: 100px;"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn-insert" onclick="insertRowAfter(${rowIndex})" title="在下方插入行">+ 插入</button>
                            <button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button>
                        </div>
                    </td>
                `;
            } else {
                cells = `
                    <td class="drag-handle" title="拖动排序">⋮⋮</td>
                    <td style="text-align: center;">${rowIndex}</td>
                    <td><input type="text" name="items[${rowIndex}][product_name]" required></td>
                    <td><input type="text" name="items[${rowIndex}][spec]"></td>
                    <td><input type="text" name="items[${rowIndex}][unit]" value="项" style="width: 60px;"></td>
                    <td><input type="number" name="items[${rowIndex}][quantity]" value="1" onchange="calculateRow(${rowIndex})" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})" style="width: 100px;"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td><input type="text" name="items[${rowIndex}][remark]"></td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn-insert" onclick="insertRowAfter(${rowIndex})" title="在下方插入行">+ 插入</button>
                            <button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button>
                        </div>
                    </td>
                `;
            }

            row.innerHTML = cells;
            
            if (insertAfterRowId) {
                const targetRow = document.getElementById('row_' + insertAfterRowId);
                if (targetRow && targetRow.nextSibling) {
                    tbody.insertBefore(row, targetRow.nextSibling);
                }
            }
            
            updateRowNumbers();
        }
        
        // 在指定行后插入新行
        function insertRowAfter(afterRowId) {
            const currentRow = document.getElementById('row_' + afterRowId);
            let config = null;
            
            if (templateType === 'assembled_pc') {
                const categorySelect = currentRow.querySelector('.category-select');
                if (categorySelect && categorySelect.value) {
                    config = {
                        category: categorySelect.options[categorySelect.selectedIndex].text,
                        category_id: categorySelect.value,
                        unit: currentRow.querySelector('input[name*="[unit]"]').value || '个',
                        quantity: 1
                    };
                }
            }
            
            addRow(config, afterRowId);
        }
        
        // 拖拽事件处理
        function handleDragStart(e) {
            draggedRow = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }
        
        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }
        
        function handleDragEnter(e) {
            if (this !== draggedRow) {
                this.classList.add('drag-over');
            }
        }
        
        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }
        
        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedRow !== this) {
                const tbody = document.getElementById('itemsBody');
                const allRows = Array.from(tbody.querySelectorAll('tr'));
                const draggedIndex = allRows.indexOf(draggedRow);
                const targetIndex = allRows.indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    tbody.insertBefore(draggedRow, this.nextSibling);
                } else {
                    tbody.insertBefore(draggedRow, this);
                }
                
                updateRowNumbers();
                setTimeout(() => checkCompatibility(), 100);
            }
            
            this.classList.remove('drag-over');
            return false;
        }
        
        function handleDragEnd(e) {
            this.classList.remove('dragging');
            
            document.querySelectorAll('.drag-over').forEach(row => {
                row.classList.remove('drag-over');
            });
            
            draggedRow = null;
        }
        
        // 更新所有行的序号
        function updateRowNumbers() {
            const rows = document.querySelectorAll('#itemsBody tr');
            rows.forEach((row, index) => {
                const seqCell = row.cells[1];
                if (seqCell) {
                    seqCell.textContent = index + 1;
                }
            });
        }
        
        // 当配件分类改变时触发
        function onCategoryChange(rowId, select) {
            const categoryId = select.value;
            const categoryName = select.options[select.selectedIndex].text;
            
            const row = document.getElementById('row_' + rowId);
            row.querySelector('input[name*="[category]"]').value = categoryName;
            
            const productSelect = document.getElementById('product_select_' + rowId);
            if (categoryId) {
                productSelect.innerHTML = buildProductSelect(categoryId, rowId);
            } else {
                productSelect.innerHTML = '<option value="">请先选择配件类型</option>';
            }
            
            row.querySelector('input[name*="[product_name]"]').value = '';
            row.querySelector('input[name*="[spec]"]').value = '';
            row.querySelector('input[name*="[price]"]').value = '';
            document.getElementById('subtotal_' + rowId).textContent = '0.00';
            
            calculateTotal();
        }

        // 选择产品时自动填充信息
        function selectProduct(rowId, select) {
            const option = select.options[select.selectedIndex];
            if (!option.value) return;
            
            const productId = option.value;
            const product = allProducts[productId];
            
            if (product) {
                const row = document.getElementById('row_' + rowId);
                
                row.querySelector('input[name*="[product_name]"]').value = product.name;
                row.querySelector('input[name*="[spec]"]').value = product.spec || '';
                row.querySelector('input[name*="[price]"]').value = product.default_price || 0;
                
                calculateRow(rowId);
                
                if (product.category_id == 11) {
                    selectedCPU = product;
                    updateCompatibleOptions();
                    showStatus('✅ CPU已选择: ' + product.name + '，主板和内存选项已更新', 'success');
                }
                
                setTimeout(() => checkCompatibility(), 100);
            }
        }
        
        // 更新兼容的选项（当选择了CPU后调用）
        function updateCompatibleOptions() {
            if (!selectedCPU) return;
            
            const rows = document.querySelectorAll('#itemsBody tr');
            rows.forEach(row => {
                const categorySelect = row.querySelector('.category-select');
                if (!categorySelect) return;
                
                const categoryId = categorySelect.value;
                
                if (categoryId == 12 || categoryId == 13) {
                    const rowId = row.id.replace('row_', '');
                    const productSelect = document.getElementById('product_select_' + rowId);
                    if (productSelect) {
                        const currentValue = productSelect.value;
                        productSelect.innerHTML = buildProductSelect(categoryId, rowId);
                        if (currentValue) {
                            productSelect.value = currentValue;
                        }
                    }
                }
            });
        }

        // 删除行
        function deleteRow(index) {
            if (!confirm('确定要删除这一行吗?')) return;
            
            const row = document.getElementById('row_' + index);
            if (row) {
                row.remove();
                updateRowNumbers();
                calculateTotal();
                
                setTimeout(() => checkCompatibility(), 100);
            }
        }

        // 计算单行小计
        function calculateRow(index) {
            const qty = parseFloat(document.querySelector(`input[name="items[${index}][quantity]"]`).value) || 0;
            const price = parseFloat(document.querySelector(`input[name="items[${index}][price]"]`).value) || 0;
            const subtotal = qty * price;
            
            document.getElementById('subtotal_' + index).textContent = subtotal.toFixed(2);
            calculateTotal();
        }

        // 计算总计
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('[id^="subtotal_"]').forEach(span => {
                total += parseFloat(span.textContent) || 0;
            });

            const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
            const finalAmount = total - discount;

            document.getElementById('subtotal').textContent = '¥' + total.toFixed(2);
            document.getElementById('totalAmount').textContent = '¥' + finalAmount.toFixed(2);
        }

        // 保存报价单
        function saveQuote(status) {
            const form = document.getElementById('quoteForm');
            const formData = new FormData(form);
            formData.append('status', status);
            formData.append('action', 'create');

            const subtotal = parseFloat(document.getElementById('subtotal').textContent.replace('¥', '')) || 0;
            const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
            formData.append('final_amount', (subtotal - discount).toFixed(2));

            if (!form.checkValidity()) {
                alert('请填写所有必填项!');
                return;
            }

            fetch('quote_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('保存成功!');
                    window.location.href = 'quotes.php';
                } else {
                    alert('保存失败:' + data.message);
                }
            })
            .catch(error => {
                alert('网络错误:' + error);
            });
        }

        // 页面加载时初始化
        window.onload = async function() {
            console.log('页面加载中...');
            await loadProducts();
            initTable();
            console.log('初始化完成');
        };
    </script>
</body>
</html>