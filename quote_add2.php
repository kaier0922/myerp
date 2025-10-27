<?php
/**
 * =====================================================
 * 文件名：quote_add.php
 * 功能：新增报价单
 * 描述：创建新的报价单，支持多种模板类型，动态添加产品明细
 * =====================================================
 */

session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$conn = getDBConnection();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : 0;
    $template_type = $_POST['template_type'];
    $quote_date = $_POST['quote_date'];
    $valid_days = $_POST['valid_days'];
    $status = $_POST['status'] ?? '草稿';
    
    // 工程类特有字段
    $project_name = $_POST['project_name'] ?? '';
    $project_location = $_POST['project_location'] ?? '';
    $construction_period = $_POST['construction_period'] ?? '';
    
    $terms = $_POST['terms'] ?? '';
    $discount = $_POST['discount'] ?? 0;
    
    // 计算总金额
    $items = json_decode($_POST['items_data'], true);
    $total_amount = 0;
    $total_cost = 0;
    
    foreach ($items as $item) {
        $total_amount += floatval($item['subtotal']);
        $total_cost += floatval($item['cost_subtotal']);
    }
    
    $final_amount = $total_amount - $discount;
    
    // 生成报价单号
    $quote_no = 'Q' . date('YmdHis');
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 插入报价单主表
        $sql = "INSERT INTO quotes 
                (user_id, customer_id, project_name, project_location, construction_period,
                 quote_no, template_type, quote_date, valid_days, status, 
                 final_amount, terms, discount, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssssissd", 
            $user_id, $customer_id, $project_name, $project_location, $construction_period,
            $quote_no, $template_type, $quote_date, $valid_days, $status,
            $final_amount, $terms, $discount
        );
        $stmt->execute();
        $quote_id = $conn->insert_id;
        
        // 插入报价单明细
        $item_sql = "INSERT INTO quote_items 
                     (quote_id, seq, category, product_id, product_name, brand, model, spec, 
                      unit, warranty, quantity, price, cost, subtotal, cost_subtotal, remark) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $item_stmt = $conn->prepare($item_sql);
        
        foreach ($items as $index => $item) {
            $seq = $index + 1;
            $product_id = !empty($item['product_id']) ? $item['product_id'] : null;
            
            $item_stmt->bind_param("iiiissssssidddds",
                $quote_id, $seq, $item['category'], $product_id, 
                $item['product_name'], $item['brand'], $item['model'], $item['spec'],
                $item['unit'], $item['warranty'], $item['quantity'], 
                $item['price'], $item['cost'], $item['subtotal'], $item['cost_subtotal'],
                $item['remark']
            );
            $item_stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['message'] = '报价单创建成功！';
        header('Location: quote_view.php?id=' . $quote_id);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = '创建失败：' . $e->getMessage();
    }
}

