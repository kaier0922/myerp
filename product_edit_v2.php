<?php
/**
 * ============================================================================
 * 文件名: product_edit.php
 * 版本: 3.0
 * 创建日期: 2025-10-17
 * 说明: 产品编辑页面 - 适配真实表结构
 * 
 * 真实表结构字段映射：
 * - name (产品名称)
 * - sku (SKU编码)
 * - category_id (分类ID)
 * - product_type (产品类型)
 * - spec (规格)
 * - supplier_name (供应商)
 * - unit (单位)
 * - cost_price (成本价)
 * - default_price (默认价格)
 * - stock_quantity (库存数量)
 * - min_stock (最小库存)
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

// ==================== 获取产品ID ====================
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    die('无效的产品ID');
}

// ==================== 连接数据库 ====================
$conn = getDBConnection();

// ==================== 查询产品信息 ====================
$product_sql = "
    SELECT 
        id,
        name,
        category_id,
        product_type,
        sku,
        spec,
        supplier_name,
        unit,
        cost_price,
        default_price,
        stock_quantity,
        min_stock,
        is_active
    FROM products 
    WHERE id = ?
";

$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param('i', $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    die('产品不存在');
}

$product = $product_result->fetch_assoc();
$product_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑产品 - <?php echo htmlspecialchars($product['name']); ?></title>
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1000px;
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

        .form-input, .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .info-box-title {
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 8px;
        }

        .info-box-content {
            font-size: 14px;
            color: #075985;
        }

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
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">📦 编辑产品</div>
        <div class="navbar-actions">
            <a href="products.php" class="btn btn-secondary">取消</a>
            <button class="btn btn-success" onclick="saveProduct()">保存产品</button>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <form id="productForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $product_id; ?>">

            <!-- ==================== 产品信息提示 ==================== -->
            <div class="info-box">
                <div class="info-box-title">📝 编辑产品信息</div>
                <div class="info-box-content">
                    产品SKU: <strong><?php echo htmlspecialchars($product['sku']); ?></strong> | 
                    创建时间: <?php echo date('Y-m-d H:i'); ?>
                </div>
            </div>

            <!-- ==================== 基本信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">基本信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">产品名称</label>
                        <input type="text" name="product_name" class="form-input" 
                               value="<?php echo htmlspecialchars($product['name']); ?>"
                               placeholder="请输入产品名称" required>
                        <div class="form-hint">产品的完整名称，例如：联想ThinkPad E14</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">SKU编码</label>
                        <input type="text" name="sku" class="form-input" 
                               value="<?php echo htmlspecialchars($product['sku']); ?>"
                               placeholder="产品唯一编码" required>
                        <div class="form-hint">产品唯一标识码，不可重复</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">产品类型</label>
                        <select name="product_type" class="form-select">
                            <option value="hardware" <?php echo $product['product_type'] == 'hardware' ? 'selected' : ''; ?>>硬件</option>
                            <option value="software" <?php echo $product['product_type'] == 'software' ? 'selected' : ''; ?>>软件</option>
                            <option value="service" <?php echo $product['product_type'] == 'service' ? 'selected' : ''; ?>>服务</option>
                            <option value="consumable" <?php echo $product['product_type'] == 'consumable' ? 'selected' : ''; ?>>耗材</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">分类</label>
                        <input type="number" name="category_id" class="form-input" 
                               value="<?php echo $product['category_id']; ?>"
                               min="1" placeholder="分类ID">
                        <div class="form-hint">产品所属分类ID</div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">规格型号</label>
                        <input type="text" name="spec" class="form-input" 
                               value="<?php echo htmlspecialchars($product['spec']); ?>"
                               placeholder="例如：i5-1135G7/16GB/512GB SSD">
                        <div class="form-hint">产品的详细规格参数</div>
                    </div>
                </div>
            </div>

            <!-- ==================== 供应商和单位 ==================== -->
            <div class="form-card">
                <h2 class="section-title">供应商信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">供应商</label>
                        <input type="text" name="supplier" class="form-input" 
                               value="<?php echo htmlspecialchars($product['supplier_name']); ?>"
                               placeholder="请输入供应商名称">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">计量单位</label>
                        <input type="text" name="unit" class="form-input" 
                               value="<?php echo htmlspecialchars($product['unit']); ?>"
                               placeholder="例如：台、个、套" required>
                    </div>
                </div>
            </div>

            <!-- ==================== 价格信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">价格信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">成本价</label>
                        <input type="number" name="purchase_price" class="form-input" 
                               value="<?php echo $product['cost_price']; ?>"
                               step="0.01" min="0" placeholder="0.00">
                        <div class="form-hint">产品的采购成本价格</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">默认售价</label>
                        <input type="number" name="selling_price" class="form-input" 
                               value="<?php echo $product['default_price']; ?>"
                               step="0.01" min="0" placeholder="0.00">
                        <div class="form-hint">产品的默认销售价格</div>
                    </div>
                </div>

                <?php
                // 计算利润和利润率
                $profit = $product['default_price'] - $product['cost_price'];
                $profit_rate = $product['cost_price'] > 0 ? ($profit / $product['cost_price'] * 100) : 0;
                ?>
                <div class="info-box" style="background: #f0fdf4; border-color: #bbf7d0;">
                    <div class="info-box-title" style="color: #166534;">💰 利润分析</div>
                    <div class="info-box-content" style="color: #15803d;">
                        利润: <strong>¥<?php echo number_format($profit, 2); ?></strong> | 
                        利润率: <strong><?php echo number_format($profit_rate, 2); ?>%</strong>
                    </div>
                </div>
            </div>

            <!-- ==================== 库存信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">库存信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">当前库存</label>
                        <input type="number" name="stock_quantity" class="form-input" 
                               value="<?php echo $product['stock_quantity']; ?>"
                               min="0" placeholder="0">
                        <div class="form-hint">当前实际库存数量</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">最小库存</label>
                        <input type="number" name="min_stock" class="form-input" 
                               value="<?php echo $product['min_stock']; ?>"
                               min="0" placeholder="10">
                        <div class="form-hint">库存预警阈值，低于此值需补货</div>
                    </div>
                </div>

                <?php if ($product['stock_quantity'] <= $product['min_stock']): ?>
                <div class="info-box" style="background: #fef2f2; border-color: #fecaca;">
                    <div class="info-box-title" style="color: #991b1b;">⚠️ 库存预警</div>
                    <div class="info-box-content" style="color: #b91c1c;">
                        当前库存 (<?php echo $product['stock_quantity']; ?>) 已低于最小库存 (<?php echo $product['min_stock']; ?>)，请及时补货！
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </main>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 编辑产品页面加载 ===');

        /**
         * 保存产品
         */
        function saveProduct() {
            const form = document.getElementById('productForm');

            if (!form.checkValidity()) {
                alert('请填写所有必填项！');
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);

            const saveButton = event.target;
            saveButton.disabled = true;
            saveButton.textContent = '保存中...';

            fetch('product_save.php', {
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
                    window.location.href = 'products.php';
                } else {
                    alert('保存失败: ' + data.message);
                    saveButton.disabled = false;
                    saveButton.textContent = '保存产品';
                }
            })
            .catch(error => {
                console.error('保存错误:', error);
                alert('保存出错: ' + error.message);
                saveButton.disabled = false;
                saveButton.textContent = '保存产品';
            });
        }

        // 实时计算利润
        const costPriceInput = document.querySelector('input[name="purchase_price"]');
        const sellingPriceInput = document.querySelector('input[name="selling_price"]');

        function updateProfit() {
            const costPrice = parseFloat(costPriceInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;
            const profit = sellingPrice - costPrice;
            const profitRate = costPrice > 0 ? (profit / costPrice * 100) : 0;

            // 更新显示（如果有显示元素的话）
            console.log('利润:', profit.toFixed(2), '利润率:', profitRate.toFixed(2) + '%');
        }

        costPriceInput.addEventListener('input', updateProfit);
        sellingPriceInput.addEventListener('input', updateProfit);

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>