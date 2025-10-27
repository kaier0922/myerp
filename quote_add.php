<?php
/**
 * =====================================================
 * 文件名：quote_add.php
 * 功能：新增报价单
 * 描述：创建新的报价单，支持自定义新增客户、拖拽排序、实时计算等功能
 * 版本：2.0
 * 更新日期：2025-10-22
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
$role = $_SESSION['role'];

$conn = getDBConnection();

// ==================== 处理POST请求（保存报价单）====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 获取基本信息
        $customer_id = intval($input['customer_id'] ?? 0);
        $template_type = $conn->real_escape_string($input['template_type'] ?? 'assembled_pc');
        $quote_date = $conn->real_escape_string($input['quote_date'] ?? date('Y-m-d'));
        $valid_days = intval($input['valid_days'] ?? 3);
        $project_name = $conn->real_escape_string($input['project_name'] ?? '');
        $project_location = $conn->real_escape_string($input['project_location'] ?? '');
        $construction_period = $conn->real_escape_string($input['construction_period'] ?? '');
        $terms = $conn->real_escape_string($input['terms'] ?? '');
        $final_amount = floatval($input['final_amount'] ?? 0);
        $items = $input['items'] ?? [];
        
        // 处理新客户
        $new_customer_name = trim($input['new_customer_name'] ?? '');
        $new_customer_contact = trim($input['new_customer_contact'] ?? '');
        $new_customer_phone = trim($input['new_customer_phone'] ?? '');
        
        if ($customer_id === -1 && !empty($new_customer_name)) {
            // 新增客户
            $stmt = $conn->prepare("INSERT INTO customers (company_name, contact_name, phone, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $new_customer_name, $new_customer_contact, $new_customer_phone);
            
            if ($stmt->execute()) {
                $customer_id = $conn->insert_id;
            } else {
                throw new Exception('客户创建失败');
            }
            $stmt->close();
        }
        
        // 生成报价单号
        $quote_no = 'Q' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        
        // 插入报价单主表
        $stmt = $conn->prepare("
            INSERT INTO quotes 
            (user_id, customer_id, project_name, project_location, construction_period, 
             quote_no, template_type, quote_date, valid_days, status, final_amount, terms, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '草稿', ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "iissssssids",
            $user_id,
            $customer_id,
            $project_name,
            $project_location,
            $construction_period,
            $quote_no,
            $template_type,
            $quote_date,
            $valid_days,
            $final_amount,
            $terms
        );
        
        if (!$stmt->execute()) {
            throw new Exception('报价单创建失败：' . $stmt->error);
        }
        
        $quote_id = $conn->insert_id;
        $stmt->close();
        
        // 插入报价单明细
        if (!empty($items)) {
            foreach ($items as $item) {
                $seq = intval($item['seq'] ?? 0);
                $product_id = isset($item['product_id']) && !empty($item['product_id']) ? intval($item['product_id']) : null;
                $product_name = $item['product_name'] ?? '';
                $brand = $item['brand'] ?? '';
                $model = $item['model'] ?? '';
                $spec = $item['spec'] ?? '';
                $unit = $item['unit'] ?? '个';
                $warranty = $item['warranty'] ?? '';
                $quantity = intval($item['quantity'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $cost = floatval($item['cost'] ?? 0);
                $subtotal = floatval($item['subtotal'] ?? 0);
                $cost_subtotal = floatval($item['cost_subtotal'] ?? 0);
                $remark = $item['remark'] ?? '';
                
                // 使用预处理语句插入数据
                if ($product_id !== null) {
                    $stmt = $conn->prepare("
                        INSERT INTO quote_items 
                        (quote_id, seq, product_id, product_name, brand, model, spec, unit, 
                         warranty, quantity, price, cost, subtotal, cost_subtotal, remark) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "iiissssssidddds",
                        $quote_id, $seq, $product_id, $product_name, $brand, $model, $spec, $unit,
                        $warranty, $quantity, $price, $cost, $subtotal, $cost_subtotal, $remark
                    );
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO quote_items 
                        (quote_id, seq, product_name, brand, model, spec, unit, 
                         warranty, quantity, price, cost, subtotal, cost_subtotal, remark) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "iissssssidddds",
                        $quote_id, $seq, $product_name, $brand, $model, $spec, $unit,
                        $warranty, $quantity, $price, $cost, $subtotal, $cost_subtotal, $remark
                    );
                }
                
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => '报价单创建成功',
            'quote_id' => $quote_id,
            'quote_no' => $quote_no
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// ==================== 获取客户列表 ====================
$customers = $conn->query("SELECT id, company_name, contact_name, phone FROM customers ORDER BY created_at DESC");

// ==================== 获取产品列表（按分类组织）====================
$products_by_category = [];
$products_result = $conn->query("
    SELECT p.*, pc.name as category_name, pc.id as category_id
    FROM products p 
    LEFT JOIN product_categories pc ON p.category_id = pc.id 
    WHERE p.is_active = 1 
    ORDER BY pc.id, p.name
");

while ($product = $products_result->fetch_assoc()) {
    $category_id = $product['category_id'];
    if (!isset($products_by_category[$category_id])) {
        $products_by_category[$category_id] = [
            'name' => $product['category_name'],
            'products' => []
        ];
    }
    $products_by_category[$category_id]['products'][] = $product;
}

// 转换为JSON供JavaScript使用
$products_json = json_encode($products_by_category, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增报价单 - 企业管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        /* 顶部导航栏 */
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

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* 主内容区 */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* 页面头部 */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        /* 按钮样式 */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            line-height: 1.5;
        }

        .btn-back {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
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

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        /* 表单卡片 */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* 表单布局 */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .form-group label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-control {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        /* 客户新增区域 */
        .new-customer-area {
            display: none;
            background: #f7fafc;
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            border: 2px dashed #cbd5e0;
        }

        .new-customer-area.active {
            display: block;
        }

        /* 明细表格 */
        .items-table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .items-table thead {
            background: #f7fafc;
        }

        .items-table th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        .items-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .items-table tbody tr {
            transition: all 0.2s;
            cursor: move;
        }

        .items-table tbody tr:hover {
            background: #f7fafc;
        }

        .items-table tbody tr.sortable-ghost {
            opacity: 0.4;
            background: #eef2ff;
        }

        .items-table input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }

        .items-table input:focus {
            border-color: #667eea;
            outline: none;
        }

        .items-table select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }

        .items-table select:focus {
            border-color: #667eea;
            outline: none;
        }

        .product-select {
            max-width: 200px;
        }

        .drag-handle {
            cursor: move;
            color: #a0aec0;
            font-size: 18px;
            padding: 0 4px;
        }

        .drag-handle:hover {
            color: #667eea;
        }

        .btn-remove {
            background: #fee;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remove:hover {
            background: #fecaca;
        }

        /* 合计区域 */
        .total-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 16px;
        }

        .total-row.final {
            border-top: 2px solid #e2e8f0;
            margin-top: 12px;
            padding-top: 16px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }

        /* 提示信息 */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-info {
            background: #e0f2fe;
            color: #075985;
            border-left: 4px solid #0284c7;
        }

        .alert-success {
            background: #f0fdf4;
            color: #15803d;
            border-left: 4px solid #22c55e;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* 加载状态 */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading.active {
            display: flex;
        }

        .loading-spinner {
            background: white;
            padding: 32px;
            border-radius: 12px;
            text-align: center;
        }

        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span>📊</span>
            <span>企业管理系统</span>
        </a>
        <div class="navbar-user">
            <span>👤 <?php echo htmlspecialchars($nickname); ?></span>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">新增报价单</h1>
            <div class="btn-group">
                <a href="quotes.php" class="btn btn-back">← 返回列表</a>
                <button type="button" class="btn btn-success" onclick="saveQuote()">💾 保存报价单</button>
            </div>
        </div>

        <!-- 基本信息 -->
        <div class="form-card">
            <h2 class="form-section-title">基本信息</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">选择客户</label>
                    <select id="customer_id" class="form-control" onchange="handleCustomerChange()">
                        <option value="">请选择客户</option>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['company_name'] ?? ''); ?>
                                <?php if ($customer['contact_name']): ?>
                                    - <?php echo htmlspecialchars($customer['contact_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endwhile; ?>
                        <option value="-1">➕ 自定义新增客户</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">报价日期</label>
                    <input type="date" id="quote_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label class="required">有效期（天）</label>
                    <input type="number" id="valid_days" class="form-control" value="3" min="1">
                </div>

                <div class="form-group">
                    <label>模板类型</label>
                    <select id="template_type" class="form-control">
                        <option value="assembled_pc">组装电脑</option>
                        <option value="brand_pc">品牌整机</option>
                        <option value="weak_current">弱电工程</option>
                        <option value="strong_current">强电工程</option>
                    </select>
                </div>
            </div>

            <!-- 新增客户区域 -->
            <div id="newCustomerArea" class="new-customer-area">
                <h3 style="margin-bottom: 16px; font-size: 16px; color: #4a5568;">新增客户信息</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">公司名称</label>
                        <input type="text" id="new_customer_name" class="form-control" placeholder="请输入公司名称">
                    </div>
                    <div class="form-group">
                        <label>联系人</label>
                        <input type="text" id="new_customer_contact" class="form-control" placeholder="请输入联系人姓名">
                    </div>
                    <div class="form-group">
                        <label>联系电话</label>
                        <input type="tel" id="new_customer_phone" class="form-control" placeholder="请输入联系电话">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>项目名称</label>
                    <input type="text" id="project_name" class="form-control" placeholder="选填">
                </div>

                <div class="form-group">
                    <label>项目地址</label>
                    <input type="text" id="project_location" class="form-control" placeholder="选填">
                </div>

                <div class="form-group">
                    <label>施工工期</label>
                    <input type="text" id="construction_period" class="form-control" placeholder="例如：30天">
                </div>
            </div>

            <div class="form-group">
                <label>条款说明</label>
                <textarea id="terms" class="form-control" placeholder="输入报价条款、付款方式、质保条件等信息">1. 所有配件均为全新正品行货
2. 提供详细配置清单
3. 免费组装调试
4. 质保期按各配件厂商标准执行
5. 新机保修三年，机械硬盘保修两年</textarea>
            </div>
        </div>

        <!-- 报价明细 -->
        <div class="form-card">
            <h2 class="form-section-title">报价明细</h2>
            
            <div class="alert alert-info">
                💡 提示：默认显示常见配件，可拖动☰调整顺序。选择"➕ 自定义输入"可自由填写，或点击下方按钮添加更多明细行。
            </div>

            <button type="button" class="btn btn-secondary" onclick="addItem()" style="margin-bottom: 16px;">
                ➕ 添加空白行
            </button>

            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">拖动</th>
                            <th style="width: 50px;">序号</th>
                            <th style="width: 120px;">配件类型</th>
                            <th style="width: 200px;">选择产品</th>
                            <th style="width: 150px;">规格型号</th>
                            <th style="width: 80px;">品牌</th>
                            <th style="width: 60px;">单位</th>
                            <th style="width: 80px;">数量</th>
                            <th style="width: 100px;">单价</th>
                            <th style="width: 100px;">金额</th>
                            <th style="width: 100px;">质保</th>
                            <th style="width: 150px;">备注</th>
                            <th style="width: 60px;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- 动态生成的明细行 -->
                    </tbody>
                </table>
            </div>

            <!-- 合计区域 -->
            <div class="total-section">
                <div class="total-row">
                    <span>商品总额：</span>
                    <span id="totalAmount">¥0.00</span>
                </div>
                <div class="total-row final">
                    <span>最终报价：</span>
                    <span id="finalAmount">¥0.00</span>
                </div>
            </div>
        </div>
    </main>

    <!-- 加载遮罩 -->
    <div id="loading" class="loading">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div>正在保存...</div>
        </div>
    </div>

    <!-- SortableJS库（用于拖拽排序）-->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <script>
        /**
         * =====================================================
         * 全局变量
         * =====================================================
         */
        let itemCounter = 0;
        let sortable = null;
        
        // 产品数据（从PHP传入）
        const productsData = <?php echo $products_json; ?>;
        
        // 默认组装电脑配件模板
        const defaultPCParts = [
            { seq: 1, name: '处理器', category_id: 11, unit: '个' },
            { seq: 2, name: '主板', category_id: 12, unit: '块' },
            { seq: 3, name: '内存', category_id: 13, unit: '条' },
            { seq: 4, name: '硬盘/SSD', category_id: 14, unit: '块' },
            { seq: 5, name: '显卡', category_id: 15, unit: '块' },
            { seq: 6, name: '电源', category_id: 16, unit: '个' },
            { seq: 7, name: '机箱', category_id: 17, unit: '个' },
            { seq: 8, name: '散热器', category_id: 18, unit: '个' },
            { seq: 9, name: '显示器', category_id: 41, unit: '台' },
            { seq: 10, name: '键鼠套装', category_id: 45, unit: '套' }
        ];

        /**
         * =====================================================
         * 页面加载完成后初始化
         * =====================================================
         */
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化拖拽排序
            initSortable();
            
            // 添加默认配件行
            defaultPCParts.forEach(part => {
                addItemWithData(part);
            });
            
            // 监听模板类型变化
            document.getElementById('template_type').addEventListener('change', handleTemplateChange);
        });

        /**
         * =====================================================
         * 处理模板类型变化
         * =====================================================
         */
        function handleTemplateChange() {
            const templateType = document.getElementById('template_type').value;
            
            if (templateType === 'assembled_pc') {
                // 如果切换到组装电脑模板，且当前没有行，则添加默认配件
                const tbody = document.getElementById('itemsTableBody');
                if (tbody.children.length === 0) {
                    defaultPCParts.forEach(part => {
                        addItemWithData(part);
                    });
                }
            }
        }

        /**
         * =====================================================
         * 初始化拖拽排序
         * =====================================================
         */
        function initSortable() {
            const tbody = document.getElementById('itemsTableBody');
            sortable = Sortable.create(tbody, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                    updateSequenceNumbers();
                    calculateTotal();
                }
            });
        }

        /**
         * =====================================================
         * 处理客户选择变化
         * =====================================================
         */
        function handleCustomerChange() {
            const customerId = document.getElementById('customer_id').value;
            const newCustomerArea = document.getElementById('newCustomerArea');
            
            if (customerId === '-1') {
                newCustomerArea.classList.add('active');
            } else {
                newCustomerArea.classList.remove('active');
            }
        }

        /**
         * =====================================================
         * 生成产品选择下拉框HTML
         * =====================================================
         */
        function generateProductSelect(categoryId = null) {
            let html = '<select class="product-select" onchange="handleProductSelect(this)">';
            html += '<option value="">-- 请选择产品 --</option>';
            
            // 如果指定了分类，优先显示该分类的产品
            if (categoryId && productsData[categoryId]) {
                const category = productsData[categoryId];
                html += `<optgroup label="${category.name}">`;
                category.products.forEach(product => {
                    html += `<option value="${product.id}" 
                        data-name="${escapeHtml(product.name)}"
                        data-brand="${escapeHtml(product.brand || '')}"
                        data-model="${escapeHtml(product.model || '')}"
                        data-spec="${escapeHtml(product.spec || '')}"
                        data-unit="${escapeHtml(product.unit || '个')}"
                        data-price="${product.sale_price || 0}"
                        data-cost="${product.cost_price || 0}">
                        ${escapeHtml(product.name)} ${product.brand ? '- ' + escapeHtml(product.brand) : ''}
                    </option>`;
                });
                html += '</optgroup>';
            }
            
            // 显示所有其他分类的产品
            for (const [catId, category] of Object.entries(productsData)) {
                if (catId == categoryId) continue; // 跳过已经显示的分类
                
                html += `<optgroup label="${category.name}">`;
                category.products.forEach(product => {
                    html += `<option value="${product.id}" 
                        data-name="${escapeHtml(product.name)}"
                        data-brand="${escapeHtml(product.brand || '')}"
                        data-model="${escapeHtml(product.model || '')}"
                        data-spec="${escapeHtml(product.spec || '')}"
                        data-unit="${escapeHtml(product.unit || '个')}"
                        data-price="${product.sale_price || 0}"
                        data-cost="${product.cost_price || 0}">
                        ${escapeHtml(product.name)} ${product.brand ? '- ' + escapeHtml(product.brand) : ''}
                    </option>`;
                });
                html += '</optgroup>';
            }
            
            html += '<option value="custom">➕ 自定义输入</option>';
            html += '</select>';
            
            return html;
        }

        /**
         * =====================================================
         * HTML转义函数
         * =====================================================
         */
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * =====================================================
         * 处理产品选择
         * =====================================================
         */
        function handleProductSelect(select) {
            const row = select.closest('tr');
            const selectedOption = select.options[select.selectedIndex];
            
            if (select.value === 'custom') {
                // 自定义输入 - 清空所有字段，启用编辑
                row.querySelector('.item-spec').value = '';
                row.querySelector('.item-brand').value = '';
                row.querySelector('.item-unit').value = '个';
                row.querySelector('.item-price').value = '0';
                row.querySelector('.item-cost').value = '0';
                
                // 启用所有输入框
                row.querySelectorAll('input').forEach(input => {
                    input.readOnly = false;
                    input.style.background = '';
                });
                
                select.value = ''; // 重置选择框
            } else if (select.value) {
                // 选择了产品 - 自动填充
                const productName = selectedOption.dataset.name || '';
                const brand = selectedOption.dataset.brand || '';
                const model = selectedOption.dataset.model || '';
                const spec = selectedOption.dataset.spec || '';
                const unit = selectedOption.dataset.unit || '个';
                const price = selectedOption.dataset.price || '0';
                const cost = selectedOption.dataset.cost || '0';
                
                // 填充数据
                row.querySelector('.item-spec').value = spec || (model ? model : '');
                row.querySelector('.item-brand').value = brand;
                row.querySelector('.item-unit').value = unit;
                row.querySelector('.item-price').value = price;
                row.querySelector('.item-cost').value = cost;
                
                // 存储product_id
                row.setAttribute('data-product-id', select.value);
                
                // 触发价格计算
                calculateRowTotal(row.querySelector('.item-price'));
            }
        }

        /**
         * =====================================================
         * 添加带数据的明细行
         * =====================================================
         */
        function addItemWithData(data = {}) {
            itemCounter++;
            const tbody = document.getElementById('itemsTableBody');
            const row = document.createElement('tr');
            row.setAttribute('data-item-id', itemCounter);
            
            const partName = data.name || '';
            const categoryId = data.category_id || null;
            const unit = data.unit || '个';
            
            row.innerHTML = `
                <td><span class="drag-handle">☰</span></td>
                <td><strong class="seq-number">${data.seq || itemCounter}</strong></td>
                <td><input type="text" class="item-name" value="${partName}" placeholder="配件类型"></td>
                <td>${generateProductSelect(categoryId)}</td>
                <td><input type="text" class="item-spec" placeholder="规格型号"></td>
                <td><input type="text" class="item-brand" placeholder="品牌"></td>
                <td><input type="text" class="item-unit" value="${unit}"></td>
                <td><input type="number" class="item-quantity" value="1" min="1" onchange="calculateRowTotal(this)"></td>
                <td><input type="number" class="item-price" value="0" min="0" step="0.01" onchange="calculateRowTotal(this)"></td>
                <td><strong class="item-subtotal">¥0.00</strong></td>
                <td><input type="text" class="item-warranty" placeholder="质保期"></td>
                <td><input type="text" class="item-remark" placeholder="备注"></td>
                <td><button type="button" class="btn-remove" onclick="removeItem(this)">删除</button></td>
            `;
            
            // 隐藏的成本价字段
            const costInput = document.createElement('input');
            costInput.type = 'hidden';
            costInput.className = 'item-cost';
            costInput.value = '0';
            row.appendChild(costInput);
            
            tbody.appendChild(row);
            updateSequenceNumbers();
        }

        /**
         * =====================================================
         * 添加空明细行
         * =====================================================
         */
        function addItem() {
            addItemWithData({});
        }

        /**
         * =====================================================
         * 移除明细行
         * =====================================================
         */
        function removeItem(btn) {
            if (confirm('确定要删除这一行吗？')) {
                btn.closest('tr').remove();
                updateSequenceNumbers();
                calculateTotal();
            }
        }

        /**
         * =====================================================
         * 更新序号
         * =====================================================
         */
        function updateSequenceNumbers() {
            const rows = document.querySelectorAll('#itemsTableBody tr');
            rows.forEach((row, index) => {
                const seqNum = row.querySelector('.seq-number');
                if (seqNum) {
                    seqNum.textContent = index + 1;
                }
            });
        }

        /**
         * =====================================================
         * 计算单行金额
         * =====================================================
         */
        function calculateRowTotal(input) {
            const row = input.closest('tr');
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const subtotal = quantity * price;
            
            row.querySelector('.item-subtotal').textContent = '¥' + subtotal.toFixed(2);
            
            calculateTotal();
        }

        /**
         * =====================================================
         * 计算总金额
         * =====================================================
         */
        function calculateTotal() {
            const rows = document.querySelectorAll('#itemsTableBody tr');
            let total = 0;
            
            rows.forEach(row => {
                const quantity = parseFloat(row.querySelector('.item-quantity')?.value) || 0;
                const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
                total += quantity * price;
            });
            
            document.getElementById('totalAmount').textContent = '¥' + total.toFixed(2);
            document.getElementById('finalAmount').textContent = '¥' + total.toFixed(2);
        }

        /**
         * =====================================================
         * 收集表单数据
         * =====================================================
         */
        function collectFormData() {
            // 基本信息
            const customerId = document.getElementById('customer_id').value;
            const templateType = document.getElementById('template_type').value;
            const quoteDate = document.getElementById('quote_date').value;
            const validDays = document.getElementById('valid_days').value;
            const projectName = document.getElementById('project_name').value || '';
            const projectLocation = document.getElementById('project_location').value || '';
            const constructionPeriod = document.getElementById('construction_period').value || '';
            const terms = document.getElementById('terms').value || '';
            
            // 新客户信息
            const newCustomerName = document.getElementById('new_customer_name')?.value || '';
            const newCustomerContact = document.getElementById('new_customer_contact')?.value || '';
            const newCustomerPhone = document.getElementById('new_customer_phone')?.value || '';
            
            // 验证必填项
            if (!customerId) {
                alert('请选择客户');
                return null;
            }
            
            if (customerId === '-1' && !newCustomerName) {
                alert('请输入新客户的公司名称');
                return null;
            }
            
            if (!quoteDate) {
                alert('请选择报价日期');
                return null;
            }
            
            if (!validDays || validDays < 1) {
                alert('请输入有效的有效期天数');
                return null;
            }
            
            // 收集明细
            const items = [];
            const rows = document.querySelectorAll('#itemsTableBody tr');
            
            rows.forEach((row, index) => {
                const partName = (row.querySelector('.item-name')?.value || '').trim();
                const productId = row.getAttribute('data-product-id') || null;
                const spec = (row.querySelector('.item-spec')?.value || '').trim();
                const brand = (row.querySelector('.item-brand')?.value || '').trim();
                const unit = (row.querySelector('.item-unit')?.value || '个').trim();
                const warranty = (row.querySelector('.item-warranty')?.value || '').trim();
                const quantity = parseFloat(row.querySelector('.item-quantity')?.value) || 0;
                const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
                const cost = parseFloat(row.querySelector('.item-cost')?.value) || 0;
                const remark = (row.querySelector('.item-remark')?.value || '').trim();
                
                // 只添加有配件名称或价格的行
                if (partName || price > 0) {
                    const subtotal = quantity * price;
                    const costSubtotal = quantity * cost;
                    
                    items.push({
                        seq: index + 1,
                        product_id: productId,
                        product_name: partName,
                        brand: brand,
                        model: '', // 型号字段可以留空，规格已经包含了
                        spec: spec,
                        unit: unit,
                        warranty: warranty,
                        quantity: quantity,
                        price: price,
                        cost: cost,
                        subtotal: subtotal,
                        cost_subtotal: costSubtotal,
                        remark: remark
                    });
                }
            });
            
            // 计算最终金额
            const finalAmount = items.reduce((sum, item) => sum + item.subtotal, 0);
            
            return {
                customer_id: customerId,
                template_type: templateType,
                quote_date: quoteDate,
                valid_days: validDays,
                project_name: projectName,
                project_location: projectLocation,
                construction_period: constructionPeriod,
                terms: terms,
                final_amount: finalAmount,
                items: items,
                new_customer_name: newCustomerName,
                new_customer_contact: newCustomerContact,
                new_customer_phone: newCustomerPhone
            };
        }

        /**
         * =====================================================
         * 保存报价单
         * =====================================================
         */
        async function saveQuote() {
            const formData = collectFormData();
            
            if (!formData) {
                return;
            }
            
            // 显示加载状态
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('quote_add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                document.getElementById('loading').classList.remove('active');
                
                if (result.success) {
                    alert('报价单创建成功！\n报价单号：' + result.quote_no);
                    window.location.href = 'quotes.php';
                } else {
                    alert('保存失败：' + result.message);
                }
            } catch (error) {
                document.getElementById('loading').classList.remove('active');
                console.error('保存错误:', error);
                alert('保存出错，请重试');
            }
        }
    </script>
</body>
</html>