// 获取客户列表
$customers = $conn->query("
    SELECT id, company_name, contact_name, phone 
    FROM customers 
    ORDER BY company_name
");

// 获取产品分类
$categories = $conn->query("
    SELECT id, parent_id, name 
    FROM product_categories 
    WHERE parent_id = 0 
    ORDER BY sort_order
");

// 获取所有产品
$products = $conn->query("
    SELECT p.*, pc.name as category_name 
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.is_active = 1
    ORDER BY pc.sort_order, p.name
");

// 获取模板条款
$templates = $conn->query("
    SELECT template_type, default_terms 
    FROM quote_templates 
    WHERE is_active = 1
");

$template_terms = [];
while ($template = $templates->fetch_assoc()) {
    $template_terms[$template['template_type']] = $template['default_terms'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增报价单 - 报价管理系统</title>
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
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            text-decoration: none;
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

        .form-header {
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .required {
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
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
            min-height: 100px;
            resize: vertical;
        }

        /* 产品明细表格 */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .items-table th {
            background: #f7fafc;
            padding: 12px 8px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        .items-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #e2e8f0;
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

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1.5;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .summary-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
        }

        .summary-row.total {
            border-top: 2px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        .product-search {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            display: none;
        }

        .search-results.active {
            display: block;
        }

        .search-result-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f7fafc;
        }

        .search-result-item:hover {
            background: #f7fafc;
        }

        .search-result-name {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .search-result-info {
            font-size: 12px;
            color: #718096;
        }

        .template-fields {
            display: none;
        }

        .template-fields.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <a href="quotes.php" class="navbar-brand">
            <span>💰</span>
            <span>新增报价单</span>
        </a>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="form-card">
            <div class="form-header">
                <h1 class="form-title">新增报价单</h1>
            </div>

            <!-- 错误提示 -->
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- 报价单表单 -->
            <form method="POST" action="" id="quoteForm">
                <!-- 基本信息 -->
                <div class="form-section">
                    <h3 class="section-title">基本信息</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">选择客户</label>
                            <select name="customer_id" class="form-select">
                                <option value="">-- 选择客户（可选）--</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>">
                                        <?php echo htmlspecialchars($customer['company_name']); ?>
                                        <?php if ($customer['contact_name']): ?>
                                            - <?php echo htmlspecialchars($customer['contact_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">模板类型 <span class="required">*</span></label>
                            <select name="template_type" class="form-select" id="templateType" required onchange="handleTemplateChange()">
                                <option value="">-- 选择模板类型 --</option>
                                <option value="assembled_pc">组装电脑报价单</option>
                                <option value="brand_pc">品牌整机报价单</option>
                                <option value="weak_current">弱电工程报价单</option>
                                <option value="strong_current">强电工程报价单</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">报价日期 <span class="required">*</span></label>
                            <input type="date" name="quote_date" class="form-input" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">有效期（天数）<span class="required">*</span></label>
                            <input type="number" name="valid_days" class="form-input" 
                                   value="15" min="1" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">状态 <span class="required">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="草稿" selected>草稿</option>
                                <option value="已发送">已发送</option>
                            </select>
                        </div>
                    </div>

                    <!-- 工程类特有字段 -->
                    <div id="projectFields" class="template-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">项目名称</label>
                                <input type="text" name="project_name" class="form-input" 
                                       placeholder="如：办公楼综合布线工程">
                            </div>

                            <div class="form-group">
                                <label class="form-label">项目地点</label>
                                <input type="text" name="project_location" class="form-input" 
                                       placeholder="如：深圳市南山区科技园">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">施工周期</label>
                            <input type="text" name="construction_period" class="form-input" 
                                   placeholder="如：30个工作日">
                        </div>
                    </div>
                </div>

                <!-- 产品明细 -->
                <div class="form-section">
                    <h3 class="section-title">产品明细</h3>
                    
                    <div style="margin-bottom: 16px;">
                        <button type="button" class="btn btn-success btn-sm" onclick="addItem()">+ 添加产品</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="showProductSearch()">📦 从产品库选择</button>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">序号</th>
                                    <th style="width: 150px;">产品名称 <span class="required">*</span></th>
                                    <th style="width: 100px;">品牌</th>
                                    <th style="width: 100px;">型号</th>
                                    <th style="width: 120px;">规格</th>
                                    <th style="width: 60px;">单位</th>
                                    <th style="width: 80px;">数量 <span class="required">*</span></th>
                                    <th style="width: 100px;">单价 <span class="required">*</span></th>
                                    <th style="width: 100px;">成本价</th>
                                    <th style="width: 100px;">小计</th>
                                    <th style="width: 120px;">备注</th>
                                    <th style="width: 60px;">操作</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <!-- 动态添加的行 -->
                            </tbody>
                        </table>
                    </div>

                    <!-- 汇总信息 -->
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>产品小计：</span>
                            <span id="subtotalAmount">¥0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>优惠折扣：</span>
                            <input type="number" name="discount" id="discountInput" 
                                   step="0.01" min="0" value="0" 
                                   style="width: 150px; text-align: right;"
                                   onchange="calculateTotal()">
                        </div>
                        <div class="summary-row total">
                            <span>报价总额：</span>
                            <span id="totalAmount">¥0.00</span>
                        </div>
                    </div>
                </div>

                <!-- 条款说明 -->
                <div class="form-section">
                    <h3 class="section-title">条款说明</h3>
                    <div class="form-group">
                        <textarea name="terms" id="termsText" class="form-textarea" 
                                  style="min-height: 150px;" 
                                  placeholder="请输入报价单条款说明..."></textarea>
                    </div>
                </div>

                <!-- 隐藏字段：存储产品明细数据 -->
                <input type="hidden" name="items_data" id="itemsData">

                <!-- 操作按钮 -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" 
                            onclick="window.location.href='quotes.php'">取消</button>
                    <button type="submit" class="btn btn-primary" onclick="return validateAndSubmit()">💾 创建报价单</button>
                </div>
            </form>
        </div>
    </main>

    <!-- 产品搜索弹窗 -->
    <div id="productSearchModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; padding: 50px;">
        <div style="background: white; max-width: 800px; margin: 0 auto; border-radius: 12px; padding: 24px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>选择产品</h3>
                <button onclick="closeProductSearch()" style="border: none; background: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <input type="text" id="productSearchInput" class="form-input" 
                   placeholder="搜索产品名称、型号..." 
                   onkeyup="filterProducts(this.value)" 
                   style="margin-bottom: 16px;">
            
            <div id="productsList" style="max-height: 400px; overflow-y: auto;">
                <?php while ($product = $products->fetch_assoc()): ?>
                    <div class="search-result-item" onclick='selectProduct(<?php echo json_encode($product); ?>)'>
                        <div class="search-result-name">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </div>
                        <div class="search-result-info">
                            <?php echo htmlspecialchars($product['category_name']); ?> | 
                            规格: <?php echo htmlspecialchars($product['spec'] ?? '-'); ?> | 
                            价格: ¥<?php echo number_format($product['default_price'], 2); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script>
        // 模板条款数据
        const templateTerms = <?php echo json_encode($template_terms); ?>;

        let itemIndex = 0;

        /**
         * 处理模板类型变化
         */
        function handleTemplateChange() {
            const templateType = document.getElementById('templateType').value;
            const projectFields = document.getElementById('projectFields');
            const termsText = document.getElementById('termsText');
            
            // 显示/隐藏工程字段
            if (templateType === 'weak_current' || templateType === 'strong_current') {
                projectFields.classList.add('active');
            } else {
                projectFields.classList.remove('active');
            }
            
            // 填充默认条款
            if (templateTerms[templateType]) {
                termsText.value = templateTerms[templateType];
            }
        }

        /**
         * 添加产品行
         */
        function addItem(productData = null) {
            itemIndex++;
            const tbody = document.getElementById('itemsTableBody');
            const row = tbody.insertRow();
            row.id = 'item-' + itemIndex;
            
            row.innerHTML = `
                <td style="text-align: center;">${itemIndex}</td>
                <td>
                    <input type="text" class="item-name" value="${productData?.name || ''}" required>
                    <input type="hidden" class="item-product-id" value="${productData?.id || ''}">
                </td>
                <td><input type="text" class="item-brand" value="${productData?.supplier_name || ''}"></td>
                <td><input type="text" class="item-model" value=""></td>
                <td><input type="text" class="item-spec" value="${productData?.spec || ''}"></td>
                <td><input type="text" class="item-unit" value="${productData?.unit || '个'}" style="width: 60px;"></td>
                <td><input type="number" class="item-quantity" value="1" min="1" required onchange="calculateRow(${itemIndex})"></td>
                <td><input type="number" class="item-price" value="${productData?.default_price || 0}" step="0.01" min="0" required onchange="calculateRow(${itemIndex})"></td>
                <td><input type="number" class="item-cost" value="${productData?.cost_price || 0}" step="0.01" min="0" onchange="calculateRow(${itemIndex})"></td>
                <td><input type="number" class="item-subtotal" value="0" readonly style="background: #f7fafc; font-weight: 600;"></td>
                <td><input type="text" class="item-remark" value=""></td>
                <td style="text-align: center;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(${itemIndex})">删除</button>
                </td>
            `;
            
            calculateRow(itemIndex);
        }

        /**
         * 计算单行金额
         */
        function calculateRow(index) {
            const row = document.getElementById('item-' + index);
            if (!row) return;
            
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
            
            const subtotal = quantity * price;
            row.querySelector('.item-subtotal').value = subtotal.toFixed(2);
            
            calculateTotal();
        }

        /**
         * 计算总金额
         */
        function calculateTotal() {
            const tbody = document.getElementById('itemsTableBody');
            let total = 0;
            
            for (let row of tbody.rows) {
                const subtotal = parseFloat(row.querySelector('.item-subtotal').value) || 0;
                total += subtotal;
            }
            
            const discount = parseFloat(document.getElementById('discountInput').value) || 0;
            const finalTotal = total - discount;
            
            document.getElementById('subtotalAmount').textContent = '¥' + total.toFixed(2);
            document.getElementById('totalAmount').textContent = '¥' + finalTotal.toFixed(2);
        }

        /**
         * 删除产品行
         */
        function removeItem(index) {
            const row = document.getElementById('item-' + index);
            if (row) {
                row.remove();
                calculateTotal();
                updateRowNumbers();
            }
        }

        /**
         * 更新行号
         */
        function updateRowNumbers() {
            const tbody = document.getElementById('itemsTableBody');
            Array.from(tbody.rows).forEach((row, index) => {
                row.cells[0].textContent = index + 1;
            });
        }

        /**
         * 显示产品搜索
         */
        function showProductSearch() {
            document.getElementById('productSearchModal').style.display = 'block';
        }

        /**
         * 关闭产品搜索
         */
        function closeProductSearch() {
            document.getElementById('productSearchModal').style.display = 'none';
        }

        /**
         * 筛选产品
         */
        function filterProducts(keyword) {
            const items = document.querySelectorAll('#productsList .search-result-item');
            keyword = keyword.toLowerCase();
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(keyword) ? 'block' : 'none';
            });
        }

        /**
         * 选择产品
         */
        function selectProduct(product) {
            addItem(product);
            closeProductSearch();
        }

        /**
         * 验证并提交表单
         */
        function validateAndSubmit() {
            const tbody = document.getElementById('itemsTableBody');
            
            if (tbody.rows.length === 0) {
                alert('请至少添加一个产品！');
                return false;
            }
            
            // 收集产品明细数据
            const items = [];
            for (let row of tbody.rows) {
                const item = {
                    product_id: row.querySelector('.item-product-id').value,
                    product_name: row.querySelector('.item-name').value,
                    brand: row.querySelector('.item-brand').value,
                    model: row.querySelector('.item-model').value,
                    spec: row.querySelector('.item-spec').value,
                    unit: row.querySelector('.item-unit').value,
                    quantity: parseFloat(row.querySelector('.item-quantity').value),
                    price: parseFloat(row.querySelector('.item-price').value),
                    cost: parseFloat(row.querySelector('.item-cost').value) || 0,
                    subtotal: parseFloat(row.querySelector('.item-subtotal').value),
                    cost_subtotal: (parseFloat(row.querySelector('.item-quantity').value) || 0) * (parseFloat(row.querySelector('.item-cost').value) || 0),
                    remark: row.querySelector('.item-remark').value,
                    category: '',
                    warranty: ''
                };
                
                if (!item.product_name || item.quantity <= 0 || item.price < 0) {
                    alert('请检查产品明细，确保所有必填项已填写！');
                    return false;
                }
                
                items.push(item);
            }
            
            document.getElementById('itemsData').value = JSON.stringify(items);
            return true;
        }

        // 页面加载时添加一行空白产品
        window.addEventListener('DOMContentLoaded', function() {
            addItem();
        });
    </script>
</body>
</html>