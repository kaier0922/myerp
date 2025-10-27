<?php
/**
 * ============================================================================
 * 文件名: delivery_add.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 新建送货单页面
 * 
 * 功能说明：
 * 1. 创建送货单
 * 2. 可从报价单导入
 * 3. 选择现有客户或手动输入
 * 4. 动态添加送货明细
 * 5. 自动计算费用
 * 6. 支持货到付款选项
 * ============================================================================
 */

session_start();
require_once 'config.php';

// ==================== 权限验证 ====================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ==================== 连接数据库 ====================
$conn = getDBConnection();

// ==================== 加载客户列表 ====================
$customers_sql = "SELECT id, company_name, contact_name, phone, address FROM customers ORDER BY company_name";
$customers = $conn->query($customers_sql);

// ==================== 加载报价单列表（已审核的）====================
$quotes_sql = "
    SELECT q.id, q.quote_no, c.company_name, q.final_amount, q.quote_date 
    FROM quotes q 
    LEFT JOIN customers c ON q.customer_id = c.id 
    WHERE q.status = '已审核' 
    ORDER BY q.created_at DESC 
    LIMIT 50
";
$quotes = $conn->query($quotes_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新建送货单</title>
    <style>
        /* ==================== 全局样式 ==================== */
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

        /* ==================== 导航栏 ==================== */
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

        /* ==================== 按钮样式 ==================== */
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        /* ==================== 主内容区 ==================== */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==================== 表单卡片 ==================== */
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

        /* ==================== 表单样式 ==================== */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }

        .form-label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
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

        .form-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        /* ==================== 来源选择器 ==================== */
        .source-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .source-option {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .source-option:hover {
            border-color: #cbd5e0;
            background: #f7fafc;
        }

        .source-option.active {
            border-color: #667eea;
            background: #f3f4ff;
        }

        .source-option input[type="radio"] {
            display: none;
        }

        .source-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .source-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .source-desc {
            font-size: 12px;
            color: #718096;
        }

        /* ==================== 客户选择器 ==================== */
        .customer-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .customer-selector button {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .customer-selector button:hover {
            border-color: #cbd5e0;
            background: #f7fafc;
        }

        .customer-selector button.active {
            border-color: #667eea;
            background: #f3f4ff;
            color: #667eea;
        }

        /* ==================== 产品明细表格 ==================== */
        .items-container {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead {
            background: #f7fafc;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 14px;
        }

        .items-table input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-remove-item {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remove-item:hover {
            background: #dc2626;
        }

        .btn-add-item {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 12px;
            width: 100%;
        }

        .btn-add-item:hover {
            background: #059669;
        }

        /* ==================== 费用汇总 ==================== */
        .fee-summary {
            background: #f7fafc;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .fee-row.total {
            border-top: 2px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
        }

        /* ==================== 复选框样式 ==================== */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            user-select: none;
        }

        /* ==================== 响应式 ==================== */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 16px;
            }

            .main-content {
                padding: 16px;
            }

            .form-card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .source-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">🚚 新建送货单</div>
        <div class="navbar-actions">
            <a href="deliveries.php" class="btn btn-secondary">返回列表</a>
            <button class="btn btn-success" onclick="saveDelivery()">保存送货单</button>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <form id="deliveryForm">
            <input type="hidden" name="action" value="add">

            <!-- ==================== 送货单来源 ==================== -->
            <div class="form-card">
                <h2 class="section-title">送货单来源</h2>
                <div class="source-selector">
                    <label class="source-option active" onclick="selectSource('manual')">
                        <input type="radio" name="source" value="manual" checked>
                        <div class="source-icon">📝</div>
                        <div class="source-title">手动创建</div>
                        <div class="source-desc">手动输入客户和产品信息</div>
                    </label>
                    <label class="source-option" onclick="selectSource('quote')">
                        <input type="radio" name="source" value="quote">
                        <div class="source-icon">📋</div>
                        <div class="source-title">从报价单导入</div>
                        <div class="source-desc">选择已审核的报价单</div>
                    </label>
                </div>

                <!-- 报价单选择 -->
                <div id="quoteSelectField" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">选择报价单</label>
                        <select name="quote_id" class="form-select" onchange="loadQuoteData(this)">
                            <option value="">请选择报价单</option>
                            <?php if ($quotes && $quotes->num_rows > 0): ?>
                                <?php while ($quote = $quotes->fetch_assoc()): ?>
                                <option value="<?php echo $quote['id']; ?>">
                                    <?php echo htmlspecialchars($quote['quote_no']); ?> - 
                                    <?php echo htmlspecialchars($quote['company_name']); ?> - 
                                    ¥<?php echo number_format($quote['final_amount'], 2); ?>
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ==================== 客户信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">客户信息</h2>

                <div class="customer-selector">
                    <button type="button" class="active" onclick="selectCustomerMode('existing')">
                        选择现有客户
                    </button>
                    <button type="button" onclick="selectCustomerMode('new')">
                        手动输入客户
                    </button>
                </div>

                <!-- 现有客户选择 -->
                <div id="existingCustomerFields">
                    <div class="form-group">
                        <label class="form-label">选择客户</label>
                        <select name="customer_id" class="form-select" onchange="fillCustomerInfo(this)">
                            <option value="">请选择客户</option>
                            <?php if ($customers && $customers->num_rows > 0): ?>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                <option value="<?php echo $customer['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($customer['company_name']); ?>"
                                        data-contact="<?php echo htmlspecialchars($customer['contact_name']); ?>"
                                        data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                        data-address="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($customer['company_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- 手动输入字段 -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">客户名称</label>
                        <input type="text" name="customer_name" class="form-input" 
                               placeholder="请输入客户名称" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">联系人</label>
                        <input type="text" name="contact_name" class="form-input" 
                               placeholder="请输入联系人" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">联系电话</label>
                        <input type="tel" name="contact_phone" class="form-input" 
                               placeholder="请输入联系电话" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">送货地址</label>
                        <input type="text" name="delivery_address" class="form-input" 
                               placeholder="请输入送货地址" required>
                    </div>
                </div>
            </div>

            <!-- ==================== 送货信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">送货信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">送货日期</label>
                        <input type="date" name="delivery_date" class="form-input" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">送货时间段</label>
                        <select name="delivery_time" class="form-select">
                            <option value="">不限</option>
                            <option value="上午 (9:00-12:00)">上午 (9:00-12:00)</option>
                            <option value="下午 (14:00-18:00)">下午 (14:00-18:00)</option>
                            <option value="晚上 (18:00-21:00)">晚上 (18:00-21:00)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">送货人</label>
                        <input type="text" name="delivery_person" class="form-input" 
                               placeholder="请输入送货人姓名">
                    </div>

                    <div class="form-group">
                        <label class="form-label">车牌号</label>
                        <input type="text" name="vehicle_no" class="form-input" 
                               placeholder="例如：粤A12345">
                    </div>
                </div>
            </div>

            <!-- ==================== 送货明细 ==================== -->
            <div class="form-card">
                <h2 class="section-title">送货明细</h2>

                <div class="items-container">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th width="40">序号</th>
                                <th width="280">产品名称</th>
                                <th width="200">规格型号</th>
                                <th width="80">数量</th>
                                <th width="60">单位</th>
                                <th width="120">单价</th>
                                <th width="120">小计</th>
                                <th width="80">操作</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr id="itemRow0">
                                <td style="text-align: center;">1</td>
                                <td>
                                    <input type="text" name="items[0][product_name]" placeholder="产品名称" required>
                                </td>
                                <td>
                                    <input type="text" name="items[0][product_spec]" placeholder="规格型号">
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]" value="1" min="1" 
                                           onchange="calculateItemTotal(0)" required>
                                </td>
                                <td>
                                    <input type="text" name="items[0][unit]" value="件">
                                </td>
                                <td>
                                    <input type="number" name="items[0][unit_price]" value="0" step="0.01" min="0" 
                                           onchange="calculateItemTotal(0)" required>
                                </td>
                                <td>
                                    <input type="text" name="items[0][subtotal]" value="0.00" readonly>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-remove-item" onclick="removeItem(0)">删除</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn-add-item" onclick="addItem()">+ 添加产品</button>
            </div>

            <!-- ==================== 费用信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">费用信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">货物金额</label>
                        <input type="number" name="goods_amount" id="goodsAmount" class="form-input" 
                               value="0" step="0.01" min="0" readonly>
                        <div class="form-hint">自动从明细计算</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">运费</label>
                        <input type="number" name="freight_fee" id="freightFee" class="form-input" 
                               value="0" step="0.01" min="0" onchange="calculateTotal()">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">支付方式</label>
                    <select name="payment_method" class="form-select">
                        <option value="">待确定</option>
                        <option value="现金">现金</option>
                        <option value="银行转账">银行转账</option>
                        <option value="支付宝">支付宝</option>
                        <option value="微信">微信</option>
                        <option value="刷卡">刷卡</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="collect_on_delivery" id="collectOnDelivery" value="1">
                        <label for="collectOnDelivery">货到付款</label>
                    </div>
                    <div class="form-hint">勾选此项表示需要在送货时收款</div>
                </div>

                <div class="fee-summary">
                    <div class="fee-row">
                        <span>货物金额：</span>
                        <span id="summaryGoodsAmount">¥0.00</span>
                    </div>
                    <div class="fee-row">
                        <span>运费：</span>
                        <span id="summaryFreightFee">¥0.00</span>
                    </div>
                    <div class="fee-row total">
                        <span>总金额：</span>
                        <span id="summaryTotalAmount">¥0.00</span>
                    </div>
                </div>
            </div>

            <!-- ==================== 备注 ==================== -->
            <div class="form-card">
                <h2 class="section-title">备注信息</h2>
                <div class="form-group">
                    <textarea name="notes" class="form-textarea" 
                              placeholder="其他需要说明的信息" rows="4"></textarea>
                </div>
            </div>
        </form>
    </main>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 新建送货单页面加载 ===');

        let itemIndex = 1;

        /**
         * 选择来源
         */
        function selectSource(source) {
            const options = document.querySelectorAll('.source-option');
            options.forEach(opt => opt.classList.remove('active'));
            event.currentTarget.classList.add('active');

            const quoteField = document.getElementById('quoteSelectField');
            if (source === 'quote') {
                quoteField.style.display = 'block';
            } else {
                quoteField.style.display = 'none';
                document.querySelector('select[name="quote_id"]').value = '';
            }
        }

        /**
         * 加载报价单数据
         */
        function loadQuoteData(select) {
            const quoteId = select.value;
            if (!quoteId) return;

            console.log('加载报价单:', quoteId);

            fetch(`quote_data.php?id=${quoteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 填充客户信息
                        document.querySelector('input[name="customer_name"]').value = data.customer_name || '';
                        document.querySelector('input[name="contact_name"]').value = data.contact_name || '';
                        document.querySelector('input[name="contact_phone"]').value = data.phone || '';
                        document.querySelector('input[name="delivery_address"]').value = data.address || '';

                        // 清空并重新加载明细
                        const tbody = document.getElementById('itemsBody');
                        tbody.innerHTML = '';
                        itemIndex = 0;

                        if (data.items && data.items.length > 0) {
                            data.items.forEach(item => {
                                addItemWithData(item);
                            });
                        } else {
                            addItem();
                        }

                        calculateTotal();
                    } else {
                        alert('加载报价单失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('加载错误:', error);
                    alert('加载报价单出错');
                });
        }

        /**
         * 选择客户模式
         */
        function selectCustomerMode(mode) {
            const buttons = document.querySelectorAll('.customer-selector button');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            const existingFields = document.getElementById('existingCustomerFields');
            const customerSelect = document.querySelector('select[name="customer_id"]');

            if (mode === 'existing') {
                existingFields.style.display = 'block';
            } else {
                existingFields.style.display = 'none';
                customerSelect.value = '';
            }
        }

        /**
         * 填充客户信息
         */
        function fillCustomerInfo(select) {
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.querySelector('input[name="customer_name"]').value = option.dataset.name || '';
                document.querySelector('input[name="contact_name"]').value = option.dataset.contact || '';
                document.querySelector('input[name="contact_phone"]').value = option.dataset.phone || '';
                document.querySelector('input[name="delivery_address"]').value = option.dataset.address || '';
            }
        }

        /**
         * 添加产品行
         */
        function addItem() {
            const tbody = document.getElementById('itemsBody');
            const newRow = document.createElement('tr');
            newRow.id = 'itemRow' + itemIndex;

            newRow.innerHTML = `
                <td style="text-align: center;">${itemIndex + 1}</td>
                <td>
                    <input type="text" name="items[${itemIndex}][product_name]" placeholder="产品名称" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][product_spec]" placeholder="规格型号">
                </td>
                <td>
                    <input type="number" name="items[${itemIndex}][quantity]" value="1" min="1" 
                           onchange="calculateItemTotal(${itemIndex})" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][unit]" value="件">
                </td>
                <td>
                    <input type="number" name="items[${itemIndex}][unit_price]" value="0" step="0.01" min="0" 
                           onchange="calculateItemTotal(${itemIndex})" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][subtotal]" value="0.00" readonly>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove-item" onclick="removeItem(${itemIndex})">删除</button>
                </td>
            `;

            tbody.appendChild(newRow);
            itemIndex++;
            updateRowNumbers();
        }

        /**
         * 添加产品行（带数据）
         */
        function addItemWithData(item) {
            const tbody = document.getElementById('itemsBody');
            const newRow = document.createElement('tr');
            newRow.id = 'itemRow' + itemIndex;

            const quantity = item.quantity || 1;
            const unitPrice = item.price || 0;
            const subtotal = quantity * unitPrice;

            newRow.innerHTML = `
                <td style="text-align: center;">${itemIndex + 1}</td>
                <td>
                    <input type="text" name="items[${itemIndex}][product_name]" value="${item.product_name || ''}" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][product_spec]" value="${item.spec || ''}">
                </td>
                <td>
                    <input type="number" name="items[${itemIndex}][quantity]" value="${quantity}" min="1" 
                           onchange="calculateItemTotal(${itemIndex})" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][unit]" value="${item.unit || '件'}">
                </td>
                <td>
                    <input type="number" name="items[${itemIndex}][unit_price]" value="${unitPrice}" step="0.01" min="0" 
                           onchange="calculateItemTotal(${itemIndex})" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][subtotal]" value="${subtotal.toFixed(2)}" readonly>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove-item" onclick="removeItem(${itemIndex})">删除</button>
                </td>
            `;

            tbody.appendChild(newRow);
            itemIndex++;
        }

        /**
         * 删除产品行
         */
        function removeItem(index) {
            const rows = document.querySelectorAll('#itemsBody tr');
            if (rows.length > 1) {
                document.getElementById('itemRow' + index).remove();
                updateRowNumbers();
                calculateTotal();
            } else {
                alert('至少保留一个产品');
            }
        }

        /**
         * 更新行号
         */
        function updateRowNumbers() {
            const rows = document.querySelectorAll('#itemsBody tr');
            rows.forEach((row, index) => {
                row.querySelector('td:first-child').textContent = index + 1;
            });
        }

        /**
         * 计算单行小计
         */
        function calculateItemTotal(index) {
            const row = document.getElementById('itemRow' + index);
            if (!row) return;

            const qtyInput = row.querySelector('input[name*="[quantity]"]');
            const priceInput = row.querySelector('input[name*="[unit_price]"]');
            const subtotalInput = row.querySelector('input[name*="[subtotal]"]');

            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const subtotal = qty * price;

            subtotalInput.value = subtotal.toFixed(2);
            calculateTotal();
        }

        /**
         * 计算总金额
         */
        function calculateTotal() {
            let goodsAmount = 0;
            const rows = document.querySelectorAll('#itemsBody tr');

            rows.forEach(row => {
                const subtotalInput = row.querySelector('input[name*="[subtotal]"]');
                if (subtotalInput) {
                    goodsAmount += parseFloat(subtotalInput.value) || 0;
                }
            });

            const freightFee = parseFloat(document.getElementById('freightFee').value) || 0;
            const totalAmount = goodsAmount + freightFee;

            document.getElementById('goodsAmount').value = goodsAmount.toFixed(2);
            document.getElementById('summaryGoodsAmount').textContent = '¥' + goodsAmount.toFixed(2);
            document.getElementById('summaryFreightFee').textContent = '¥' + freightFee.toFixed(2);
            document.getElementById('summaryTotalAmount').textContent = '¥' + totalAmount.toFixed(2);
        }

        /**
         * 保存送货单
         */
        function saveDelivery() {
            const form = document.getElementById('deliveryForm');

            if (!form.checkValidity()) {
                alert('请填写所有必填项！');
                return;
            }

            const formData = new FormData(form);

            const saveButton = event.target;
            saveButton.disabled = true;
            saveButton.textContent = '保存中...';

            fetch('delivery_save.php', {
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
                    window.location.href = 'deliveries.php';
                } else {
                    alert('保存失败: ' + data.message);
                    saveButton.disabled = false;
                    saveButton.textContent = '保存送货单';
                }
            })
            .catch(error => {
                console.error('保存错误:', error);
                alert('保存出错: ' + error.message);
                saveButton.disabled = false;
                saveButton.textContent = '保存送货单';
            });
        }

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>