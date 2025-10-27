<?php
/**
 * 文件名: quote_create.php
 * 版本: 4.0 - 终极版
 * 说明: 创建报价单，支持自定义输入产品（自动保存到产品库）
 * 日期: 2025-10-12
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
$template_result = $conn->query("SELECT * FROM quote_templates WHERE template_code = '$template_type'");
$template = $template_result ? $template_result->fetch_assoc() : null;

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
    <title>创建报价单 - <?php echo $template_names[$template_type] ?? '报价单'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
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
            display: inline-block;
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

        .items-table input:focus, .items-table select:focus {
            outline: none;
            border-color: #667eea;
        }

        .category-select {
            background: #f0f9ff;
            font-weight: 500;
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

        /* 自定义输入样式 */
        .custom-input-row {
            background: #fffbeb !important;
        }

        .custom-inputs {
            display: none;
            padding: 8px;
            background: #fef3c7;
            border-radius: 6px;
            margin-top: 6px;
        }

        .custom-inputs.active {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 8px;
        }

        .custom-inputs input {
            padding: 8px;
            border: 1px solid #fbbf24;
            border-radius: 4px;
            font-size: 13px;
        }

        .custom-inputs input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
        }

        .hidden {
            display: none;
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
        <div class="navbar-brand"><?php echo $template_names[$template_type] ?? '报价单'; ?></div>
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
                                while ($customer = $customers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['company_name']); ?>
                                    <?php if ($customer['contact_name']): ?>
                                        (<?php echo htmlspecialchars($customer['contact_name']); ?>)
                                    <?php endif; ?>
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
                <h2 class="section-title">产品清单</h2>

                <?php if ($template_type == 'assembled_pc'): ?>
                <div class="alert alert-info">
                    <strong>💡 使用说明：</strong><br>
                    • <strong>从产品库选择</strong>：选择配件类型 → 选择具体型号（自动填充价格）<br>
                    • <strong>自定义输入</strong>：选择配件类型 → 在品牌型号选"✏️ 自定义输入" → 填写产品信息<br>
                    • <strong>自动保存</strong>：自定义输入的产品保存报价单时会自动添加到产品库
                </div>
                <?php endif; ?>

                <button type="button" class="btn-add-row" onclick="addRow()">+ 添加行</button>

                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr id="tableHeader"></tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
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

        // 表头配置
        const tableHeaders = {
            'assembled_pc': ['序号', '配件名称', '品牌型号', '规格参数', '单位', '数量', '单价', '小计', '操作'],
            'brand_pc': ['序号', '产品名称', '品牌', '型号', '单位', '质保', '数量', '单价', '小计', '操作'],
            'weak_current': ['序号', '项目名称', '规格/说明', '单位', '数量', '单价', '小计', '备注', '操作'],
            'strong_current': ['序号', '项目名称', '规格/说明', '单位', '数量', '单价', '小计', '备注', '操作']
        };

        // 配件分类映射
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
            76: '机械硬盘',
            41: '显示器',
            45: '键鼠套装'
        };

        // 默认配置
        const defaultAssembledPCConfig = [
            { category: 'CPU处理器', category_id: 11, unit: '个', quantity: 1 },
            { category: '主板', category_id: 12, unit: '个', quantity: 1 },
            { category: '内存', category_id: 13, unit: '条', quantity: 2 },
            { category: '硬盘/SSD', category_id: 14, unit: '个', quantity: 1 },
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
                const response = await fetch('get_products_v2.php?type=all');
                const data = await response.json();
                
                if (data.success) {
                    allProducts = data.products;
                    console.log('✅ 加载产品:', Object.keys(allProducts).length);
                }
            } catch (error) {
                console.error('❌ 加载产品失败:', error);
            }
        }

        // 初始化表格
        function initTable() {
            const headers = tableHeaders[templateType];
            const headerRow = document.getElementById('tableHeader');
            headerRow.innerHTML = headers.map(h => `<th>${h}</th>`).join('');
            
            if (templateType === 'assembled_pc') {
                defaultAssembledPCConfig.forEach(config => addRow(config));
            } else {
                addRow();
            }
        }

        // 构建分类下拉框
        function buildCategorySelect(defaultId = 0) {
            let options = '<option value="">请选择配件类型</option>';
            for (let id in categoryMap) {
                const selected = defaultId == id ? 'selected' : '';
                options += `<option value="${id}" ${selected}>${categoryMap[id]}</option>`;
            }
            return options;
        }

        // 构建产品下拉框
        function buildProductSelect(categoryId) {
            if (!categoryId) {
                return '<option value="">请先选择配件类型</option>';
            }

            const products = Object.values(allProducts).filter(p => p.category_id == categoryId);
            
            let options = `<option value="">请选择具体型号 (${products.length}个可选)</option>`;
            
            products.forEach(p => {
                let stockInfo = '';
                if (p.stock_quantity == 0) {
                    stockInfo = ' [缺货]';
                } else if (p.stock_quantity < 10) {
                    stockInfo = ` [库存:${p.stock_quantity}]`;
                }
                
                options += `<option value="${p.id}" data-name="${p.name}" data-spec="${p.spec || ''}" data-price="${p.default_price || 0}">
                    ${p.name}${stockInfo} - ¥${p.default_price || 0}
                </option>`;
            });
            
            // 添加自定义输入选项
            options += `<option value="custom" style="background: #fef3c7; font-weight: bold;">✏️ 自定义输入（产品库没有的配件）</option>`;
            
            return options;
        }

        // 添加行
        function addRow(config = null) {
            rowIndex++;
            const tbody = document.getElementById('itemsBody');
            const row = tbody.insertRow();
            row.id = 'row_' + rowIndex;

            let cells = '';

            if (templateType === 'assembled_pc') {
                const categoryName = config ? config.category : '';
                const categoryId = config ? config.category_id : 0;
                const quantity = config ? config.quantity : 1;
                const unit = config ? config.unit : '个';

                const categoryOptions = buildCategorySelect(categoryId);
                const productOptions = categoryId ? buildProductSelect(categoryId) : '<option value="">请先选择配件类型</option>';

                cells = `
                    <td style="text-align: center;">${rowIndex}</td>
                    <td style="width: 150px;">
                        <select name="items[${rowIndex}][category_id]" class="category-select" onchange="onCategoryChange(${rowIndex}, this)">
                            ${categoryOptions}
                        </select>
                        <input type="hidden" name="items[${rowIndex}][category]" value="${categoryName}">
                    </td>
                    <td style="width: 320px;">
                        <select name="items[${rowIndex}][product_id]" id="product_select_${rowIndex}" onchange="selectProduct(${rowIndex}, this)">
                            ${productOptions}
                        </select>
                        <input type="hidden" name="items[${rowIndex}][product_name]" id="hidden_name_${rowIndex}">
                        
                        <div class="custom-inputs" id="custom_inputs_${rowIndex}">
                            <input type="text" name="items[${rowIndex}][custom_name]" placeholder="产品名称 *" id="custom_name_${rowIndex}">
                            <input type="text" name="items[${rowIndex}][custom_supplier]" placeholder="品牌（可选）" id="custom_supplier_${rowIndex}">
                        </div>
                    </td>
                    <td><input type="text" name="items[${rowIndex}][spec]" id="spec_${rowIndex}" readonly placeholder="自动填充"></td>
                    <td style="width: 60px;"><input type="text" name="items[${rowIndex}][unit]" value="${unit}" readonly></td>
                    <td style="width: 70px;"><input type="number" name="items[${rowIndex}][quantity]" value="${quantity}" onchange="calculateRow(${rowIndex})"></td>
                    <td style="width: 100px;"><input type="number" name="items[${rowIndex}][price]" id="price_${rowIndex}" step="0.01" onchange="calculateRow(${rowIndex})"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td style="width: 80px;">
                        <button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button>
                    </td>
                `;
            } else {
                cells = `
                    <td style="text-align: center;">${rowIndex}</td>
                    <td><input type="text" name="items[${rowIndex}][product_name]" required></td>
                    <td><input type="text" name="items[${rowIndex}][spec]"></td>
                    <td style="width: 60px;"><input type="text" name="items[${rowIndex}][unit]" value="项"></td>
                    <td style="width: 70px;"><input type="number" name="items[${rowIndex}][quantity]" value="1" onchange="calculateRow(${rowIndex})"></td>
                    <td style="width: 100px;"><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td><input type="text" name="items[${rowIndex}][remark]"></td>
                    <td style="width: 80px;">
                        <button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button>
                    </td>
                `;
            }

            row.innerHTML = cells;
            updateRowNumbers();
        }

        // 更新行号
        function updateRowNumbers() {
            const rows = document.querySelectorAll('#itemsBody tr');
            rows.forEach((row, index) => {
                const seqCell = row.cells[0];
                if (seqCell) {
                    seqCell.textContent = index + 1;
                }
            });
        }

        // 分类改变
        function onCategoryChange(rowId, select) {
            const categoryId = select.value;
            const categoryName = select.options[select.selectedIndex].text;

            const row = document.getElementById('row_' + rowId);
            row.querySelector('input[name*="[category]"]').value = categoryName;

            const productSelect = document.getElementById('product_select_' + rowId);
            if (categoryId) {
                productSelect.innerHTML = buildProductSelect(categoryId);
            } else {
                productSelect.innerHTML = '<option value="">请先选择配件类型</option>';
            }

            // 清空自定义输入
            const customInputs = document.getElementById('custom_inputs_' + rowId);
            if (customInputs) {
                customInputs.classList.remove('active');
            }

            row.classList.remove('custom-input-row');
            row.querySelector('input[name*="[product_name]"]').value = '';
            row.querySelector('input[name*="[spec]"]').value = '';
            row.querySelector('input[name*="[price]"]').value = '';
            document.getElementById('subtotal_' + rowId).textContent = '0.00';

            calculateTotal();
        }

        // 选择产品
        function selectProduct(rowId, select) {
            const option = select.options[select.selectedIndex];
            const row = document.getElementById('row_' + rowId);
            const customInputs = document.getElementById('custom_inputs_' + rowId);
            const specInput = document.getElementById('spec_' + rowId);
            const priceInput = document.getElementById('price_' + rowId);

            // 自定义输入模式
            if (option.value === 'custom') {
                row.classList.add('custom-input-row');
                customInputs.classList.add('active');

                row.querySelector('input[name*="[product_name]"]').value = '';
                specInput.value = '';
                specInput.removeAttribute('readonly');
                specInput.style.background = '#fffbeb';
                priceInput.value = '';
                priceInput.style.background = '#fffbeb';

                const customNameInput = document.getElementById('custom_name_' + rowId);
                setTimeout(() => customNameInput.focus(), 100);

                customNameInput.addEventListener('input', function() {
                    const hiddenName = document.getElementById('hidden_name_' + rowId);
                    if (hiddenName) {
                        hiddenName.value = this.value;
                    }
                });

                return;
            }

            // 隐藏自定义输入
            row.classList.remove('custom-input-row');
            customInputs.classList.remove('active');

            const customNameInput = document.getElementById('custom_name_' + rowId);
            const customSupplierInput = document.getElementById('custom_supplier_' + rowId);
            if (customNameInput) customNameInput.value = '';
            if (customSupplierInput) customSupplierInput.value = '';

            specInput.setAttribute('readonly', true);
            specInput.style.background = '';
            priceInput.style.background = '';

            if (!option.value) {
                row.querySelector('input[name*="[product_name]"]').value = '';
                specInput.value = '';
                priceInput.value = '';
                document.getElementById('subtotal_' + rowId).textContent = '0.00';
                calculateTotal();
                return;
            }

            // 从产品库选择
            const productId = option.value;
            const product = allProducts[productId];

            if (product) {
                row.querySelector('input[name*="[product_name]"]').value = product.name;
                specInput.value = product.spec || '';
                priceInput.value = product.default_price || 0;
                calculateRow(rowId);
            }
        }

        // 删除行
        function deleteRow(index) {
            if (!confirm('确定要删除这一行吗?')) return;

            const row = document.getElementById('row_' + index);
            if (row) {
                row.remove();
                updateRowNumbers();
                calculateTotal();
            }
        }

        // 计算单行
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

            const subtotal = parseFloat(document.getElementById('subtotal').textContent.replace('¥', '')) || 0;
            const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
            formData.append('final_amount', (subtotal - discount).toFixed(2));

            if (!form.checkValidity()) {
                alert('请填写所有必填项!');
                return;
            }

            // 显示加载状态
            const saveButtons = document.querySelectorAll('.btn-primary');
            saveButtons.forEach(btn => {
                btn.disabled = true;
                btn.textContent = '保存中...';
            });

            fetch('quote_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'quotes.php';
                } else {
                    alert('保存失败: ' + data.message);
                    saveButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.textContent = btn.textContent.replace('保存中...', '');
                    });
                }
            })
            .catch(error => {
                console.error('保存错误:', error);
                alert('保存出错: ' + error.message);
                saveButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.textContent = btn.textContent.replace('保存中...', '');
                });
            });
        }

        // 页面加载
        window.onload = async function() {
            console.log('初始化页面...');
            await loadProducts();
            initTable();
            console.log('初始化完成');
        };
    </script>
</body>
</html>