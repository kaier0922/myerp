<?php
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

// 获取产品分类
$categories = $conn->query("SELECT * FROM product_categories ORDER BY parent_id, sort_order");

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
        }

        .items-table input, .items-table select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 13px;
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
                            <?php while ($customer = $customers->fetch_assoc()): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['company_name']); ?>
                                    (<?php echo htmlspecialchars($customer['contact_name']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">报价日期</label>
                        <input type="date" name="quote_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">有效期（天）</label>
                        <input type="number" name="valid_days" class="form-input" value="15">
                    </div>

                    <div class="form-group">
                        <label class="form-label">报价单号</label>
                        <input type="text" name="quote_no" class="form-input" placeholder="留空自动生成">
                    </div>
                </div>

                <!-- 施工类项目特有字段 -->
                <div id="projectFields" class="<?php echo in_array($template_type, ['weak_current', 'strong_current']) ? '' : 'hidden'; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">项目名称</label>
                            <input type="text" name="project_name" class="form-input" placeholder="例如：XX大厦弱电系统改造">
                        </div>

                        <div class="form-group">
                            <label class="form-label">项目地址</label>
                            <input type="text" name="project_location" class="form-input">
                        </div>

                        <div class="form-group">
                            <label class="form-label">工期</label>
                            <input type="text" name="construction_period" class="form-input" placeholder="例如：30个工作日">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 产品/项目明细 -->
            <div class="form-card">
                <h2 class="section-title">
                    <?php 
                    if (in_array($template_type, ['weak_current', 'strong_current'])) {
                        echo '工程项目清单';
                    } else {
                        echo '产品清单';
                    }
                    ?>
                </h2>

                <!-- 兼容性警告 -->
                <?php if ($template_type == 'assembled_pc'): ?>
                <div id="compatibilityWarning" style="display: none; background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px 16px; margin-bottom: 15px; border-radius: 4px; color: #92400e; font-size: 14px;">
                </div>
                <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; margin-bottom: 15px; border-radius: 4px; color: #1e3a8a; font-size: 13px;">
                    💡 <strong>提示：</strong>系统会自动检查CPU、主板、内存的兼容性。选择CPU后，只会显示兼容的主板和内存。
                </div>
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
                        <span>小计：</span>
                        <span id="subtotal">¥0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>折扣金额：</span>
                        <input type="number" name="discount" class="form-input" style="width: 150px; text-align: right;" 
                               value="0" step="0.01" onchange="calculateTotal()">
                    </div>
                    <div class="summary-row total">
                        <span>合计：</span>
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
        let compatibilityRules = {}; // 存储兼容性规则
        let allProducts = {}; // 存储所有产品数据

        // 不同模板的表头配置
        const tableHeaders = {
            'assembled_pc': ['序号', '配件名称', '品牌型号', '规格参数', '单位', '数量', '单价', '小计', '操作'],
            'brand_pc': ['序号', '产品名称', '品牌', '型号', '单位', '质保', '数量', '单价', '小计', '操作'],
            'weak_current': ['序号', '项目名称', '规格/说明', '单位', '数量', '单价', '小计', '备注', '操作'],
            'strong_current': ['序号', '项目名称', '规格/说明', '单位', '数量', '单价', '小计', '备注', '操作']
        };

        // 加载产品数据
        async function loadProducts() {
            if (templateType !== 'assembled_pc') return;
            
            try {
                const response = await fetch('get_products.php?type=hardware&category=11,12,13');
                const data = await response.json();
                
                if (data.success) {
                    allProducts = data.products;
                    console.log('加载产品数据:', allProducts);
                }
            } catch (error) {
                console.error('加载产品失败:', error);
            }
        }

        // 初始化表头
        function initTable() {
            const headers = tableHeaders[templateType];
            const headerRow = document.getElementById('tableHeader');
            headerRow.innerHTML = headers.map(h => `<th>${h}</th>`).join('');
            
            // 添加第一行
            addRow();
        }

        // 检查兼容性
        function checkCompatibility(rowId) {
            if (templateType !== 'assembled_pc') return;
            
            // 获取所有行
            const rows = document.querySelectorAll('#itemsBody tr');
            let cpuTags = null;
            let warnings = [];
            
            // 查找CPU（第一行通常是CPU）
            rows.forEach((row, index) => {
                const productSelect = row.querySelector('select[name*="[product_id]"]');
                if (productSelect && productSelect.value) {
                    const product = allProducts[productSelect.value];
                    if (product && product.tags) {
                        if (index === 0 || product.category_id == 11) { // CPU分类
                            cpuTags = product.tags;
                        }
                    }
                }
            });
            
            if (!cpuTags) return; // 没有选择CPU，不检查
            
            // 检查主板和内存兼容性
            rows.forEach((row, index) => {
                const productSelect = row.querySelector('select[name*="[product_id]"]');
                if (productSelect && productSelect.value) {
                    const product = allProducts[productSelect.value];
                    if (product && product.tags) {
                        // 检查主板兼容性（category_id = 12）
                        if (product.category_id == 12) {
                            const compatible = checkTags(cpuTags, product.tags);
                            if (!compatible) {
                                warnings.push(`⚠️ 主板 "${product.name}" 与所选CPU不兼容！`);
                                row.style.background = '#fee2e2';
                            } else {
                                row.style.background = '';
                            }
                        }
                        
                        // 检查内存兼容性（category_id = 13）
                        if (product.category_id == 13) {
                            const compatible = checkTags(cpuTags, product.tags);
                            if (!compatible) {
                                warnings.push(`⚠️ 内存 "${product.name}" 与所选CPU不兼容！`);
                                row.style.background = '#fee2e2';
                            } else {
                                row.style.background = '';
                            }
                        }
                    }
                }
            });
            
            // 显示警告信息
            const warningDiv = document.getElementById('compatibilityWarning');
            if (warnings.length > 0) {
                warningDiv.innerHTML = warnings.join('<br>');
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        }

        // 检查标签兼容性
        function checkTags(cpuTags, productTags) {
            // CPU标签和产品标签必须有交集
            // 比如：CPU是['Intel', 'LGA1700', 'DDR5']
            // 主板必须包含['Intel', 'LGA1700', 'DDR5']
            // 内存必须包含['DDR5']
            
            const cpuSet = new Set(cpuTags);
            const productSet = new Set(productTags);
            
            // 检查是否有共同标签
            for (let tag of productTags) {
                if (cpuSet.has(tag)) {
                    return true; // 有任何共同标签就认为兼容
                }
            }
            
            return false;
        }

        // 过滤兼容的产品
        function filterCompatibleProducts(categoryId) {
            if (templateType !== 'assembled_pc') return allProducts;
            
            // 获取CPU的标签
            const firstRow = document.querySelector('#itemsBody tr');
            if (!firstRow) return allProducts;
            
            const cpuSelect = firstRow.querySelector('select[name*="[product_id]"]');
            if (!cpuSelect || !cpuSelect.value) return allProducts;
            
            const cpu = allProducts[cpuSelect.value];
            if (!cpu || !cpu.tags) return allProducts;
            
            const cpuTags = cpu.tags;
            
            // 过滤产品
            const filtered = {};
            for (let id in allProducts) {
                const product = allProducts[id];
                // 如果是主板或内存，检查兼容性
                if (product.category_id == 12 || product.category_id == 13) {
                    if (product.tags && checkTags(cpuTags, product.tags)) {
                        filtered[id] = product;
                    }
                } else {
                    filtered[id] = product;
                }
            }
            
            return filtered;
        }

        // 添加行
        function addRow() {
            rowIndex++;
            const tbody = document.getElementById('itemsBody');
            const row = tbody.insertRow();
            row.id = 'row_' + rowIndex;

            let cells = '';
            
            if (templateType === 'assembled_pc') {
                // 获取可用产品（如果不是第一行，需要过滤）
                const products = rowIndex === 1 ? allProducts : filterCompatibleProducts();
                
                // 构建产品选择下拉框
                let productOptions = '<option value="">请选择配件</option>';
                for (let id in products) {
                    const p = products[id];
                    productOptions += `<option value="${id}" data-name="${p.name}" data-spec="${p.spec || ''}" data-price="${p.default_price}">${p.name}</option>`;
                }
                
                cells = `
                    <td style="text-align: center;">${rowIndex}</td>
                    <td>
                        <select name="items[${rowIndex}][product_id]" onchange="selectProduct(${rowIndex}, this)" style="width: 100%;">
                            ${productOptions}
                        </select>
                        <input type="hidden" name="items[${rowIndex}][product_name]">
                    </td>
                    <td><input type="text" name="items[${rowIndex}][brand]" readonly></td>
                    <td><input type="text" name="items[${rowIndex}][spec]" readonly></td>
                    <td><input type="text" name="items[${rowIndex}][unit]" value="个" style="width: 60px;" readonly></td>
                    <td><input type="number" name="items[${rowIndex}][quantity]" value="1" onchange="calculateRow(${rowIndex})" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})" style="width: 100px;" readonly></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td><button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button></td>
                `;
            } else if (templateType === 'brand_pc') {
                cells = `
                    <td style="text-align: center;">${rowIndex}</td>
                    <td><input type="text" name="items[${rowIndex}][product_name]" required></td>
                    <td><input type="text" name="items[${rowIndex}][brand]"></td>
                    <td><input type="text" name="items[${rowIndex}][model]"></td>
                    <td><input type="text" name="items[${rowIndex}][unit]" value="台" style="width: 60px;"></td>
                    <td><input type="text" name="items[${rowIndex}][warranty]" value="1年" style="width: 80px;"></td>
                    <td><input type="number" name="items[${rowIndex}][quantity]" value="1" onchange="calculateRow(${rowIndex})" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})" style="width: 100px;"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td><button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button></td>
                `;
            } else {
                // 施工类
                cells = `
                    <td style="text-align: center;">${rowIndex}</td>
                    <td><input type="text" name="items[${rowIndex}][product_name]" required></td>
                    <td><input type="text" name="items[${rowIndex}][spec]"></td>
                    <td><input type="text" name="items[${rowIndex}][unit]" value="项" style="width: 60px;"></td>
                    <td><input type="number" name="items[${rowIndex}][quantity]" value="1" onchange="calculateRow(${rowIndex})" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowIndex}][price]" step="0.01" onchange="calculateRow(${rowIndex})" style="width: 100px;"></td>
                    <td style="text-align: right;"><span id="subtotal_${rowIndex}">0.00</span></td>
                    <td><input type="text" name="items[${rowIndex}][remark]"></td>
                    <td><button type="button" class="btn-delete-row" onclick="deleteRow(${rowIndex})">删除</button></td>
                `;
            }

            row.innerHTML = cells;
        }

        // 选择产品时自动填充信息
        function selectProduct(rowId, select) {
            const option = select.options[select.selectedIndex];
            if (!option.value) return;
            
            const productId = option.value;
            const product = allProducts[productId];
            
            if (product) {
                const row = document.getElementById('row_' + rowId);
                
                // 填充产品信息
                row.querySelector('input[name*="[product_name]"]').value = product.name;
                row.querySelector('input[name*="[brand]"]').value = product.name.split(' ')[0] || '';
                row.querySelector('input[name*="[spec]"]').value = product.spec || '';
                row.querySelector('input[name*="[price]"]').value = product.default_price || 0;
                
                // 自动计算
                calculateRow(rowId);
                
                // 检查兼容性
                checkCompatibility(rowId);
            }
        }

        // 删除行
        function deleteRow(index) {
            const row = document.getElementById('row_' + index);
            if (row) {
                row.remove();
                calculateTotal();
                checkCompatibility();
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

            // 计算最终金额
            const subtotal = parseFloat(document.getElementById('subtotal').textContent.replace('¥', '')) || 0;
            const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
            formData.append('final_amount', (subtotal - discount).toFixed(2));

            fetch('quote_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('保存成功！');
                    window.location.href = 'quotes.php';
                } else {
                    alert('保存失败：' + data.message);
                }
            })
            .catch(error => {
                alert('网络错误：' + error);
            });
        }

        // 页面加载时初始化
        window.onload = async function() {
            await loadProducts(); // 先加载产品数据
            initTable(); // 再初始化表格
        };
    </script>
</body>
</html>