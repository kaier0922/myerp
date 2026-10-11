<?php
/**
 * ============================================================================
 * 文件名: delivery_edit.php
 * 版本: 1.1
 * 创建日期: 2025-10-17
 * 更新日期: 2025-11-20
 * 说明: 编辑送货单页面（含序列号管理）
 * 
 * 功能说明：
 * 1. 加载现有送货单数据
 * 2. 编辑送货单信息
 * 3. 编辑送货明细（含序列号）
 * 4. 支持条码枪输入序列号
 * 5. 更新费用计算
 * ============================================================================
 */

session_start();
require_once 'config.php';
require_once 'auth_helper.php';

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
$delivery_sql = "SELECT d.*, q.unit_qty AS quote_unit_qty, q.final_amount AS quote_final_amount FROM deliveries d LEFT JOIN quotes q ON d.quote_id = q.id WHERE d.id = ? AND d.user_id = " . intval($_SESSION['user_id']) . "";
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

// 检查是否已审核
if (isset($delivery['approved']) && $delivery['approved']) {
    die('已审核的送货单不能编辑');
}

// ==================== 查询送货明细 ====================
$items_sql = "SELECT di.*, COALESCE(di.category, pc.name) as cat_name FROM delivery_items di LEFT JOIN products p ON di.product_id=p.id LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE di.delivery_id = ? ORDER BY di.id";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param('i', $delivery_id);
$items_stmt->execute();
$items_arr = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// 从明细项实时计算总金额（避免DB存储的旧值与明细不一致）
if (!empty($delivery['quote_id']) && !empty($delivery['quote_final_amount'])) {
    $display_goods_amount = floatval($delivery['quote_final_amount']);
} else {
    $display_goods_amount = array_sum(array_map(function($it) {
        return floatval($it['quantity']) * floatval($it['unit_price']);
    }, $items_arr));
}

// ==================== 加载客户列表 ====================
$customers_sql = "SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name, phone, address FROM customers WHERE " . tenantWhere() . " ORDER BY company_name";
$customers = $conn->query($customers_sql);

