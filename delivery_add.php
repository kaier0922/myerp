<?php
/**
 * delivery_add.php - 新建送货单页面 v2.1
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

$conn = getDBConnection();

$customers_sql = "SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name, phone, address FROM customers WHERE " . tenantWhere() . " ORDER BY company_name";
$customers = $conn->query($customers_sql);

$quotes_sql = "
    SELECT q.id, q.quote_no, COALESCE(c.short_name, c.company_name) AS company_name, q.final_amount, q.unit_qty, q.quote_date
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.approved = 1 AND " . tenantWhere("q") . "
    AND q.id NOT IN (SELECT quote_id FROM deliveries WHERE quote_id IS NOT NULL AND quote_id > 0 AND " . tenantWhere() . ")
    ORDER BY q.created_at DESC
    LIMIT 100
";
$quotes = $conn->query($quotes_sql);

// 加载分类层级：大类（parent_id=0）和子类
$cats_res = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE is_active=1 ORDER BY sort_order, id");
$parent_cats_arr = [];
$cats_by_parent  = [];
while ($c = $cats_res->fetch_assoc()) {
    if ($c['parent_id'] == 0) { $parent_cats_arr[] = $c; }
    else { $cats_by_parent[$c['parent_id']][] = $c; }
}

$conn->close();

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<?php $page_title = '新建送货单'; include __DIR__ . '/head.php'; ?>
<style>

/* 导航栏 */

/* 侧边栏 */

/* 主内容 */
.content-inner{max-width:960px;margin:0 auto;}

