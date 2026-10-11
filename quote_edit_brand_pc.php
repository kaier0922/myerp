<?php
/**
 * 文件名: quote_edit_brand_pc.php
 * 功能: 编辑报价单 - 编辑品牌整机报价单
 * 版本: 2.0 - 新增行改为三层选产品（大类→关键字→产品）
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();

require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];
$conn = getDBConnection();
$quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($quote_id <= 0) die('无效的报价单ID');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $customer_id         = intval($input['customer_id'] ?? 0);
        $contact_person      = $input['contact_person'] ?? '';
        $contact_phone       = $input['contact_phone'] ?? '';
        $customer_address    = $input['customer_address'] ?? '';
        $template_type       = $input['template_type'] ?? 'assembled_pc';
        $quote_date          = $input['quote_date'] ?? '';
        $valid_days          = intval($input['valid_days'] ?? 3);
        $project_name        = $input['project_name'] ?? '';
        $project_location    = $input['project_location'] ?? '';
        $construction_period = $input['construction_period'] ?? '';
        $terms               = $input['terms'] ?? '';
        $remark              = $input['remark'] ?? '';
        $final_amount        = floatval($input['final_amount'] ?? 0);
        $discount            = floatval($input['discount'] ?? 0);
        $unit_qty            = intval($input['unit_qty'] ?? 1);
        error_log('[DEBUG unit_qty] received=' . ($input['unit_qty']??'NULL') . ' parsed=' . $unit_qty);
        $items               = $input['items'] ?? [];
        $new_customer_name    = trim($input['new_customer_name'] ?? '');
        $new_customer_contact = trim($input['new_customer_contact'] ?? '');
        $new_customer_phone   = trim($input['new_customer_phone'] ?? '');

        if ($customer_id === -1 && !empty($new_customer_name)) {
            $stmt = $conn->prepare("INSERT INTO customers (company_name,contact_name,phone,created_at) VALUES (?,?,?,NOW())");
            $stmt->bind_param("sss", $new_customer_name, $new_customer_contact, $new_customer_phone);
            if ($stmt->execute()) $customer_id = $conn->insert_id;
            else throw new Exception('客户创建失败');
            $stmt->close();
        }

        $stmt = $conn->prepare("UPDATE quotes SET customer_id=?,contact_person=?,contact_phone=?,customer_address=?,project_name=?,project_location=?,construction_period=?,template_type=?,quote_date=?,valid_days=?,final_amount=?,discount=?,unit_qty=?,terms=?,remark=?,updated_at=NOW() WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
        $stmt->bind_param("issssssssiddissi", $customer_id,$contact_person,$contact_phone,$customer_address,$project_name,$project_location,$construction_period,$template_type,$quote_date,$valid_days,$final_amount,$discount,$unit_qty,$terms,$remark,$quote_id);
        if (!$stmt->execute()) throw new Exception('更新失败: ' . $stmt->error);
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM quote_items WHERE quote_id=?");
        $stmt->bind_param("i", $quote_id); $stmt->execute(); $stmt->close();

        foreach ($items as $item) {
            $seq          = intval($item['seq'] ?? 0);
            $product_id   = isset($item['product_id']) && $item['product_id'] !== '' ? intval($item['product_id']) : null;
            $category     = $item['category'] ?? '';
            $product_name = $item['product_name'] ?? '';
            $spec         = $item['spec'] ?? '';
            $unit         = $item['unit'] ?? '个';
            $quantity     = intval($item['quantity'] ?? 1);
            $price        = floatval($item['price'] ?? 0);
            $cost         = floatval($item['cost'] ?? 0);
            $subtotal     = $quantity * $price;
            $cost_subtotal = $quantity * $cost;
            $remark_item  = $item['remark'] ?? '';

            if ($product_id !== null) {
                $stmt = $conn->prepare("INSERT INTO quote_items (quote_id,seq,category,product_id,product_name,spec,unit,quantity,price,cost,subtotal,cost_subtotal,remark) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("iisisssidddds", $quote_id,$seq,$category,$product_id,$product_name,$spec,$unit,$quantity,$price,$cost,$subtotal,$cost_subtotal,$remark_item);
            } else {
                $stmt = $conn->prepare("INSERT INTO quote_items (quote_id,seq,category,product_name,spec,unit,quantity,price,cost,subtotal,cost_subtotal,remark) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("iissssidddds", $quote_id,$seq,$category,$product_name,$spec,$unit,$quantity,$price,$cost,$subtotal,$cost_subtotal,$remark_item);
            }
            $stmt->execute(); $stmt->close();

            // 回写销售价格和成本价到产品库
            if ($product_id !== null && $price > 0) {
                $writeback_cost = ceil($price * 0.92 / 5) * 5;
                $upd = $conn->prepare("UPDATE products SET default_price=?, cost_price=? WHERE id=? AND user_id=?");
                if ($upd) { $upd->bind_param('ddii', $price, $writeback_cost, $product_id, $_SESSION['user_id']); $upd->execute(); $upd->close(); }
            }
        }
        $conn->close();
        echo json_encode(['success'=>true,'message'=>'保存成功','quote_id'=>$quote_id]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

$stmt = $conn->prepare("SELECT q.*,COALESCE(c.short_name, c.company_name) AS company_name,c.contact_name,c.phone FROM quotes q LEFT JOIN customers c ON q.customer_id=c.id WHERE q.id=?");
$stmt->bind_param("i",$quote_id); $stmt->execute();
$quote = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$quote) die('报价单不存在');

if (!empty($quote['quote_date'])) {
    $d = $quote['quote_date'];
    if (preg_match('/^\d{8}$/',$d)) $quote['quote_date']=substr($d,0,4).'-'.substr($d,4,2).'-'.substr($d,6,2);
    elseif (strtotime($d)) $quote['quote_date']=date('Y-m-d',strtotime($d));
}
if (empty($quote['valid_days'])||$quote['valid_days']<=0) $quote['valid_days']=3;

$stmt = $conn->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY seq ASC");
$stmt->bind_param("i",$quote_id); $stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$quote_json = json_encode($quote, JSON_UNESCAPED_UNICODE);
$items_json = json_encode($items, JSON_UNESCAPED_UNICODE);

$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name, phone FROM customers WHERE " . tenantWhere() . " ORDER BY created_at DESC");

$categories_res = $conn->query("SELECT pc.id, pc.name FROM product_categories pc WHERE EXISTS (SELECT 1 FROM products p WHERE p.category_id=pc.id AND p.is_active=1 AND (p.user_id=0 OR p.user_id=" . intval($_SESSION['user_id']) . ")) ORDER BY CASE WHEN pc.id BETWEEN 21 AND 25 THEN 1 WHEN pc.id BETWEEN 11 AND 19 OR pc.id=76 THEN 2 WHEN pc.id BETWEEN 41 AND 46 THEN 3 WHEN pc.id BETWEEN 31 AND 35 THEN 4 WHEN pc.id BETWEEN 51 AND 54 OR pc.id=100 THEN 5 WHEN pc.id BETWEEN 61 AND 65 OR pc.id IN(96,99) THEN 6 WHEN pc.id BETWEEN 71 AND 82 THEN 7 ELSE 9 END, pc.sort_order, pc.id");
$categories_list = [];
while ($cat = $categories_res->fetch_assoc()) $categories_list[] = $cat;
$categories_json = json_encode($categories_list, JSON_UNESCAPED_UNICODE);

// 产品大类→次级类级联数据（供新增行使用，与 quote_create_brand_pc.php 加载逻辑一致；已有行仍用上面的扁平列表）
$_cats_all = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE is_active=1 ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
$_npc_parents = array_values(array_filter($_cats_all, fn($c) => $c['parent_id'] == 0));
// 本页要求的大类展示顺序：整机设备优先，其余依次；仅本页排序，不改全局 sort_order
$_npc_order_ids = [2, 1, 3, 4, 6, 7, 5, 83]; // 整机设备,硬件配件,网络设备,外设周边,软件授权,技术服务,监控安防,强弱电工程
usort($_npc_parents, function($a, $b) use ($_npc_order_ids) {
    $ai = array_search((int)$a['id'], $_npc_order_ids); $ai = $ai === false ? 999 : $ai;
    $bi = array_search((int)$b['id'], $_npc_order_ids); $bi = $bi === false ? 999 : $bi;
    return $ai <=> $bi;
});
$_npc_by_parent = [];
foreach ($_cats_all as $_c) { if ($_c['parent_id'] > 0) $_npc_by_parent[$_c['parent_id']][] = $_c; }
$npcParentsJson  = json_encode($_npc_parents, JSON_UNESCAPED_UNICODE);
$npcByParentJson = json_encode($_npc_by_parent, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
<?php $page_title = '编辑品牌整机报价单 - ' . htmlspecialchars($quote['quote_no']); include __DIR__ . '/head.php'; ?>
<style>
.container{max-width:1400px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;}
.header{background:linear-gradient(135deg,#1f6dad 0%,#135286 100%);color:white;padding:30px 40px;display:flex;justify-content:space-between;align-items:center;}
.header h1{font-size:26px;font-weight:600;}
.header-actions{display:flex;gap:12px;}
.form-section-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #1f6dad;}
/* 利润核算开关：默认隐藏成本列 */
.cost-col{display:none;}
body.cost-mode .cost-col{display:table-cell;}
#totalCostRow,#totalProfitRow{display:none;}
body.cost-mode #totalCostRow,body.cost-mode #totalProfitRow{display:flex;}
.btn-cost-toggle{padding:8px 18px;border:2px solid #94a3b8;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:#f1f5f9;color:#64748b;transition:all .2s;white-space:nowrap;}
.btn-cost-toggle.on{background:#16a34a;border-color:#16a34a;color:#fff;}
.btn-cost-toggle:hover{opacity:.85;}
.form-group label{font-size:13px;font-weight:500;color:#475569;margin-bottom:6px;}
.form-control{padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
textarea.form-control{min-height:80px;resize:vertical;}
.new-customer-area{display:none;background:#fff;padding:20px;border-radius:8px;border:2px dashed #1f6dad;margin-top:12px;}
.new-customer-area.active{display:block;}
.items-table-container{overflow-x:auto;margin-bottom:20px;border:1px solid #e2e8f0;border-radius:8px;}
.items-table{width:100%;border-collapse:collapse;background:#fff;}
.items-table thead{background:linear-gradient(135deg,#1f6dad 0%,#135286 100%);color:white;}
.items-table th{padding:11px 8px;text-align:center;font-size:13px;font-weight:600;white-space:nowrap;}
.items-table td{padding:7px 8px;border-bottom:1px solid #e2e8f0;vertical-align:middle;}
.items-table tbody tr:hover{background:#f8fafc;}
.items-table input,.items-table select{width:100%;padding:5px 7px;border:1px solid #cbd5e1;border-radius:4px;font-size:13px;}
.items-table input:focus,.items-table select:focus{outline:none;border-color:#1f6dad;}
.drag-handle{cursor:move;font-size:16px;color:#94a3b8;user-select:none;display:block;text-align:center;}
.drag-handle:hover{color:#1f6dad;}
.sortable-ghost{opacity:.4;background:#e0e7ff;}
.new-row-select td{background:#fffbeb;}
.btn-remove{padding:3px 10px;background:#ef4444;color:white;border:none;border-radius:4px;font-size:12px;cursor:pointer;white-space:nowrap;}
.btn-remove:hover{background:#dc2626;}
.total-section{background:#fff;padding:20px;border-radius:8px;border:2px solid #1f6dad;}
.total-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:14px;}
.total-row.final{font-size:18px;font-weight:700;color:#1f6dad;border-top:2px solid #e2e8f0;padding-top:12px;margin-top:8px;}
.loading{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:9999;justify-content:center;align-items:center;}
.loading.active{display:flex;}
.loading-spinner{background:white;padding:40px;border-radius:12px;text-align:center;}
.spinner{width:48px;height:48px;margin:0 auto 16px;border:4px solid #e2e8f0;border-top-color:#1f6dad;border-radius:50%;animation:spin 1s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
/* 三层选产品 */
.sel-cat{background:#eff6ff!important;border-color:#93c5fd!important;font-weight:600!important;color:#135286!important;margin-bottom:3px!important;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="page-header">
  <h1 class="page-title">✏️ 编辑品牌整机报价单 - <?php echo htmlspecialchars($quote['quote_no']); ?></h1>
  <div style="display:flex;gap:10px;">
    <a href="quotes.php" class="btn btn-secondary">← 返回</a>
    <a href="quote_view.php?id=<?php echo $quote_id; ?>" class="btn btn-secondary">👁️ 预览</a>
    <button onclick="saveQuote()" class="btn btn-primary">💾 保存</button>
  </div>
</div>
<div style="max-width:1200px;margin:0 auto;">
<div class="alert-warning">⚠️ 修改将覆盖原有数据，请谨慎操作！</div>
<div class="form-card">
  <h2 class="form-section-title">基本信息</h2>
  <div class="form-row">
    <div class="form-group">
      <label>客户 <span style="color:red">*</span></label>
      <select id="customer_id" class="form-control" onchange="handleCustomerChange()">
        <option value="">-- 请选择客户 --</option>
        <?php while ($c=$customers->fetch_assoc()): ?>
        <option value="<?php echo $c['id']; ?>" <?php echo $c['id']==$quote['customer_id']?'selected':''; ?>>
          <?php echo htmlspecialchars($c['company_name'].' - '.$c['contact_name']); ?>
        </option>
        <?php endwhile; ?>
        <option value="-1">➕ 新增客户</option>
      </select>
    </div>
    <div class="form-group"><label>联系人</label><input type="text" id="contact_person" class="form-control" value="<?php echo htmlspecialchars($quote['contact_person']??''); ?>"></div>
    <div class="form-group"><label>联系电话</label><input type="text" id="contact_phone" class="form-control" value="<?php echo htmlspecialchars($quote['contact_phone']??''); ?>"></div>
    <div class="form-group"><label>客户地址</label><input type="text" id="customer_address" class="form-control" value="<?php echo htmlspecialchars($quote['customer_address']??''); ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>报价日期</label><input type="text" id="quote_date" class="form-control" value="<?php echo htmlspecialchars($quote['quote_date']); ?>"></div>
    <div class="form-group"><label>有效期（天）</label><input type="number" id="valid_days" class="form-control" value="<?php echo $quote['valid_days']; ?>" min="1"></div>
    <div class="form-group"><label>项目名称</label><input type="text" id="project_name" class="form-control" value="<?php echo htmlspecialchars($quote['project_name']??''); ?>"></div>
    <div class="form-group"><label>项目地址</label><input type="text" id="project_location" class="form-control" value="<?php echo htmlspecialchars($quote['project_location']??''); ?>"></div>
  </div>
  <input type="hidden" id="template_type" value="brand_pc">
  <div id="newCustomerArea" class="new-customer-area">
    <div class="form-row">
      <div class="form-group"><label>公司名称 *</label><input type="text" id="new_customer_name" class="form-control"></div>
      <div class="form-group"><label>联系人</label><input type="text" id="new_customer_contact" class="form-control"></div>
      <div class="form-group"><label>联系电话</label><input type="text" id="new_customer_phone" class="form-control"></div>
    </div>
  </div>
</div>
<div class="form-card">
  <h2 class="form-section-title" style="display:flex;justify-content:space-between;align-items:center;">
    报价明细
    <button type="button" id="costToggleBtn" class="btn-cost-toggle" onclick="toggleCostMode()">💰 利润核算 OFF</button>
  </h2>
  <div class="alert-info">💡 已有行可直接编辑；新增行请：① 选蓝色<b>产品大类</b> → ② 输入<b>关键字</b>过滤（可选）→ ③ 选<b>具体产品</b>。</div>
  <button type="button" class="btn btn-secondary" onclick="addItem()" style="margin-bottom:14px;">➕ 添加行</button>
  <div class="items-table-container">
    <table class="items-table">
      <thead id="itemsTableHead"><tr></tr></thead>
      <tbody id="itemsTableBody"></tbody>
    </table>
  </div>
  <div class="total-section">
    <div class="total-row"><span>商品总额：</span><span id="totalAmount">¥0.00</span></div>
    <div class="total-row">
      <span>折扣金额：</span>
      <input type="number" id="discount" class="form-control" value="<?php echo $quote['discount']??'0'; ?>"
             min="0" step="any" style="width:140px;text-align:right;">
    </div>
    <div class="total-row final"><span>最终报价：</span><span id="finalAmount">¥0.00</span></div>
    <div id="totalCostRow" class="total-row" style="color:#888;font-size:14px;"><span>成本合计：</span><span id="totalCost">¥0.00</span></div>
    <div id="totalProfitRow" class="total-row" style="color:#16a34a;font-size:14px;font-weight:600;"><span>毛利润：</span><span id="totalProfit">¥0.00</span></div>
  </div>
</div>
<div class="form-card">
  <h2 class="form-section-title">备注</h2>
  <div class="form-group">
    <textarea id="remark" class="form-control" rows="3" placeholder="补充说明、特殊要求、交货条件等..."><?php echo htmlspecialchars($quote['remark']??''); ?></textarea>
  </div>
</div>
<div class="form-card">
  <h2 class="form-section-title">条款说明</h2>
  <div class="form-group">
    <textarea id="terms" class="form-control" rows="5"><?php echo htmlspecialchars($quote['terms']??''); ?></textarea>
  </div>
</div>
</div>
</main>
<div id="loading" class="loading">
  <div class="loading-spinner"><div class="spinner"></div><div>正在保存...</div></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>

let itemCounter = 0;
const categoriesData  = <?php echo $categories_json; ?>;
const NPC_PARENT_CATS    = <?php echo $npcParentsJson; ?>;
const NPC_CATS_BY_PARENT = <?php echo $npcByParentJson; ?>;
const productsCache   = {};
async function fetchProductsByCategory(catId) {
    if (productsCache[catId]) return productsCache[catId];
    const r = await fetch('api_products_by_category.php?cat_id=' + catId);
    const d = await r.json();
    productsCache[catId] = d;
    return d;
}
const quoteData    = <?php echo $quote_json; ?>;
const itemsData    = <?php echo $items_json; ?>;

document.addEventListener('DOMContentLoaded', function() {
    initTableHead();
    Sortable.create(document.getElementById('itemsTableBody'), {
        handle:'.drag-handle', animation:150, ghostClass:'sortable-ghost',
        onEnd: function() { updateSeq(); calculateTotal(); }
    });
    loadExistingItems();
    document.getElementById('discount').addEventListener('input', calculateTotal);
    if (localStorage.getItem('costMode') === '1') {
        document.body.classList.add('cost-mode');
        const btn = document.getElementById('costToggleBtn');
        if (btn) { btn.textContent = '💰 利润核算 ON'; btn.classList.add('on'); }
    }
});

function handleCustomerChange() {
    document.getElementById('newCustomerArea').classList.toggle('active', document.getElementById('customer_id').value==='-1');
}

function escapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
}

function removeItem(btn) {
    if (confirm('确定删除这一行？')) { btn.closest('tr').remove(); updateSeq(); calculateTotal(); }
}

function updateSeq() {
    document.querySelectorAll('#itemsTableBody tr').forEach((r,i) => {
        const s = r.querySelector('.seq-num'); if (s) s.textContent = i+1;
    });
}

function calcRowTotal(input) {
    const row   = input.closest('tr');
    const qty   = parseFloat(row.querySelector('.item-qty').value)||0;
    const price = parseFloat(row.querySelector('.item-price').value)||0;
    row.querySelector('.item-sub').textContent = '¥'+(qty*price).toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    let total = 0, totalCost = 0;
    document.querySelectorAll('#itemsTableBody tr').forEach(r => {
        const qty = parseFloat(r.querySelector('.item-qty')?.value)||0;
        total     += qty * (parseFloat(r.querySelector('.item-price')?.value)||0);
        totalCost += qty * (parseFloat(r.querySelector('.item-cost')?.value)||0);
    });
    const disc = parseFloat(document.getElementById('discount').value)||0;
    document.getElementById('totalAmount').textContent = '¥'+total.toFixed(2);
    document.getElementById('finalAmount').textContent = '¥'+(total-disc).toFixed(2);
    if (document.getElementById('totalCost'))   document.getElementById('totalCost').textContent   = '¥'+totalCost.toFixed(2);
    if (document.getElementById('totalProfit')) document.getElementById('totalProfit').textContent = '¥'+(total-disc-totalCost).toFixed(2);
}

function toggleCostMode() {
    const on  = document.body.classList.toggle('cost-mode');
    const btn = document.getElementById('costToggleBtn');
    btn.textContent = on ? '💰 利润核算 ON' : '💰 利润核算 OFF';
    btn.classList.toggle('on', on);
    localStorage.setItem('costMode', on ? '1' : '0');
    calculateTotal();
}

function collectFormData() {
    const customerId = document.getElementById('customer_id').value;
    if (!customerId) { alert('请选择客户'); return null; }
    const newName = document.getElementById('new_customer_name')?.value||'';
    if (customerId==='-1' && !newName) { alert('请输入新客户名称'); return null; }

    const items = [];
    document.querySelectorAll('#itemsTableBody tr').forEach((row, index) => {
        const productName = (row.querySelector('.item-product-name')?.value||'').trim();
        const price       = parseFloat(row.querySelector('.item-price')?.value)||0;
        if (!productName && price<=0) return;

        let category = '';
        const catEl = row.querySelector('.item-category');
        if (catEl) {
            category = catEl.tagName==='SELECT'
                ? (catEl.options[catEl.selectedIndex]?.text||'').replace(/\s*\(\d+\)$/,'').trim()
                : catEl.value.trim();
            if (category.startsWith('--') || category.startsWith('—')) category = '';
        }

        const productId = row.getAttribute('data-product-id')||null;
        items.push({
            seq: index+1,
            product_id: productId && productId!=='' ? productId : null,
            category:   category,
            product_name: (row.querySelector('.item-spec')?.value||'').trim(),         // 品牌/型号 → product_name
            spec:         (row.querySelector('.item-product-name')?.value||'').trim(), // 配置参数 → spec
            unit:     (row.querySelector('.item-unit')?.value||'台').trim(),
            quantity: parseFloat(row.querySelector('.item-qty')?.value)||1,
            price:    price,
            cost:     parseFloat(row.querySelector('.item-cost')?.value)||0,
            remark:   (row.querySelector('.item-remark')?.value||'').trim(),
        });
    });

    const disc = parseFloat(document.getElementById('discount').value)||0;
    return {
        customer_id: customerId,
        contact_person: document.getElementById('contact_person').value||'',
        contact_phone:  document.getElementById('contact_phone').value||'',
        customer_address: document.getElementById('customer_address').value||'',
        template_type:  document.getElementById('template_type').value,
        quote_date:    (document.getElementById('quote_date').value||'').replace(/-/g,''),
        valid_days:     document.getElementById('valid_days').value,
        project_name:   document.getElementById('project_name').value||'',
        project_location: document.getElementById('project_location').value||'',
        construction_period: '',
        terms:   document.getElementById('terms').value||'',
        remark:  document.getElementById('remark').value||'',
        discount: disc,
        unit_qty: 1,
        final_amount: items.reduce((s,i)=>s+i.quantity*i.price,0)-disc,
        items: items,
        new_customer_name:    newName,
        new_customer_contact: document.getElementById('new_customer_contact')?.value||'',
        new_customer_phone:   document.getElementById('new_customer_phone')?.value||'',
    };
}

async function saveQuote() {
    const data = collectFormData();
    if (!data) return;
    document.getElementById('loading').classList.add('active');
    try {
        const r = await fetch(window.location.href, {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
        });
        const res = await r.json();
        document.getElementById('loading').classList.remove('active');
        if (res.success) { alert('保存成功！'); window.location.href='quote_view.php?id=<?php echo $quote_id; ?>'; }
        else alert('保存失败：'+res.message);
    } catch(e) {
        document.getElementById('loading').classList.remove('active');
        alert('保存出错，请重试');
    }
}

// ── 表头 ─────────────────────────────────────────────────
const THEAD    = ['拖动','序号','产品类型','品牌/型号','配置参数','单位','数量','单价','成本价','金额','操作'];
const THEAD_W  = ['40px','50px','200px','100px','','60px','75px','100px','100px','95px','60px'];
const THEAD_CLS= ['','','','','','','','','cost-col','',''];

function initTableHead() {
    const tr = document.querySelector('#itemsTableHead tr');
    tr.innerHTML = THEAD.map((h,i)=>`<th${THEAD_CLS[i]?' class="'+THEAD_CLS[i]+'"':''}${THEAD_W[i]?' style="width:'+THEAD_W[i]+'"':''}>${h}</th>`).join('');
}

// ── 加载已有行 ────────────────────────────────────────────
function loadExistingItems() {
    itemsData.forEach(item => {
        itemCounter++;
        const tbody = document.getElementById('itemsTableBody');
        const row = document.createElement('tr');
        row.setAttribute('data-item-id', itemCounter);
        if (item.product_id) row.setAttribute('data-product-id', item.product_id);
        const cat   = item.category || '';
        const qty   = item.quantity || 1;
        const price = item.price    || 0;
        row.innerHTML = `
            <td><span class="drag-handle">☰</span></td>
            <td><strong class="seq-num">${item.seq||itemCounter}</strong></td>
            <td>
                <select class="item-category"
                    style="width:100%;background:#eff6ff;border-color:#93c5fd;font-weight:600;color:#135286;">
                    ${buildCatOpts(cat)}
                </select>
            </td>
            <td><input type="text" class="item-spec" value="${escapeHtml(item.product_name||'')}" placeholder="品牌/型号"></td>
            <td><input type="text" class="item-product-name" value="${escapeHtml(item.spec||'')}" placeholder="配置参数"></td>
            <td><input type="text" class="item-unit" value="${escapeHtml(item.unit||'台')}"></td>
            <td><input type="number" class="item-qty" value="${qty}" min="1" onchange="calcRowTotal(this)"></td>
            <td><input type="number" class="item-price" value="${price}" step="any" onchange="calcRowTotal(this)"></td>
            <td class="cost-col"><input type="number" class="item-cost" value="${item.cost||0}" min="0" step="any" onchange="calcRowTotal(this)" style="background:#fff8e1;width:100%;"></td>
            <td><strong class="item-sub">¥${(qty*price).toFixed(2)}</strong></td>
            <td><button type="button" class="btn-remove" onclick="removeItem(this)">删除</button></td>
        `;
        tbody.appendChild(row);
    });
    updateSeq(); calculateTotal();
}

// ── 三层选产品（新增行专用） ─────────────────────────────

// 大类选项（品牌整机开放全部分类），selectedName 用于已有行预选
function buildCatOpts(selectedName = '') {
    let html = '<option value="">— 请选产品大类 —</option>';
    categoriesData.forEach(cat => {
        const sel = (selectedName && cat.name === selectedName) ? ' selected' : '';
        html += `<option value="${cat.id}"${sel}>${escapeHtml(cat.name)}</option>`;
    });
    return html;
}

// 产品选项（按大类 + 关键字，品牌整机开放全部类型）
async function buildProdOpts(catId, kw) {
    if (!catId) return '<option value="">— 请先选次级类 —</option>';
    const products = await fetchProductsByCategory(catId);
    if (!products || !products.length) return '<option value="">— 该分类暂无产品 —</option>';
    const kl = (kw||'').toLowerCase().trim();
    const list = kl ? products.filter(p=>(p.name||'').toLowerCase().includes(kl)||(p.spec||'').toLowerCase().includes(kl)) : products;
    let html = `<option value="">— 请选择项目 (${list.length}) —</option>`;
    list.forEach(p => {
        const tl = p.product_type==='service'?'[服务] ':'[设备] ';
        const st = p.stock_quantity<=0?' [缺货]':(p.stock_quantity<5?` [库存:${p.stock_quantity}]`:'');
        html += `<option value="${p.id}"
            data-name="${escapeHtml(p.name)}" data-spec="${escapeHtml(p.spec||'')}"
            data-price="${p.default_price||0}" data-cost="${p.cost_price||0}"
            data-unit="${escapeHtml(p.unit||'项')}" data-type="${p.product_type||''}">
            ${tl}${escapeHtml(p.name)}${st} — ¥${p.default_price||0}
        </option>`;
    });
    return html;
}

// 新增行专用：大类选项（第一层）
function buildParentCatOpts() {
    let html = '';
    NPC_PARENT_CATS.forEach((cat, i) => {
        html += `<option value="${cat.id}"${i===0?' selected':''}>${escapeHtml(cat.name)}</option>`;
    });
    return html;
}

// 新增行专用：次级类选项（第二层）
function buildSubCatOpts(parentId) {
    const children = NPC_CATS_BY_PARENT[parentId] || [];
    let html = '<option value="">— 请选次级类 —</option>';
    children.forEach(c => { html += `<option value="${c.id}">${escapeHtml(c.name)}</option>`; });
    return html;
}

// 大类切换（新增行）
async function onNrParentChange(id, sel) {
    const parentId  = sel.value;
    const subSel    = document.getElementById('nr-cs-'+id);
    const filterInp = document.getElementById('nr-fi-'+id);
    const prodSel   = document.getElementById('nr-ps-'+id);
    filterInp.value = '';
    document.getElementById('nr-'+id).setAttribute('data-product-id','');
    if (!parentId) {
        subSel.innerHTML = '<option value="">— 请选次级类 —</option>';
        subSel.disabled  = true;
        prodSel.innerHTML = '<option value="">— 请先选次级类 —</option>';
        return;
    }
    const children = NPC_CATS_BY_PARENT[parentId] || [];
    if (!children.length) {
        // 该大类本身就是叶子分类，直接当次级类使用并加载产品
        const pc = NPC_PARENT_CATS.find(c => c.id == parentId);
        subSel.innerHTML = `<option value="${parentId}">${escapeHtml(pc ? pc.name : '')}</option>`;
        subSel.disabled  = false;
        prodSel.innerHTML = await buildProdOpts(parentId, '');
    } else {
        subSel.innerHTML = buildSubCatOpts(parentId);
        subSel.disabled  = false;
        prodSel.innerHTML = '<option value="">— 请先选次级类 —</option>';
    }
}

// 次级类切换（新增行）
async function onNrSubChange(id, sel) {
    document.getElementById('nr-fi-'+id).value = '';
    document.getElementById('nr-ps-'+id).innerHTML = await buildProdOpts(sel.value, '');
}

// 关键字过滤
async function onNrFilter(id, kw) {
    const catId = document.getElementById('nr-cs-'+id).value;
    document.getElementById('nr-ps-'+id).innerHTML = await buildProdOpts(catId, kw);
}

// 选中产品
function onNrProdSel(id, sel) {
    const opt = sel.options[sel.selectedIndex];
    const row = document.getElementById('nr-'+id);
    if (!opt || !opt.value || opt.value==='custom') { row.setAttribute('data-product-id',''); return; }
    row.setAttribute('data-product-id', opt.value);
    // 品牌/型号 → item-spec（collectFormData读它存到product_name）
    row.querySelector('.item-spec').value         = (opt.dataset.spec || '').split(' ')[0];
    // 配置参数 → item-product-name（collectFormData读它存到spec）
    const nameStr = opt.dataset.name || '';
    const specStr = opt.dataset.spec || '';
    row.querySelector('.item-product-name').value = specStr ? nameStr + ' ' + specStr : nameStr;
    row.querySelector('.item-price').value        = opt.dataset.price || 0;
    row.querySelector('.item-cost').value         = opt.dataset.cost  || 0;
    row.querySelector('.item-unit').value         = opt.dataset.unit  || '台';
    // item-category 是大类 select，已由用户选择，无需覆盖
    calcRowTotal(row.querySelector('.item-price'));
}

// 添加新行
function addItem() {
    itemCounter++;
    const id = itemCounter;
    const row = document.createElement('tr');
    row.id = 'nr-'+id;
    row.setAttribute('data-item-id', id);
    row.setAttribute('data-product-id', '');
    row.classList.add('new-row-select');
    row.innerHTML = `
        <td><span class="drag-handle">☰</span></td>
        <td><strong class="seq-num">${id}</strong></td>
        <td>
            <select id="nr-parent-${id}" onchange="onNrParentChange(${id},this)"
                style="width:100%;background:#eff6ff;border-color:#93c5fd;font-weight:600;color:#135286;margin-bottom:3px;">
                ${buildParentCatOpts()}
            </select>
            <select id="nr-cs-${id}" class="item-category" onchange="onNrSubChange(${id},this)"
                style="width:100%;">
                ${buildSubCatOpts(NPC_PARENT_CATS[0] ? NPC_PARENT_CATS[0].id : '')}
            </select>
        </td>
        <td><input type="text" class="item-spec" value="" placeholder="品牌/型号"></td>
        <td>
            <input id="nr-fi-${id}" type="text" placeholder="🔍 关键字过滤（可选）"
                oninput="onNrFilter(${id},this.value)"
                style="margin-bottom:3px;font-size:12px;">
            <select id="nr-ps-${id}" onchange="onNrProdSel(${id},this)">
                <option value="">— 请先选次级类 —</option>
            </select>
            <input type="hidden" class="item-product-name" value="">
        </td>
        <td><input type="text" class="item-unit" value="台"></td>
        <td><input type="number" class="item-qty" value="1" min="1" onchange="calcRowTotal(this)"></td>
        <td><input type="number" class="item-price" value="0" step="any" onchange="calcRowTotal(this)"></td>
        <td class="cost-col"><input type="number" class="item-cost" value="0" min="0" step="any" onchange="calcRowTotal(this)" style="background:#fff8e1;width:100%;"></td>
        <td><strong class="item-sub">¥0.00</strong></td>
        <td><button type="button" class="btn-remove" onclick="removeItem(this)">删除</button></td>
    `;
    document.getElementById('itemsTableBody').appendChild(row);
    updateSeq();
}

</script>
</body>
</html>
