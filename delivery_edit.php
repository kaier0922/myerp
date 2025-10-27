<?php
/**
 * ============================================================================
 * 文件名: delivery_edit.php
 * 版本: 1.0
 * 创建日期: 2025-10-17
 * 说明: 编辑送货单页面
 * 
 * 功能说明：
 * 1. 加载现有送货单数据
 * 2. 编辑送货单信息
 * 3. 编辑送货明细
 * 4. 更新费用计算
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

// ==================== 获取参数 ====================
$delivery_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($delivery_id <= 0) {
    die('无效的送货单ID');
}

// ==================== 连接数据库 ====================
$conn = getDBConnection();

// ==================== 查询送货单信息 ====================
$delivery_sql = "SELECT * FROM deliveries WHERE id = ?";
$delivery_stmt = $conn->prepare($delivery_sql);
$delivery_stmt->bind_param('i', $delivery_id);
$delivery_stmt->execute();
$delivery_result = $delivery_stmt->get_result();

if ($delivery_result->num_rows === 0) {
    die('送货单不存在');
}

$delivery = $delivery_result->fetch_assoc();
$delivery_stmt->close();

// 检查是否可以编辑
if (in_array($delivery['status'], ['completed', 'cancelled'])) {
    die('已完成或已取消的送货单不能编辑');
}

// ==================== 查询送货明细 ====================
$items_sql = "SELECT * FROM delivery_items WHERE delivery_id = ? ORDER BY id";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param('i', $delivery_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
$items_stmt->close();

// ==================== 加载客户列表 ====================
$customers_sql = "SELECT id, company_name, contact_name, phone, address FROM customers ORDER BY company_name";
$customers = $conn->query($customers_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑送货单 - <?php echo htmlspecialchars($delivery['delivery_no']); ?></title>
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

        /* ==================== 明细表格 ==================== */
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

        /* ==================== 信息提示框 ==================== */
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

            .items-table {
                font-size: 12px;
            }

            .items-table th,
            .items-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">📝 编辑送货单 - <?php echo htmlspecialchars($delivery['delivery_no']); ?></div>
        <div class="navbar-actions">
            <a href="delivery_view.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary">取消</a>
            <button class="btn btn-success" onclick="saveDelivery()">保存修改</button>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <!-- ==================== 信息提示 ==================== -->
        <div class="info-box">
            <div class="info-box-title">📦 编辑送货单</div>
            <div class="info-box-content">
                送货单号: <strong><?php echo htmlspecialchars($delivery['delivery_no']); ?></strong> | 
                当前状态: <strong><?php 
                    $status_labels = [
                        'pending' => '待送货',
                        'delivering' => '配送中',
                        'completed' => '已完成',
                        'failed' => '失败',
                        'cancelled' => '已取消'
                    ];
                    echo $status_labels[$delivery['status']] ?? $delivery['status'];
                ?></strong>
            </div>
        </div>

        <form id="deliveryForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $delivery_id; ?>">

            <!-- ==================== 客户信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">客户信息</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">客户名称</label>
                        <input type="text" name="customer_name" class="form-input" 
                               value="<?php echo htmlspecialchars($delivery['customer_name']); ?>" 
                               placeholder="请输入客户名称" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">联系人</label>
                        <input type="text" name="contact_name" class="form-input" 
                               value="<?php echo htmlspecialchars($delivery['contact_name']); ?>"
                               placeholder="请输入联系人" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">联系电话</label>
                        <input type="tel" name="contact_phone" class="form-input" 
                               value="<?php echo htmlspecialchars($delivery['contact_phone']); ?>"
                               placeholder="请输入联系电话" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">送货地址</label>
                        <input type="text" name="delivery_address" class="form-input" 
                               value="<?php echo htmlspecialchars($delivery['delivery_address']); ?>"
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
                               value="<?php echo $delivery['delivery_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">送货时间段</label>
                        <select name="delivery_time" class="form-select">
                            <option value="">不限</option>
                            <option value="上午 (9:00-12:00)" <?php echo $delivery['delivery_time'] == '上午 (9:00-12:00)' ? 'selected' : ''; ?>>上午 (9:00-12:00)</option>
                            <option value="下午 (14:00-18:00)" <?php echo $delivery['delivery_time'] == '下午 (14:00-18:00)' ? 'selected' : ''; ?>>下午 (14:00-18:00)</option>
                            <option value="晚上 (18:00-21:00)" <?php echo $delivery['delivery_time'] == '晚上 (18:00-21:00)' ? 'selected' : ''; ?>>晚上 (18:00-21:00)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">送货人</label>
                        <input type="text" name="delivery_person" class="form-input" 
                               value="<?php echo htmlspecialchars($delivery['delivery_person']); ?>"
                               placeholder="请输入送货人姓名">
                    </div>

                    <div class="form-group">
                        <label class="form-label">车牌号</label>
                        <input type="text" name="vehicle_no" class="form-input" 
                               value="<?php echo htmlspecialchars($delivery['vehicle_no']); ?>"
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
                            <?php 
                            $index = 0;
                            while ($item = $items->fetch_assoc()): 
                            ?>
                            <tr id="itemRow<?php echo $index; ?>">
                                <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                <td>
                                    <input type="text" name="items[<?php echo $index; ?>][product_name]" 
                                           value="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                           placeholder="产品名称" required>
                                </td>
                                <td>
                                    <input type="text" name="items[<?php echo $index; ?>][product_spec]" 
                                           value="<?php echo htmlspecialchars($item['product_spec']); ?>"
                                           placeholder="规格型号">
                                </td>
                                <td>
                                    <input type="number" name="items[<?php echo $index; ?>][quantity]" 
                                           value="<?php echo $item['quantity']; ?>"
                                           min="1" onchange="calculateItemTotal(<?php echo $index; ?>)" required>
                                </td>
                                <td>
                                    <input type="text" name="items[<?php echo $index; ?>][unit]" 
                                           value="<?php echo htmlspecialchars($item['unit']); ?>">
                                </td>
                                <td>
                                    <input type="number" name="items[<?php echo $index; ?>][unit_price]" 
                                           value="<?php echo $item['unit_price']; ?>"
                                           step="0.01" min="0" onchange="calculateItemTotal(<?php echo $index; ?>)" required>
                                </td>
                                <td>
                                    <input type="text" name="items[<?php echo $index; ?>][subtotal]" 
                                           value="<?php echo number_format($item['subtotal'], 2); ?>" readonly>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-remove-item" onclick="removeItem(<?php echo $index; ?>)">删除</button>
                                </td>
                            </tr>
                            <?php 
                            $index++;
                            endwhile; 
                            ?>
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
                               value="<?php echo $delivery['goods_amount']; ?>" 
                               step="0.01" min="0" readonly>
                        <div class="form-hint">自动从明细计算</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">运费</label>
                        <input type="number" name="freight_fee" id="freightFee" class="form-input" 
                               value="<?php echo $delivery['freight_fee']; ?>"
                               step="0.01" min="0" onchange="calculateTotal()">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">支付方式</label>
                    <select name="payment_method" class="form-select">
                        <option value="">待确定</option>
                        <option value="现金" <?php echo $delivery['payment_method'] == '现金' ? 'selected' : ''; ?>>现金</option>
                        <option value="银行转账" <?php echo $delivery['payment_method'] == '银行转账' ? 'selected' : ''; ?>>银行转账</option>
                        <option value="支付宝" <?php echo $delivery['payment_method'] == '支付宝' ? 'selected' : ''; ?>>支付宝</option>
                        <option value="微信" <?php echo $delivery['payment_method'] == '微信' ? 'selected' : ''; ?>>微信</option>
                        <option value="刷卡" <?php echo $delivery['payment_method'] == '刷卡' ? 'selected' : ''; ?>>刷卡</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="collect_on_delivery" id="collectOnDelivery" 
                               value="1" <?php echo $delivery['collect_on_delivery'] ? 'checked' : ''; ?>>
                        <label for="collectOnDelivery">货到付款</label>
                    </div>
                    <div class="form-hint">勾选此项表示需要在送货时收款</div>
                </div>

                <div class="fee-summary">
                    <div class="fee-row">
                        <span>货物金额：</span>
                        <span id="summaryGoodsAmount">¥<?php echo number_format($delivery['goods_amount'], 2); ?></span>
                    </div>
                    <div class="fee-row">
                        <span>运费：</span>
                        <span id="summaryFreightFee">¥<?php echo number_format($delivery['freight_fee'], 2); ?></span>
                    </div>
                    <div class="fee-row total">
                        <span>总金额：</span>
                        <span id="summaryTotalAmount">¥<?php echo number_format($delivery['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- ==================== 备注 ==================== -->
            <div class="form-card">
                <h2 class="section-title">备注信息</h2>
                <div class="form-group">
                    <textarea name="notes" class="form-textarea" 
                              placeholder="其他需要说明的信息" rows="4"><?php echo htmlspecialchars($delivery['notes']); ?></textarea>
                </div>
            </div>
        </form>
    </main>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 编辑送货单页面加载 ===');

        let itemIndex = <?php echo $index; ?>;

        // 页面加载时计算总金额
        window.onload = function() {
            calculateTotal();
        };

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
                form.reportValidity();
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
                    window.location.href = 'delivery_view.php?id=<?php echo $delivery_id; ?>';
                } else {
                    alert('保存失败: ' + data.message);
                    saveButton.disabled = false;
                    saveButton.textContent = '保存修改';
                }
            })
            .catch(error => {
                console.error('保存错误:', error);
                alert('保存出错: ' + error.message);
                saveButton.disabled = false;
                saveButton.textContent = '保存修改';
            });
        }

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>