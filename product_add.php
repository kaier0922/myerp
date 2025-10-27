<?php
/**
 * =====================================================
 * 文件名：product_add.php
 * 功能：新增产品
 * 描述：添加产品到产品库，支持分类、价格、库存管理
 * 版本：1.0
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

// ==================== 处理POST请求（保存产品）====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 获取产品信息
        $category_id = intval($input['category_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $brand = trim($input['brand'] ?? '');
        $model = trim($input['model'] ?? '');
        $spec = trim($input['spec'] ?? '');
        $unit = trim($input['unit'] ?? '个');
        $cost_price = floatval($input['cost_price'] ?? 0);
        $sale_price = floatval($input['sale_price'] ?? 0);
        $stock_quantity = intval($input['stock_quantity'] ?? 0);
        $stock_alert = intval($input['stock_alert'] ?? 10);
        $warranty = trim($input['warranty'] ?? '');
        $description = trim($input['description'] ?? '');
        $is_active = intval($input['is_active'] ?? 1);
        
        // 验证必填项
        if (empty($name)) {
            throw new Exception('请输入产品名称');
        }
        
        if ($category_id <= 0) {
            throw new Exception('请选择产品分类');
        }
        
        if ($sale_price <= 0) {
            throw new Exception('请输入销售价格');
        }
        
        // 生成产品编号（可选）
        $product_code = 'P' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        // 插入产品
        $stmt = $conn->prepare("
            INSERT INTO products 
            (category_id, product_type, product_code, name, brand, model, spec, unit, 
             cost_price, sale_price, stock_quantity, stock_alert, warranty, description, 
             is_active, created_at, updated_at) 
            VALUES (?, 'hardware', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->bind_param(
            "issssssddiissi",
            $category_id, $product_code, $name, $brand, $model, $spec, $unit,
            $cost_price, $sale_price, $stock_quantity, $stock_alert, $warranty, $description, $is_active
        );
        
        if (!$stmt->execute()) {
            throw new Exception('产品添加失败：' . $stmt->error);
        }
        
        $product_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => '产品添加成功',
            'product_id' => $product_id,
            'product_code' => $product_code
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// ==================== 获取产品分类 ====================
$categories_result = $conn->query("
    SELECT id, parent_id, name 
    FROM product_categories 
    ORDER BY parent_id, sort_order, id
");

$categories = [];
$top_categories = [];
$sub_categories = [];

while ($cat = $categories_result->fetch_assoc()) {
    if ($cat['parent_id'] == 0) {
        $top_categories[] = $cat;
    } else {
        if (!isset($sub_categories[$cat['parent_id']])) {
            $sub_categories[$cat['parent_id']] = [];
        }
        $sub_categories[$cat['parent_id']][] = $cat;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增产品 - 企业管理系统</title>
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
            max-width: 1000px;
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

        .form-group.full-width {
            grid-column: 1 / -1;
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

        .form-help {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        /* 价格利润计算 */
        .price-calculation {
            background: #f7fafc;
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            border: 2px solid #e2e8f0;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .price-row.total {
            border-top: 2px solid #cbd5e0;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 600;
            color: #667eea;
        }

        /* 状态开关 */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: 0.4s;
            border-radius: 26px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #10b981;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
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
            <h1 class="page-title">新增产品</h1>
            <div class="btn-group">
                <a href="products.php" class="btn btn-back">← 返回列表</a>
                <button type="button" class="btn btn-success" onclick="saveProduct()">💾 保存产品</button>
            </div>
        </div>

        <div class="alert alert-info">
            💡 提示：标记为必填项（*）的字段必须填写。成本价和销售价将自动计算利润率。
        </div>

        <!-- 基本信息 -->
        <div class="form-card">
            <h2 class="form-section-title">基本信息</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">产品分类</label>
                    <select id="category_id" class="form-control">
                        <option value="">请选择分类</option>
                        <?php foreach ($top_categories as $top): ?>
                            <optgroup label="<?php echo htmlspecialchars($top['name']); ?>">
                                <?php if (isset($sub_categories[$top['id']])): ?>
                                    <?php foreach ($sub_categories[$top['id']] as $sub): ?>
                                        <option value="<?php echo $sub['id']; ?>">
                                            <?php echo htmlspecialchars($sub['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">产品名称</label>
                    <input type="text" id="name" class="form-control" placeholder="例如：Intel i5-12400">
                </div>

                <div class="form-group">
                    <label>品牌</label>
                    <input type="text" id="brand" class="form-control" placeholder="例如：Intel">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>型号</label>
                    <input type="text" id="model" class="form-control" placeholder="例如：i5-12400">
                </div>

                <div class="form-group">
                    <label>单位</label>
                    <select id="unit" class="form-control">
                        <option value="个">个</option>
                        <option value="块">块</option>
                        <option value="条">条</option>
                        <option value="台">台</option>
                        <option value="套">套</option>
                        <option value="根">根</option>
                        <option value="米">米</option>
                        <option value="支">支</option>
                        <option value="颗">颗</option>
                        <option value="次">次</option>
                        <option value="项">项</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>质保期限</label>
                    <input type="text" id="warranty" class="form-control" placeholder="例如：3年">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>规格描述</label>
                    <input type="text" id="spec" class="form-control" placeholder="例如：6核12线程 2.5GHz-4.4GHz 18MB缓存">
                    <span class="form-help">简短的规格说明，会显示在报价单上</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>详细描述</label>
                    <textarea id="description" class="form-control" placeholder="产品的详细介绍、特点、注意事项等"></textarea>
                    <span class="form-help">详细的产品介绍，仅内部查看</span>
                </div>
            </div>
        </div>

        <!-- 价格与库存 -->
        <div class="form-card">
            <h2 class="form-section-title">价格与库存</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>成本价（元）</label>
                    <input type="number" id="cost_price" class="form-control" value="0" min="0" step="0.01" 
                           onchange="calculateProfit()" placeholder="进货成本">
                    <span class="form-help">内部成本价，不对外显示</span>
                </div>

                <div class="form-group">
                    <label class="required">销售价（元）</label>
                    <input type="number" id="sale_price" class="form-control" value="0" min="0" step="0.01" 
                           onchange="calculateProfit()" placeholder="对外销售价">
                    <span class="form-help">报价单显示的价格</span>
                </div>

                <div class="form-group">
                    <label>当前库存</label>
                    <input type="number" id="stock_quantity" class="form-control" value="0" min="0" placeholder="库存数量">
                </div>

                <div class="form-group">
                    <label>库存预警值</label>
                    <input type="number" id="stock_alert" class="form-control" value="10" min="0" placeholder="预警值">
                    <span class="form-help">库存低于此值时预警</span>
                </div>
            </div>

            <!-- 价格利润计算 -->
            <div class="price-calculation">
                <div class="price-row">
                    <span>成本价：</span>
                    <span>¥<span id="display_cost">0.00</span></span>
                </div>
                <div class="price-row">
                    <span>销售价：</span>
                    <span>¥<span id="display_sale">0.00</span></span>
                </div>
                <div class="price-row">
                    <span>利润：</span>
                    <span>¥<span id="display_profit">0.00</span></span>
                </div>
                <div class="price-row total">
                    <span>利润率：</span>
                    <span><span id="display_profit_rate">0.00</span>%</span>
                </div>
            </div>
        </div>

        <!-- 其他设置 -->
        <div class="form-card">
            <h2 class="form-section-title">其他设置</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>产品状态</label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label class="switch">
                            <input type="checkbox" id="is_active" checked>
                            <span class="slider"></span>
                        </label>
                        <span id="status_text">启用</span>
                    </div>
                    <span class="form-help">停用后不会在报价单中显示</span>
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

    <script>
        /**
         * =====================================================
         * 状态开关处理
         * =====================================================
         */
        document.getElementById('is_active').addEventListener('change', function() {
            document.getElementById('status_text').textContent = this.checked ? '启用' : '停用';
        });

        /**
         * =====================================================
         * 计算利润和利润率
         * =====================================================
         */
        function calculateProfit() {
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const salePrice = parseFloat(document.getElementById('sale_price').value) || 0;
            
            const profit = salePrice - costPrice;
            const profitRate = costPrice > 0 ? (profit / costPrice * 100) : 0;
            
            document.getElementById('display_cost').textContent = costPrice.toFixed(2);
            document.getElementById('display_sale').textContent = salePrice.toFixed(2);
            document.getElementById('display_profit').textContent = profit.toFixed(2);
            document.getElementById('display_profit_rate').textContent = profitRate.toFixed(2);
            
            // 利润率颜色提示
            const rateElement = document.getElementById('display_profit_rate').parentElement;
            if (profitRate < 0) {
                rateElement.style.color = '#ef4444'; // 红色：亏损
            } else if (profitRate < 10) {
                rateElement.style.color = '#f59e0b'; // 橙色：低利润
            } else {
                rateElement.style.color = '#10b981'; // 绿色：正常利润
            }
        }

        /**
         * =====================================================
         * 收集表单数据
         * =====================================================
         */
        function collectFormData() {
            const categoryId = document.getElementById('category_id').value;
            const name = document.getElementById('name').value.trim();
            const brand = document.getElementById('brand').value.trim();
            const model = document.getElementById('model').value.trim();
            const spec = document.getElementById('spec').value.trim();
            const unit = document.getElementById('unit').value;
            const warranty = document.getElementById('warranty').value.trim();
            const description = document.getElementById('description').value.trim();
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const salePrice = parseFloat(document.getElementById('sale_price').value) || 0;
            const stockQuantity = parseInt(document.getElementById('stock_quantity').value) || 0;
            const stockAlert = parseInt(document.getElementById('stock_alert').value) || 10;
            const isActive = document.getElementById('is_active').checked ? 1 : 0;
            
            // 验证必填项
            if (!categoryId) {
                alert('请选择产品分类');
                return null;
            }
            
            if (!name) {
                alert('请输入产品名称');
                return null;
            }
            
            if (salePrice <= 0) {
                alert('请输入销售价格');
                return null;
            }
            
            return {
                category_id: categoryId,
                name: name,
                brand: brand,
                model: model,
                spec: spec,
                unit: unit,
                warranty: warranty,
                description: description,
                cost_price: costPrice,
                sale_price: salePrice,
                stock_quantity: stockQuantity,
                stock_alert: stockAlert,
                is_active: isActive
            };
        }

        /**
         * =====================================================
         * 保存产品
         * =====================================================
         */
        async function saveProduct() {
            const formData = collectFormData();
            
            if (!formData) {
                return;
            }
            
            // 显示加载状态
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('product_add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                document.getElementById('loading').classList.remove('active');
                
                if (result.success) {
                    alert('产品添加成功！\n产品编号：' + result.product_code);
                    window.location.href = 'products.php';
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