/* 按钮 */
.btn-danger{background:#ef4444;color:white;padding:5px 10px;font-size:12px;border-radius:4px;white-space:nowrap;}
.btn-danger:hover{background:#dc2626;}

/* 卡片 */
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:24px;overflow:hidden;}
.card-header{padding:16px 24px;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#1a202c;display:flex;align-items:center;gap:8px;}
.card-body{padding:24px;}

/* 来源选择 */
.source-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:16px;}
.source-option{border:2px solid #e2e8f0;border-radius:12px;padding:20px;cursor:pointer;transition:all .2s;text-align:center;}
.source-option:hover{border-color:#1f6dad;background:#f0f7ff;}
.source-option.active{border-color:#1f6dad;background:#f0f7ff;}
.source-option input{display:none;}
.source-icon{font-size:28px;margin-bottom:8px;}
.source-title{font-size:15px;font-weight:600;color:#1a202c;}
.source-desc{font-size:12px;color:#718096;margin-top:4px;}

/* 客户模式切换 */
.mode-tabs{display:flex;gap:8px;margin-bottom:16px;}
.mode-tab{padding:8px 16px;border:2px solid #e2e8f0;border-radius:8px;background:white;cursor:pointer;font-size:13px;font-weight:500;color:#4a5568;transition:all .2s;}
.mode-tab:hover{border-color:#1f6dad;}
.mode-tab.active{border-color:#1f6dad;background:#f0f7ff;color:#1f6dad;}

/* 表单 */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.form-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-label{font-size:13px;font-weight:500;color:#4a5568;}
.form-label.req::after{content:'*';color:#ef4444;margin-left:3px;}
.form-input,.form-select,.form-textarea{padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;transition:all .2s;font-family:inherit;}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.form-textarea{resize:vertical;min-height:80px;}

/* 配送方式 */
.type-tabs{display:flex;gap:10px;margin-bottom:16px;}
.type-tab{display:flex;align-items:center;gap:6px;padding:8px 16px;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;transition:all .2s;background:white;}
.type-tab:hover{border-color:#1f6dad;}
.type-tab.active{border-color:#1f6dad;background:#f0f7ff;color:#1f6dad;}
.type-tab input{display:none;}

/* 明细表格 */
.items-wrap{border:1px solid #e2e8f0;border-radius:8px;overflow:auto;margin-bottom:12px;}
.items-table{width:100%;border-collapse:collapse;min-width:780px;table-layout:fixed;}
.items-table th{padding:10px 8px;background:#f7fafc;font-size:12px;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;text-align:center;overflow:hidden;}
.items-table td{padding:6px 4px;border-bottom:1px solid #f0f4f8;vertical-align:top;overflow:hidden;}
.items-table input,.items-table select{width:100%;padding:5px 7px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;box-sizing:border-box;font-family:inherit;}
.items-table input:focus,.items-table select:focus{outline:none;border-color:#1f6dad;}
.items-table input[readonly]{background:#f7fafc;color:#4a5568;}
.s-mb{margin-bottom:3px;}
.cust-badge{font-size:11px;color:#92400e;background:#fffbeb;padding:3px 7px;border-radius:4px;margin-bottom:3px;display:block;}
.btn-add-row{width:100%;padding:10px;border:2px dashed #cbd5e0;border-radius:8px;background:white;color:#718096;cursor:pointer;font-size:13px;transition:all .2s;}
.btn-add-row:hover{border-color:#1f6dad;color:#1f6dad;background:#f0f7ff;}

/* 费用汇总 */
.fee-box{background:#f7fafc;border-radius:10px;padding:16px;margin-top:16px;}
.fee-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:14px;color:#4a5568;}
.fee-row.unit-qty-row{color:#1f6dad;font-weight:500;}
.fee-row.total{border-top:2px solid #e2e8f0;margin-top:8px;padding-top:12px;font-size:18px;font-weight:700;color:#1a202c;}

/* 隐藏元素 */
.hidden{display:none!important;}
</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容 -->
<main class="main-content">
<div class="content-inner">

<!-- 顶部操作栏 -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <h1 style="font-size:28px;font-weight:700;color:#1a202c;">🚚 新建送货单</h1>
        <p style="color:#718096;font-size:14px;margin-top:4px;">创建新的送货单，可从报价单导入</p>
    </div>
    <div style="display:flex;gap:12px;">
        <a href="deliveries.php" class="btn btn-secondary">← 返回列表</a>
        <button class="btn btn-primary" onclick="saveDelivery()">💾 保存送货单</button>
    </div>
</div>

<form id="deliveryForm">
<input type="hidden" name="action" value="add">
<input type="hidden" name="quote_id" id="quoteId" value="">

<!-- 来源选择 -->
<div class="card">
    <div class="card-header">📌 送货单来源</div>
    <div class="card-body">
        <div class="source-grid">
            <label class="source-option active" onclick="selectSource('manual',this)">
                <input type="radio" name="source" value="manual" checked>
                <div class="source-icon">📝</div>
                <div class="source-title">手动创建</div>
                <div class="source-desc">手动输入客户和产品信息</div>
            </label>
            <label class="source-option" onclick="selectSource('quote',this)">
                <input type="radio" name="source" value="quote">
                <div class="source-icon">📋</div>
                <div class="source-title">从报价单导入</div>
                <div class="source-desc">选择已审核的报价单自动填充</div>
            </label>
        </div>
        <div id="quoteSelectField" class="hidden">
            <div class="form-group">
                <label class="form-label">选择报价单</label>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                    <label style="font-size:13px;color:#4a5568;">明细模式：</label>
                    <select id="deliveryMode" class="form-select" style="width:160px;">
                        <option value="detail">完整明细</option>
                        <option value="summary">汇总一行</option>
                    </select>
                </div>
                <select class="form-select" onchange="loadQuoteData(this)">
                    <option value="">-- 请选择已审核的报价单 --</option>
                    <?php if ($quotes && $quotes->num_rows > 0): ?>
                        <?php while ($q = $quotes->fetch_assoc()): ?>
                        <option value="<?php echo $q['id']; ?>" data-unit-qty="<?php echo intval($q['unit_qty']??1); ?>">
                            <?php echo e($q['quote_no']); ?> — <?php echo e($q['company_name']); ?> — ¥<?php echo number_format($q['final_amount'],2); ?>
                            <?php if(($q['unit_qty']??1)>1): ?> (<?php echo $q['unit_qty']; ?>套)<?php endif; ?>
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- 客户信息 -->
<div class="card">
    <div class="card-header">🏢 客户信息</div>
    <div class="card-body">
        <div class="mode-tabs">
            <button type="button" class="mode-tab active" onclick="switchCustomerMode('existing',this)">选择现有客户</button>
            <button type="button" class="mode-tab" onclick="switchCustomerMode('manual',this)">手动输入</button>
        </div>
        <div id="existingCustomerField">
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">选择客户</label>
                <select class="form-select" onchange="fillCustomer(this)">
                    <option value="">-- 请选择客户 --</option>
                    <?php if ($customers && $customers->num_rows > 0): ?>
                        <?php while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>"
                            data-name="<?php echo e($c['company_name']); ?>"
                            data-contact="<?php echo e($c['contact_name']); ?>"
                            data-phone="<?php echo e($c['phone']); ?>"
                            data-address="<?php echo e($c['address']??''); ?>">
                            <?php echo e($c['company_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label req">客户名称</label>
                <input type="text" name="customer_name" id="customerName" class="form-input" placeholder="客户公司名称" required>
            </div>
            <div class="form-group">
                <label class="form-label">联系人</label>
                <input type="text" name="contact_name" id="contactName" class="form-input" placeholder="请输入联系人">
            </div>
            <div class="form-group">
                <label class="form-label">联系电话</label>
                <input type="tel" name="contact_phone" id="contactPhone" class="form-input" placeholder="请输入联系电话">
            </div>
            <div class="form-group">
                <label class="form-label">送货地址</label>
                <input type="text" name="delivery_address" id="deliveryAddress" class="form-input" placeholder="请输入送货地址">
            </div>
        </div>
    </div>
</div>

<!-- 送货信息 -->
<div class="card">
    <div class="card-header">📦 送货信息</div>
    <div class="card-body">
        <div class="form-grid-4" style="margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label req">送货日期</label>
                <input type="date" name="delivery_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">送货时间段</label>
                <select name="delivery_time" class="form-select">
                    <option value="">不限</option>
                    <option value="09:00-12:00">上午 09:00-12:00</option>
                    <option value="14:00-17:00">下午 14:00-17:00</option>
                    <option value="18:00-21:00">晚上 18:00-21:00</option>
                </select>
            </div>
            <div class="form-group" id="fieldPerson">
                <label class="form-label">送货人</label>
                <input type="text" name="delivery_person" class="form-input" placeholder="送货人姓名">
            </div>
            <div class="form-group" id="fieldVehicle">
                <label class="form-label">车牌号</label>
                <input type="text" name="vehicle_no" class="form-input" placeholder="粤A·12345">
            </div>
        </div>

        <!-- 配送方式 -->
        <div class="type-tabs">
            <label class="type-tab active" id="tabSelf">
                <input type="radio" name="delivery_type" value="self" checked onchange="switchType('self')">
                🚚 自送
            </label>
            <label class="type-tab" id="tabExpress">
                <input type="radio" name="delivery_type" value="express" onchange="switchType('express')">
                📦 快递
            </label>
            <label class="type-tab" id="tabVirtual">
                <input type="radio" name="delivery_type" value="virtual" onchange="switchType('virtual')">
                🔗 虚拟发货
            </label>
        </div>

        <!-- 快递字段 -->
        <div id="fieldsExpress" class="hidden form-grid" style="margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label">快递公司</label>
                <select name="express_company" class="form-select">
                    <option value="">请选择</option>
                    <option>顺丰速运</option><option>京东快递</option><option>中通快递</option>
                    <option>圆通速递</option><option>韵达快递</option><option>申通快递</option>
                    <option>极兔速递</option><option>德邦快递</option><option>其他</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">快递单号</label>
                <input type="text" name="express_no" class="form-input" placeholder="快递单号">
            </div>
        </div>

        <!-- 虚拟发货字段 -->
        <div id="fieldsVirtual" class="hidden" style="margin-bottom:16px;">
            <div class="form-grid" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label">发送方式</label>
                    <select name="virtual_method" class="form-select">
                        <option value="">请选择</option>
                        <option>邮件</option><option>微信</option><option>QQ</option><option>短信</option><option>其他</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">发货内容</label>
                <textarea name="virtual_content" class="form-textarea" placeholder="链接、激活码或其他发货内容..."></textarea>
            </div>
        </div>

        <!-- 费用 -->
        <div style="border-top:1px solid #e2e8f0;padding-top:16px;margin-top:8px;">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">运费</label>
                    <input type="number" name="freight_fee" id="freightFee" class="form-input" value="0" step="any" min="0" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">支付方式</label>
                    <select name="payment_method" class="form-select">
                        <option value="">待确定</option>
                        <option>现金</option><option>银行转账</option><option>支付宝</option><option>微信</option><option>刷卡</option>
                    </select>
                </div>
            </div>
            <div class="fee-box">
                <div class="fee-row"><span>货物小计</span><span id="feeGoods">¥0.00</span></div>
                <div class="fee-row"><span>运费</span><span id="feeFreight">¥0.00</span></div>
                <div class="fee-row unit-qty-row hidden" id="feeUnitQtyRow">
                    <span>套数</span><span id="feeUnitQty">1 套</span>
                </div>
                <div class="fee-row total"><span>总金额</span><span id="feeTotal">¥0.00</span></div>
            </div>
        </div>
    </div>
</div>

<!-- 送货明细 -->
<div class="card">
    <div class="card-header">📋 送货明细</div>
    <div class="card-body">
        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#1e40af;line-height:1.8;">
            💡 <strong>产品选择说明：</strong>
            依次选择 <strong>①大类 → ②子类 → ③输入关键字过滤（可选）→ ④选具体型号</strong>，选中后自动填入规格和单价。<br>
            🔧 <strong>产品不在库中？</strong>在第④步型号列表最底部选 <strong>"✏️ 自定义输入"</strong>，填写名称后保存，系统自动入库，下次在同一子类下即可直接选用。
        </div>
        <div class="items-wrap">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:36px">序号</th>
                        <th style="width:175px">产品选择（大类→子类→型号）</th>
                        <th>规格型号</th>
                        <th style="width:58px">数量</th>
                        <th style="width:48px">单位</th>
                        <th style="width:88px">单价(¥)</th>
                        <th style="width:88px">小计(¥)</th>
                        <th style="width:50px;white-space:nowrap">操作</th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>
        </div>
        <button type="button" class="btn-add-row" onclick="addRow()">＋ 添加产品行</button>
    </div>
</div>

<!-- 备注 -->
<div class="card">
    <div class="card-header">📝 备注</div>
    <div class="card-body">
        <textarea name="notes" class="form-textarea" placeholder="其他需要说明的信息" rows="3" style="width:100%;min-height:80px;"></textarea>
        <div style="margin-top:8px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:13px;color:#92400e;">
            📷 可在此扫描录入序列号进行跟踪记录，格式建议：产品名称 SN:序列号
        </div>
    </div>
</div>

</form>
</div><!-- end content-inner -->
</main>

<script>
/* ═══════ 分类数据（PHP注入） ═══════ */
const PARENT_CATS = <?php echo json_encode(array_values($parent_cats_arr), JSON_UNESCAPED_UNICODE); ?>;
const CATS_BY_PARENT = <?php echo json_encode($cats_by_parent, JSON_UNESCAPED_UNICODE); ?>;

let rowIdx = 0;
let quoteUnitQty = 1;
let quoteFinalAmount = 0;
const _prodCache = {};  // catId → products[]

/* 构建大类 option HTML（静态，复用于每行） */
const _parentOpts = PARENT_CATS.map(c =>
    `<option value="${c.id}">${esc(c.name)}</option>`
).join('');

/* ═══════ 行模板 ═══════ */
function buildRowHtml(idx) {
    return `
        <td style="text-align:center;color:#a0aec0;font-size:13px;">${idx + 1}</td>
        <td>
            <input type="hidden" name="items[${idx}][category]"    id="cat_lbl_${idx}" value="">
            <input type="hidden" name="items[${idx}][category_id]" id="cat_id_${idx}"  value="">
            <input type="hidden" name="items[${idx}][product_id]"  id="pid_${idx}"     value="0">
            <input type="hidden" name="items[${idx}][product_name]" id="pname_${idx}"  value="">
            <!-- 产品库选择 -->
            <div id="lib_div_${idx}">
                <select class="s-mb" onchange="onParentChange(${idx},this.value)">
                    <option value="">① 选产品大类</option>
                    ${_parentOpts}
                </select>
                <select id="sub_cat_${idx}" class="s-mb" onchange="onSubChange(${idx},this)" disabled>
                    <option value="">② 选子类</option>
                </select>
                <input id="kw_${idx}" placeholder="③ 输入关键字过滤..." oninput="onFilter(${idx},this.value)"
                       class="s-mb" style="display:block;" disabled>
                <select id="psel_${idx}" onchange="onProdSel(${idx},this)" disabled>
                    <option value="">④ 选具体型号</option>
                </select>
            </div>
            <!-- 自定义输入 -->
            <div id="cust_div_${idx}" style="display:none;">
                <span class="cust-badge">✏️ 自定义产品（保存后自动入库）</span>
                <input type="text" name="items[${idx}][custom_name]" id="cust_name_${idx}"
                       placeholder="产品名称 *"
                       style="border-color:#f59e0b;margin-bottom:3px;"
                       oninput="syncPname(${idx},this.value)">
                <button type="button" onclick="exitCustom(${idx})"
                        style="font-size:11px;color:#1f6dad;background:none;border:none;cursor:pointer;padding:0;">
                    ← 返回产品库选择
                </button>
            </div>
        </td>
        <td><input type="text" name="items[${idx}][product_spec]" id="spec_${idx}" placeholder="规格/型号"></td>
        <td><input type="number" name="items[${idx}][quantity]" value="1" min="1" onchange="calcRow(${idx})"></td>
        <td><input type="text"   name="items[${idx}][unit]"     id="unit_${idx}" value="件"></td>
        <td><input type="number" name="items[${idx}][unit_price]" id="price_${idx}" value="" step="any" min="0" onchange="calcRow(${idx})"></td>
        <td><input type="text" name="items[${idx}][subtotal]" id="subtotal_${idx}" value="0.00" readonly></td>
        <td style="text-align:center;">
            <button type="button" class="btn-danger" onclick="removeRow(${idx})">删除</button>
        </td>`;
}

/* ═══════ 分类级联 ═══════ */
function onParentChange(idx, parentId) {
    const subSel   = document.getElementById('sub_cat_' + idx);
    const kwInp    = document.getElementById('kw_' + idx);
    const prodSel  = document.getElementById('psel_' + idx);
    subSel.innerHTML = '<option value="">② 选子类</option>';
    subSel.disabled  = true;
    kwInp.value = ''; kwInp.disabled = true;
    prodSel.innerHTML = '<option value="">④ 选具体型号</option>'; prodSel.disabled = true;
    resetProdHidden(idx);

    if (!parentId) return;

    const children = CATS_BY_PARENT[parentId] || [];
    if (!children.length) {
        // 无子类，直接加载产品
        const pc = PARENT_CATS.find(c => c.id == parentId);
        document.getElementById('cat_id_' + idx).value  = parentId;
        document.getElementById('cat_lbl_' + idx).value = pc ? pc.name : '';
        loadProds(idx, parentId);
    } else {
        children.forEach(c => subSel.add(new Option(c.name, c.id)));
        subSel.disabled = false;
    }
}

function onSubChange(idx, selEl) {
    const catId   = selEl.value;
    const catName = selEl.options[selEl.selectedIndex]?.text || '';
    document.getElementById('cat_id_' + idx).value  = catId;
    document.getElementById('cat_lbl_' + idx).value = catName;
    const kwInp   = document.getElementById('kw_' + idx);
    const prodSel = document.getElementById('psel_' + idx);
    kwInp.value = ''; kwInp.disabled = true;
    prodSel.innerHTML = '<option value="">加载中...</option>'; prodSel.disabled = true;
    resetProdHidden(idx);
    if (!catId) return;
    loadProds(idx, catId);
}

async function loadProds(idx, catId) {
    if (!_prodCache[catId]) {
        const r = await fetch('api_products_by_category.php?cat_id=' + catId);
        _prodCache[catId] = await r.json();
    }
    renderProdSel(idx, _prodCache[catId], '');
}

function renderProdSel(idx, prods, kw) {
    const prodSel = document.getElementById('psel_' + idx);
    const kwInp   = document.getElementById('kw_' + idx);
    const filtered = kw
        ? prods.filter(p => (p.name || '').toLowerCase().includes(kw.toLowerCase())
                         || (p.spec || '').toLowerCase().includes(kw.toLowerCase()))
        : prods;

    let html = '<option value="">④ 选具体型号</option>';
    html += '<option value="__custom__">✏️ 自定义输入（不在产品库）</option>';
    filtered.forEach(p => {
        const label = p.name + (p.spec ? ' · ' + p.spec.substring(0, 22) : '')
                    + '  ¥' + parseFloat(p.default_price || 0).toFixed(0);
        html += `<option value="${p.id}"
            data-name="${esc(p.name)}" data-spec="${esc(p.spec||'')}"
            data-unit="${esc(p.unit||'件')}" data-price="${p.default_price||0}">${label}</option>`;
    });
    prodSel.innerHTML = html;
    prodSel.disabled  = false;
    kwInp.disabled    = false;
}

function onFilter(idx, kw) {
    const catId = document.getElementById('cat_id_' + idx).value;
    if (catId && _prodCache[catId]) renderProdSel(idx, _prodCache[catId], kw);
}

function onProdSel(idx, selEl) {
    const val      = selEl.value;
    const kwInp    = document.getElementById('kw_' + idx);
    const libDiv   = document.getElementById('lib_div_' + idx);
    const custDiv  = document.getElementById('cust_div_' + idx);

    if (val === '__custom__') {
        libDiv.style.display  = 'none';
        custDiv.style.display = 'block';
        resetProdHidden(idx);
        setTimeout(() => document.getElementById('cust_name_' + idx).focus(), 50);
        return;
    }
    if (!val) return;

    const opt = selEl.options[selEl.selectedIndex];
    document.getElementById('pid_' + idx).value   = val;
    document.getElementById('pname_' + idx).value = opt.dataset.name || '';
    document.getElementById('spec_' + idx).value  = opt.dataset.spec || '';
    document.getElementById('unit_' + idx).value  = opt.dataset.unit || '件';
    document.getElementById('price_' + idx).value = parseFloat(opt.dataset.price || 0).toFixed(2);
    calcRow(idx);
}

function exitCustom(idx) {
    document.getElementById('cust_div_' + idx).style.display = 'none';
    document.getElementById('lib_div_' + idx).style.display  = '';
    document.getElementById('cust_name_' + idx).value = '';
    resetProdHidden(idx);
    document.getElementById('psel_' + idx).value = '';
}

function syncPname(idx, val) {
    document.getElementById('pname_' + idx).value = val;
}

function resetProdHidden(idx) {
    document.getElementById('pid_' + idx).value   = '0';
    document.getElementById('pname_' + idx).value = '';
}

/* ═══════ 行增删 ═══════ */
function addRow() {
    const tbody = document.getElementById('itemsBody');
    const tr    = document.createElement('tr');
    tr.id       = 'itemRow' + rowIdx;
    tr.innerHTML = buildRowHtml(rowIdx);
    tbody.appendChild(tr);
    rowIdx++;
    reNumber();
}

/* 从报价单数据生成行（直接进自定义模式预填） */
function addRowWithData(item) {
    const tbody = document.getElementById('itemsBody');
    const tr    = document.createElement('tr');
    const idx   = rowIdx++;
    tr.id       = 'itemRow' + idx;
    tr.innerHTML = buildRowHtml(idx);
    tbody.appendChild(tr);

    const qty   = parseInt(item.quantity)              || 1;
    const price = parseFloat(item.price || item.unit_price) || 0;
    const name  = item.product_name || '';

    tr.querySelector(`input[name="items[${idx}][quantity]"]`).value = qty;
    document.getElementById('price_' + idx).value = price.toFixed(2);
    document.getElementById('spec_' + idx).value  = item.spec || item.model || '';
    document.getElementById('unit_' + idx).value  = item.unit || '件';
    document.getElementById('pid_' + idx).value   = item.product_id || '0';

    // 进入自定义显示模式（预填产品名）
    document.getElementById('pname_' + idx).value      = name;
    document.getElementById('cust_name_' + idx).value  = name;
    document.getElementById('lib_div_' + idx).style.display  = 'none';
    document.getElementById('cust_div_' + idx).style.display = 'block';
    if (item.category) document.getElementById('cat_lbl_' + idx).value = item.category;

    calcRow(idx);
    reNumber();
}

function removeRow(idx) {
    const rows = document.querySelectorAll('#itemsBody tr');
    if (rows.length <= 1) { sapToast('至少保留一行', 'info'); return; }
    document.getElementById('itemRow' + idx)?.remove();
    reNumber();
    calcTotal();
}

function reNumber() {
    document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
        const td = tr.querySelector('td:first-child');
        if (td) td.textContent = i + 1;
    });
}

function calcRow(idx) {
    const tr = document.getElementById('itemRow' + idx);
    if (!tr) return;
    const qty   = parseFloat(tr.querySelector(`input[name="items[${idx}][quantity]"]`)?.value)   || 0;
    const price = parseFloat(document.getElementById('price_' + idx)?.value) || 0;
    const sub   = (qty * price).toFixed(2);
    const subInp = document.getElementById('subtotal_' + idx);
    if (subInp) subInp.value = sub;
    calcTotal();
}

function calcTotal() {
    let goods = 0;
    document.querySelectorAll('#itemsBody input[name*="[subtotal]"]').forEach(inp => {
        goods += parseFloat(inp.value) || 0;
    });
    const freight = parseFloat(document.getElementById('freightFee')?.value) || 0;
    document.getElementById('feeGoods').textContent   = '¥' + goods.toFixed(2);
    document.getElementById('feeFreight').textContent = '¥' + freight.toFixed(2);
    updateUnitQtyDisplay(goods, freight);
}

function updateUnitQtyDisplay(goods, freight) {
    if (goods === undefined) {
        goods = 0;
        document.querySelectorAll('#itemsBody input[name*="[subtotal]"]').forEach(i => goods += parseFloat(i.value)||0);
        freight = parseFloat(document.getElementById('freightFee')?.value)||0;
    }
    const qtyRow = document.getElementById('feeUnitQtyRow');
    const feeTotal = document.getElementById('feeTotal');
    if (quoteUnitQty > 1) {
        qtyRow.classList.remove('hidden');
        document.getElementById('feeUnitQty').textContent = quoteUnitQty + ' 套';
        feeTotal.textContent = '¥' + quoteFinalAmount.toFixed(2);
    } else {
        qtyRow.classList.add('hidden');
        feeTotal.textContent = '¥' + (goods + freight).toFixed(2);
    }
}

function esc(str) {
    if (!str) return '';
    const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
}

/* ═══════ 来源切换 ═══════ */
function selectSource(src, el) {
    document.querySelectorAll('.source-option').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('quoteSelectField').classList.toggle('hidden', src !== 'quote');
    if (src !== 'quote') { document.getElementById('quoteId').value=''; quoteUnitQty=1; quoteFinalAmount=0; updateUnitQtyDisplay(); }
}

/* ═══════ 客户模式 ═══════ */
function switchCustomerMode(mode, el) {
    document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('existingCustomerField').classList.toggle('hidden', mode !== 'existing');
}
function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customerName').value    = opt.dataset.name    || '';
    document.getElementById('contactName').value     = opt.dataset.contact || '';
    document.getElementById('contactPhone').value    = opt.dataset.phone   || '';
    document.getElementById('deliveryAddress').value = opt.dataset.address || '';
}

/* ═══════ 配送方式 ═══════ */
function switchType(type) {
    ['self','express','virtual'].forEach(t => {
        const tab = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (tab) tab.classList.toggle('active', t === type);
    });
    document.getElementById('fieldPerson').style.display  = type === 'self' ? '' : 'none';
    document.getElementById('fieldVehicle').style.display = type === 'self' ? '' : 'none';
    document.getElementById('fieldsExpress').classList.toggle('hidden', type !== 'express');
    document.getElementById('fieldsVirtual').classList.toggle('hidden', type !== 'virtual');
}
switchType('self');

/* ═══════ 从报价单加载 ═══════ */
function loadQuoteData(sel) {
    const quoteId = sel.value;
    if (!quoteId) return;
    document.getElementById('quoteId').value = quoteId;

    fetch('api_get_approved_quote.php?quote_id=' + quoteId)
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            if (!data.success) { sapToast('加载失败：' + data.message, 'error'); return; }
            const q = data.quote;
            document.getElementById('customerName').value    = q.company_name || '';
            document.getElementById('contactName').value     = q.contact_name || '';
            document.getElementById('contactPhone').value    = q.phone        || '';
            document.getElementById('deliveryAddress').value = q.address      || '';
            quoteUnitQty     = parseInt(q.unit_qty)     || 1;
            quoteFinalAmount = parseFloat(q.final_amount) || 0;

            document.getElementById('itemsBody').innerHTML = '';
            rowIdx = 0;
            const mode = document.getElementById('deliveryMode')?.value || 'detail';
            if (mode === 'summary') {
                const totalQty = parseInt(q.unit_qty) || 1;
                addRowWithData({ product_name:'电脑配件', spec:q.quote_no||'', quantity:totalQty, unit:'套', price:parseFloat(q.final_amount)/totalQty });
            } else {
                if (data.items && data.items.length > 0) data.items.forEach(item => addRowWithData(item));
                else addRow();
            }
            calcTotal();
        })
        .catch(err => sapToast('加载报价单失败：' + err.message, 'error'));
}

/* ═══════ 保存 ═══════ */
function saveDelivery() {
    // 手动校验
    const custName = document.getElementById('customerName').value.trim();
    const dlvDate  = document.querySelector('input[name="delivery_date"]')?.value || '';
    if (!custName) { sapToast('请填写客户名称！', 'warning'); return; }
    if (!dlvDate)  { sapToast('请填写送货日期！', 'warning'); return; }

    // 校验明细
    let hasValid = false, hasBlank = false;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const idx   = tr.id.replace('itemRow', '');
        const pname = (document.getElementById('pname_' + idx)?.value || '').trim();
        const cname = (document.getElementById('cust_name_' + idx)?.value || '').trim();
        const inCust = document.getElementById('cust_div_' + idx)?.style.display !== 'none';
        if (inCust ? cname : pname) hasValid = true;
        else hasBlank = true;
    });
    if (!hasValid) { sapToast('请至少添加一个产品！', 'warning'); return; }
    if (hasBlank)  { sapToast('有产品行未填完整，请检查！', 'warning'); return; }

    const btn = document.querySelector('.btn-primary[onclick="saveDelivery()"]');
    btn.disabled = true; btn.textContent = '保存中...';

    const fd = new FormData(document.getElementById('deliveryForm'));
    let goods = 0;
    document.querySelectorAll('#itemsBody input[name*="[subtotal]"]').forEach(inp => { goods += parseFloat(inp.value)||0; });
    fd.set('goods_amount', goods.toFixed(2));

    fetch('delivery_save.php', { method:'POST', body:fd })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            if (data.success) { sapToast(data.message||'保存成功','success'); setTimeout(()=>location.href='deliveries.php',800); }
            else { sapToast('保存失败: ' + data.message, 'error'); btn.disabled=false; btn.textContent='💾 保存送货单'; }
        })
        .catch(err => { sapToast('保存出错: ' + err.message, 'error'); btn.disabled=false; btn.textContent='💾 保存送货单'; });
}

/* ═══════ 初始化 ═══════ */
addRow();  // 默认一行
</script>

<!-- SAP Toast + Confirm -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:260px;max-width:400px;padding:13px 16px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:16px;flex-shrink:0;"></span>
  <span id="sapToastMsg"  style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<div id="sapConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9000;"></div>
<div id="sapConfirmBox" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:400px;max-width:92vw;background:white;border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,0.18);z-index:9001;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:15px 20px;display:flex;align-items:center;gap:10px;">
    <span id="sapConfirmIcon"  style="font-size:18px;">⚠️</span>
    <span id="sapConfirmTitle" style="font-size:15px;font-weight:600;color:white;">确认操作</span>
  </div>
  <div style="padding:22px 20px;">
    <p id="sapConfirmMsg" style="font-size:14px;color:#2d3748;line-height:1.7;margin:0;"></p>
  </div>
  <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:10px;">
    <button id="sapConfirmCancel"
      style="padding:8px 20px;border:1px solid #e2e8f0;border-radius:7px;background:#f7fafc;
             color:#4a5568;font-size:14px;cursor:pointer;"
      onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='#f7fafc'">取消</button>
    <button id="sapConfirmOk"
      style="padding:8px 20px;border:none;border-radius:7px;
             background:linear-gradient(135deg,#1f6dad,#135286);
             color:white;font-size:14px;cursor:pointer;"
      onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">确认</button>
  </div>
</div>
<script>
function sapToast(msg,type,dur){
    type=type||'info';dur=dur||3000;
    var C={success:'#16a34a',error:'#dc2626',warning:'#d97706',info:'#1f6dad'};
    var I={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
    var el=document.getElementById('sapToast');
    if(!el)return;
    document.getElementById('sapToastIcon').textContent=I[type]||'ℹ️';
    document.getElementById('sapToastMsg').textContent=msg;
    el.style.background=C[type]||'#1f6dad';
    el.style.display='flex';el.style.opacity='1';
    clearTimeout(el._t);
    el._t=setTimeout(function(){el.style.opacity='0';
        setTimeout(function(){el.style.display='none';},300);},dur);
}
function sapConfirm(msg,onOk,opts){
    opts=opts||{};
    document.getElementById('sapConfirmMsg').innerHTML=msg.replace(/\n/g,'<br>');
    document.getElementById('sapConfirmTitle').textContent=opts.title||'确认操作';
    document.getElementById('sapConfirmIcon').textContent=opts.icon||'⚠️';
    document.getElementById('sapConfirmOk').textContent=opts.okText||'确认';
    document.getElementById('sapConfirmOk').style.background=
        opts.danger?'linear-gradient(135deg,#ef4444,#dc2626)':'linear-gradient(135deg,#1f6dad,#135286)';
    document.getElementById('sapConfirmOverlay').style.display='block';
    document.getElementById('sapConfirmBox').style.display='block';
    var ok=document.getElementById('sapConfirmOk');
    var cancel=document.getElementById('sapConfirmCancel');
    var close=function(){
        document.getElementById('sapConfirmOverlay').style.display='none';
        document.getElementById('sapConfirmBox').style.display='none';
        ok.onclick=null;cancel.onclick=null;};
    ok.onclick=function(){close();onOk();};
    cancel.onclick=function(){close();};
}
</script>

</body>
</html>