// ==================== 加载产品大类 ====================
$categories_res = $conn->query("SELECT pc.id, pc.name FROM product_categories pc WHERE EXISTS (SELECT 1 FROM products p WHERE p.category_id=pc.id AND p.is_active=1 AND (p.user_id=0 OR p.user_id=" . intval($_SESSION['user_id']) . ")) ORDER BY pc.sort_order, pc.id");
$categories_list = [];
while ($cat = $categories_res->fetch_assoc()) $categories_list[] = $cat;
$categories_json = json_encode($categories_list, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
<?php $page_title = '编辑送货单 - ' . htmlspecialchars($delivery['delivery_no']); include __DIR__ . '/head.php'; ?>
    <style>
        /* ==================== 全局样式 ==================== */

        /* ==================== 按钮样式 ==================== */

        /* ==================== 主内容区 ==================== */
        .page-inner {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ==================== 表单卡片 ==================== */

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* ==================== 表单样式 ==================== */

        .form-row.full {
            grid-template-columns: 1fr;
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
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
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
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .items-table { width: 100%; border-collapse: collapse; min-width: 860px; }

        .items-table thead {
            background: #f7fafc;
        }

        .items-table th { padding: 12px 8px; text-align: left; font-size: 13px; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }

        .items-table td { padding: 12px 8px; border-bottom: 1px solid #e2e8f0; }

        .items-table input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 14px;
        }

        .items-table input:focus {
            outline: none;
            border-color: #1f6dad;
            box-shadow: 0 0 0 2px rgba(31, 109, 173, 0.1);
        }

        /* 序列号输入框特殊样式 */
        .items-table input.serial-input {
            font-family: 'Courier New', monospace;
            background: #f0f9ff;
            border-color: #bae6fd;
        }

        .items-table input.serial-input:focus {
            background: white;
            border-color: #0284c7;
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.1);
        }

        .btn-remove-item {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            white-space: nowrap;
        }

        .btn-remove-item:hover {
            background: #dc2626;
        }

        .btn-add-item {
            background: #1f6dad;
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
            background: #135286;
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

        /* ==================== 序列号提示 ==================== */
        .serial-hint {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 12px;
            font-size: 13px;
            color: #92400e;
        }

        .serial-hint strong {
            color: #78350f;
        }

        /* ==================== 响应式 ==================== */
        @media (max-width: 768px) {

            .items-table { font-size: 12px; }

            .items-table th,
            .items-table td { padding: 8px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">✏️ 编辑送货单 - <?php echo htmlspecialchars($delivery['delivery_no']); ?></h1>
            <div style="display:flex;gap:10px;">
                <a href="delivery_view.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary">取消</a>
                <button class="btn btn-primary" onclick="saveDelivery()">💾 保存修改</button>
            </div>
        </div>
        <div class="page-inner">
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

                <!-- 客户选择器 -->
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">从客户列表选择</label>
                    <select id="customerSelect" class="form-input" onchange="fillCustomer(this)" style="background:white;">
                        <option value="">-- 手动输入 / 搜索选择 --</option>
                        <?php if ($customers && $customers->num_rows > 0): ?>
                            <?php while ($cust = $customers->fetch_assoc()): ?>
                            <option value="<?=htmlspecialchars($cust['id'])?>"
                                data-name="<?=htmlspecialchars($cust['company_name'])?>"
                                data-contact="<?=htmlspecialchars($cust['contact_name']??'')?>"
                                data-phone="<?=htmlspecialchars($cust['phone']??'')?>"
                                data-address="<?=htmlspecialchars($cust['address']??'')?>"
                                <?php echo $cust['company_name']===$delivery['customer_name']?'selected':''; ?>>
                                <?=htmlspecialchars($cust['company_name'])?>
                            </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    <input type="hidden" name="customer_id" id="customerId"
                           value="<?=htmlspecialchars($delivery['customer_id']??'')?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">客户名称</label>
                        <input type="text" name="customer_name" id="customerName" class="form-input"
                               value="<?=htmlspecialchars($delivery['customer_name'])?>"
                               placeholder="请输入或从上方选择客户" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">联系人</label>
                        <input type="text" name="contact_name" id="contactName" class="form-input"
                               value="<?=htmlspecialchars($delivery['contact_name'])?>"
                               placeholder="请输入联系人">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">联系电话</label>
                        <input type="tel" name="contact_phone" id="contactPhone" class="form-input"
                               value="<?=htmlspecialchars($delivery['contact_phone'])?>"
                               placeholder="请输入联系电话">
                    </div>
                    <div class="form-group">
                        <label class="form-label">送货地址</label>
                        <input type="text" name="delivery_address" id="deliveryAddress" class="form-input"
                               value="<?=htmlspecialchars($delivery['delivery_address'])?>"
                               placeholder="请输入送货地址">
                    </div>
                </div>
            </div>


            <!-- ==================== 送货信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">送货信息</h2>

                <!-- 第一行：日期 + 时间段 + 送货人 + 车牌 -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div class="form-group">
                        <label class="form-label required">送货日期</label>
                        <input type="date" name="delivery_date" class="form-input"
                               value="<?php echo $delivery['delivery_date']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">送货时间段</label>
                        <select name="delivery_time" class="form-select">
                            <option value="">不限</option>
                            <option value="上午 (9:00-12:00)" <?php echo $delivery['delivery_time']=='上午 (9:00-12:00)'?'selected':''; ?>>上午 (9:00-12:00)</option>
                            <option value="下午 (14:00-18:00)" <?php echo $delivery['delivery_time']=='下午 (14:00-18:00)'?'selected':''; ?>>下午 (14:00-18:00)</option>
                            <option value="晚上 (18:00-21:00)" <?php echo $delivery['delivery_time']=='晚上 (18:00-21:00)'?'selected':''; ?>>晚上 (18:00-21:00)</option>
                        </select>
                    </div>
                    <div class="form-group" id="field-person">
                        <label class="form-label">送货人</label>
                        <input type="text" name="delivery_person" class="form-input"
                               value="<?php echo htmlspecialchars($delivery['delivery_person']??''); ?>"
                               placeholder="送货人姓名">
                    </div>
                    <div class="form-group" id="field-vehicle">
                        <label class="form-label">车牌号</label>
                        <input type="text" name="vehicle_no" class="form-input"
                               value="<?php echo htmlspecialchars($delivery['vehicle_no']??''); ?>"
                               placeholder="粤A12345">
                    </div>
                </div>

                <!-- 配送方式 -->
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <span style="font-size:13px;font-weight:600;color:#4a5568;white-space:nowrap;">配送方式：</span>
                    <?php $dtype = $delivery['delivery_type'] ?? 'self'; ?>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;" id="lbl-self">
                        <input type="radio" name="delivery_type" value="self" <?php echo $dtype==='self'?'checked':''; ?> onchange="switchType('self')"> 🚚 自送
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;" id="lbl-express">
                        <input type="radio" name="delivery_type" value="express" <?php echo $dtype==='express'?'checked':''; ?> onchange="switchType('express')"> 📦 快递
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;" id="lbl-virtual">
                        <input type="radio" name="delivery_type" value="virtual" <?php echo $dtype==='virtual'?'checked':''; ?> onchange="switchType('virtual')"> 🔗 虚拟发货
                    </label>
                </div>

                <!-- 快递字段 -->
                <div id="fields-express" style="display:none;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div class="form-group">
                        <label class="form-label">快递公司</label>
                        <select name="express_company" class="form-select">
                            <option value="">请选择快递公司</option>
                            <?php foreach(['顺丰速运','京东快递','中通快递','圆通速递','韵达快递','申通快递','极兔速递','德邦快递','其他'] as $ec): ?>
                            <option value="<?=$ec?>" <?php echo ($delivery['express_company']??'')===$ec?'selected':''; ?>><?=$ec?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">快递单号</label>
                        <input type="text" name="express_no" class="form-input"
                               value="<?php echo htmlspecialchars($delivery['express_no']??''); ?>"
                               placeholder="请输入快递单号">
                    </div>
                </div>

                <!-- 虚拟发货字段 -->
                <div id="fields-virtual" style="display:none;margin-bottom:14px;">
                    <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                        <div class="form-group">
                            <label class="form-label">发送方式</label>
                            <select name="virtual_method" class="form-select">
                                <option value="">请选择发送方式</option>
                                <?php foreach(['邮件','微信','QQ','短信','其他'] as $vm): ?>
                                <option value="<?=$vm?>" <?php echo ($delivery['virtual_method']??'')===$vm?'selected':''; ?>><?=$vm?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">发货内容</label>
                            <textarea name="virtual_content" class="form-input" rows="3"
                                style="width:100%;resize:vertical;"
                                placeholder="请输入链接、激活码或其他发货内容..."><?php echo htmlspecialchars($delivery['virtual_content']??''); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>


            <!-- ==================== 送货明细 ==================== -->
            <div class="form-card">
                <h2 class="section-title">送货明细</h2>
                
                <div style="margin-bottom:10px;">
                    <button type="button" class="btn-add-item" onclick="addItem()" style="width:auto;margin:0;padding:8px 18px;">➕ 添加产品</button>
                </div>
                <div class="items-container" style="overflow-x:auto;">
                    <table class="items-table" style="min-width:900px;">
                        <thead>
                            <tr>
                                <th width="40">序号</th>
                                <th width="110">产品类型</th>
                                <th>产品名称/服务项目</th>
                                <th width="200">品牌型号/配置参数</th>
                                <th width="55">单位</th>
                                <th width="65">数量</th>
                                <th width="100" class="price-col">单价(¥)</th>
                                <th width="100" class="price-col">金额(¥)</th>
                                <th width="60">操作</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php foreach ($items_arr as $index => $item): ?>
                            <tr id="itemRow<?php echo $index; ?>">
                                <td style="text-align:center;" class="seq-cell"><?php echo $index + 1; ?></td>
                                <td>
                                    <input type="hidden" name="items[<?php echo $index; ?>][category]" class="item-category-hidden" value="<?php echo htmlspecialchars($item['cat_name'] ?? ''); ?>">
                                    <input type="text" class="item-category-text" value="<?php echo htmlspecialchars($item['cat_name'] ?? ''); ?>" placeholder="产品类型" style="width:100%;" oninput="this.previousElementSibling.value=this.value">
                                </td>
                                <td>
                                    <input type="hidden" name="items[<?php echo $index; ?>][product_id]" value="<?php echo intval($item['product_id'] ?? 0); ?>">
                                    <input type="text" name="items[<?php echo $index; ?>][product_name]"
                                           value="<?php echo htmlspecialchars($item['product_name']); ?>"
                                           placeholder="产品名称/服务项目" required style="width:100%;">
                                </td>
                                <td>
                                    <input type="text" name="items[<?php echo $index; ?>][product_spec]"
                                           value="<?php echo htmlspecialchars($item['product_spec'] ?? ''); ?>"
                                           placeholder="品牌型号/配置参数" style="width:100%;">
                                </td>
                                <td>
                                    <input type="text" name="items[<?php echo $index; ?>][unit]"
                                           value="<?php echo htmlspecialchars($item['unit'] ?? '台'); ?>" style="width:100%;">
                                </td>
                                <td>
                                    <input type="number" name="items[<?php echo $index; ?>][quantity]"
                                           value="<?php echo intval($item['quantity']); ?>"
                                           min="1" step="1" class="item-qty" onchange="recalcRow(this)" required style="width:100%;">
                                </td>
                                <td class="price-col">
                                    <input type="number" name="items[<?php echo $index; ?>][unit_price]"
                                           value="<?php echo floatval($item['unit_price']); ?>"
                                           step="any" min="0" class="item-price" onchange="recalcRow(this)" required style="width:100%;">
                                </td>
                                <td class="price-col">
                                    <input type="hidden" name="items[<?php echo $index; ?>][subtotal]" class="item-subtotal" value="<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>">
                                    <strong class="item-sub">¥<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></strong>
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn-remove-item" onclick="removeItem(this)">删除</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ==================== 费用信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">费用信息</h2>

                <div style="display:grid;grid-template-columns:2fr 1fr 2fr 1fr;gap:14px;align-items:end;margin-bottom:14px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">总金额</label>
                        <input type="number" name="goods_amount" id="goodsAmount" class="form-input"
                               value="<?php echo $display_goods_amount; ?>"
                               step="any" min="0" readonly>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">运费</label>
                        <input type="number" name="freight_fee" id="freightFee" class="form-input"
                               value="<?php echo $delivery['freight_fee'] ?? 0; ?>"
                               step="any" min="0" onchange="calculateTotal()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">支付方式</label>
                        <select name="payment_method" class="form-select">
                            <option value="">待确定</option>
                            <option value="现金" <?php echo ($delivery['payment_method'] ?? '') == '现金' ? 'selected' : ''; ?>>现金</option>
                            <option value="银行转账" <?php echo ($delivery['payment_method'] ?? '') == '银行转账' ? 'selected' : ''; ?>>银行转账</option>
                            <option value="支付宝" <?php echo ($delivery['payment_method'] ?? '') == '支付宝' ? 'selected' : ''; ?>>支付宝</option>
                            <option value="微信" <?php echo ($delivery['payment_method'] ?? '') == '微信' ? 'selected' : ''; ?>>微信</option>
                            <option value="刷卡" <?php echo ($delivery['payment_method'] ?? '') == '刷卡' ? 'selected' : ''; ?>>刷卡</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">　</label>
                        <div class="checkbox-group" style="height:40px;">
                            <input type="checkbox" name="collect_on_delivery" id="collectOnDelivery"
                                   value="1" <?php echo ($delivery['collect_on_delivery'] ?? 0) ? 'checked' : ''; ?>>
                            <label for="collectOnDelivery">货到付款</label>
                        </div>
                    </div>
                </div>

                <div class="fee-summary">
                    <?php if (!empty($delivery['quote_unit_qty']) && $delivery['quote_unit_qty'] > 1): ?>
                    <div class="fee-row">
                        <span>套数：</span>
                        <span><?php echo intval($delivery['quote_unit_qty']); ?> 套</span>
                    </div>
                    <?php endif; ?>
                    <div class="fee-row">
                        <span>总金额：</span>
                        <span id="summaryGoodsAmount">¥<?php echo number_format($display_goods_amount, 2); ?></span>
                    </div>
                    <div class="fee-row">
                        <span>运费：</span>
                        <span id="summaryFreightFee">¥<?php echo number_format($delivery['freight_fee'] ?? 0, 2); ?></span>
                    </div>
                    <div class="fee-row total">
                        <span>总金额：</span>
                        <span id="summaryTotalAmount">¥<?php echo number_format($display_goods_amount + floatval($delivery['freight_fee'] ?? 0), 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- ==================== 备注 ==================== -->
            <div class="form-card">
                <h2 class="section-title">备注信息</h2>
                <div class="form-group">
                    <textarea name="notes" class="form-textarea" 
                              placeholder="可在此记录序列号、保修日期等信息，例如：主板 SN:ABC123456 保修至2027-04-06" rows="4"><?php echo htmlspecialchars($delivery['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </form>
        </div><!-- end page-inner -->
    </main>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 编辑送货单页面加载 ===');

        let itemIndex = <?php echo count($items_arr); ?>;
        const QUOTE_UNIT_QTY = <?php echo intval($delivery['quote_unit_qty'] ?? 1); ?>;
        const QUOTE_FINAL_AMOUNT = <?php echo floatval($delivery['quote_final_amount'] ?? 0); ?>;
        const categoriesData = <?php echo $categories_json; ?>;
        const productsCache  = {};

        async function fetchProductsByCategory(catId) {
            if (productsCache[catId]) return productsCache[catId];
            const r = await fetch('api_products_by_category.php?cat_id=' + catId);
            const d = await r.json();
            productsCache[catId] = d;
            return d;
        }

        function escHtml(t) {
            if (!t) return '';
            const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
        }

        function buildCatOpts() {
            let h = '<option value="">— 请选产品大类 —</option>';
            categoriesData.forEach(c => { h += `<option value="${c.id}">${escHtml(c.name)}</option>`; });
            return h;
        }

        async function buildProdOpts(catId, kw) {
            if (!catId) return '<option value="">— 请先选产品大类 —</option>';
            const products = await fetchProductsByCategory(catId);
            if (!products || !products.length) return '<option value="">— 该分类暂无产品 —</option>';
            const kl = (kw||'').toLowerCase().trim();
            const list = kl ? products.filter(p=>(p.name||'').toLowerCase().includes(kl)||(p.spec||'').toLowerCase().includes(kl)) : products;
            let h = `<option value="">— 请选择 (${list.length}) —</option>`;
            list.forEach(p => {
                h += `<option value="${p.id}"
                    data-name="${escHtml(p.name)}" data-spec="${escHtml(p.spec||'')}"
                    data-price="${p.default_price||0}" data-unit="${escHtml(p.unit||'台')}"
                    data-cat="${escHtml(p.category_name||'')}">
                    ${escHtml(p.name)} — ¥${p.default_price||0}
                </option>`;
            });
            return h;
        }

        async function onNrCatChange(id, sel) {
            document.getElementById('nr-fi-'+id).value = '';
            document.getElementById('nr-ps-'+id).innerHTML = await buildProdOpts(sel.value, '');
            const row = document.getElementById('nr-row-'+id);
            const catHidden = row?.querySelector('.item-category-hidden');
            if (catHidden) catHidden.value = sel.options[sel.selectedIndex]?.text || '';
        }

        async function onNrFilter(id, kw) {
            const catId = document.getElementById('nr-cs-'+id).value;
            document.getElementById('nr-ps-'+id).innerHTML = await buildProdOpts(catId, kw);
        }

        function onNrProdSel(id, sel) {
            const opt = sel.options[sel.selectedIndex];
            const row = document.getElementById('nr-row-'+id);
            if (!opt || !opt.value) return;
            row.querySelector('.item-name-input').value  = opt.dataset.name  || '';
            row.querySelector('.item-spec-input').value  = opt.dataset.spec  || '';
            row.querySelector('.item-price').value       = opt.dataset.price || 0;
            row.querySelector('.item-unit-input').value  = opt.dataset.unit  || '台';
            row.querySelector('input[name*="[product_id]"]').value = opt.value;
            // 大类名称从大类 select 的选中项文字写入 hidden field
            const catSel = document.getElementById('nr-cs-'+id);
            const catText = catSel.options[catSel.selectedIndex]?.text || '';
            const catHidden = row.querySelector('.item-category-hidden');
            if (catHidden) catHidden.value = catText;
            recalcRow(row.querySelector('.item-qty'));
        }

        function togglePriceColumns(hide) {
            document.querySelectorAll('.price-col').forEach(el => {
                el.style.display = hide ? 'none' : '';
            });
        }

        // 页面加载时计算总金额
        window.onload = function() {
            calculateTotal();
        };

        function recalcRow(input) {
            const row = input.closest('tr');
            const qty   = parseFloat(row.querySelector('.item-qty').value)   || 0;
            const price = parseFloat(row.querySelector('.item-price').value)  || 0;
            const sub   = (qty * price).toFixed(2);
            row.querySelector('.item-subtotal').value  = sub;
            row.querySelector('.item-sub').textContent = '¥' + sub;
            calculateTotal();
        }

        function addItem() {
            const id = itemIndex++;
            const priceStyle = '';
            const row = document.createElement('tr');
            row.id = 'nr-row-' + id;
            row.style.background = '#fffbeb';
            row.innerHTML = `
                <td style="text-align:center;" class="seq-cell"></td>
                <td>
                    <input type="hidden" name="items[${id}][category]" class="item-category-hidden" value="">
                    <select id="nr-cs-${id}" style="width:100%;background:#eff6ff;border:1px solid #93c5fd;border-radius:4px;padding:4px 6px;font-weight:600;color:#135286;font-size:12px;"
                        onchange="onNrCatChange(${id},this)">${buildCatOpts()}</select>
                </td>
                <td>
                    <input type="hidden" name="items[${id}][product_id]" value="0">
                    <input type="text" name="items[${id}][product_name]" class="item-name-input"
                        placeholder="产品名称/服务项目" required style="width:100%;margin-bottom:3px;">
                    <input id="nr-fi-${id}" type="text" placeholder="🔍 关键字过滤"
                        oninput="onNrFilter(${id},this.value)"
                        style="width:100%;font-size:12px;margin-bottom:2px;">
                    <select id="nr-ps-${id}" onchange="onNrProdSel(${id},this)" style="width:100%;font-size:12px;">
                        <option value="">— 请先选产品大类 —</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="items[${id}][product_spec]" class="item-spec-input"
                        placeholder="品牌型号/配置参数" style="width:100%;">
                </td>
                <td>
                    <input type="text" name="items[${id}][unit]" class="item-unit-input" value="台" style="width:100%;">
                </td>
                <td>
                    <input type="number" name="items[${id}][quantity]" class="item-qty" value="1" min="1" step="1"
                        onchange="recalcRow(this)" required style="width:100%;">
                </td>
                <td class="price-col" ${priceStyle}>
                    <input type="number" name="items[${id}][unit_price]" class="item-price" value="0" step="any" min="0"
                        onchange="recalcRow(this)" required style="width:100%;">
                </td>
                <td class="price-col" ${priceStyle}>
                    <input type="hidden" name="items[${id}][subtotal]" class="item-subtotal" value="0.00">
                    <strong class="item-sub">¥0.00</strong>
                </td>
                <td style="text-align:center;">
                    <button type="button" class="btn-remove-item" onclick="removeItem(this)">删除</button>
                </td>
            `;
            document.getElementById('itemsBody').appendChild(row);
            updateRowNumbers();
        }

        function removeItem(btn) {
            const rows = document.querySelectorAll('#itemsBody tr');
            if (rows.length <= 1) { alert('⚠️ 至少保留一个产品'); return; }
            btn.closest('tr').remove();
            updateRowNumbers();
            calculateTotal();
        }

        function updateRowNumbers() {
            document.querySelectorAll('#itemsBody tr').forEach((row, i) => {
                const cell = row.querySelector('.seq-cell');
                if (cell) cell.textContent = i + 1;
            });
        }

        function calculateItemTotal(index) {
            const row = document.getElementById('itemRow' + index);
            if (!row) return;
            recalcRow(row.querySelector('.item-qty'));
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
            // unit_qty>1时总金额取报价单final_amount，与delivery_view.php逻辑一致
            const totalAmount = QUOTE_UNIT_QTY > 1 ? QUOTE_FINAL_AMOUNT : (goodsAmount + freightFee);

            document.getElementById('goodsAmount').value = goodsAmount.toFixed(2);
            document.getElementById('summaryGoodsAmount').textContent = '¥' + goodsAmount.toFixed(2);
            document.getElementById('summaryFreightFee').textContent = '¥' + freightFee.toFixed(2);
            document.getElementById('summaryTotalAmount').textContent = '¥' + totalAmount.toFixed(2);
        }

        /**
         * 保存送货单
         */
        function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('customerId').value    = opt.value;
    document.getElementById('customerName').value  = opt.dataset.name    || '';
    document.getElementById('contactName').value   = opt.dataset.contact || '';
    document.getElementById('contactPhone').value  = opt.dataset.phone   || '';
    document.getElementById('deliveryAddress').value = opt.dataset.address || '';
}


function switchType(type) {
    ['self','express','virtual'].forEach(t => {
        const lbl = document.getElementById('lbl-'+t);
        if (lbl) { lbl.style.borderColor = t===type ? '#1f6dad' : '#e2e8f0'; lbl.style.background = t===type ? '#f0f7ff' : 'white'; }
    });
    const person  = document.getElementById('field-person');
    const vehicle = document.getElementById('field-vehicle');
    if (person)  person.style.display  = type==='self' ? '' : 'none';
    if (vehicle) vehicle.style.display = type==='self' ? '' : 'none';
    const express = document.getElementById('fields-express');
    if (express) express.style.display = type==='express' ? 'grid' : 'none';
    const virt = document.getElementById('fields-virtual');
    if (virt) virt.style.display = type==='virtual' ? 'block' : 'none';
}

        function saveDelivery() {
            const form = document.getElementById('deliveryForm');

            if (!form.checkValidity()) {
                alert('⚠️ 请填写所有必填项！');
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);

            const saveButton = event.target;
            saveButton.disabled = true;
            saveButton.textContent = '💾 保存中...';

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
                    alert('✅ ' + data.message);
                    window.location.href = 'delivery_view.php?id=<?php echo $delivery_id; ?>';
                } else {
                    alert('❌ 保存失败: ' + data.message);
                    saveButton.disabled = false;
                    saveButton.textContent = '💾 保存修改';
                }
            })
            .catch(error => {
                console.error('保存错误:', error);
                alert('❌ 保存出错: ' + error.message);
                saveButton.disabled = false;
                saveButton.textContent = '💾 保存修改';
            });
        }

        console.log('=== 初始化完成 ===');
        console.log('💡 提示：序列号字段支持条码枪扫描输入');
    
// 初始化配送方式显示
document.addEventListener('DOMContentLoaded', function() {
    const checked = document.querySelector('input[name="delivery_type"]:checked');
    if (checked) switchType(checked.value);
});
</script>
</body>
</html>
