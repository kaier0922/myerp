<?php
/**
 * quote_create_weak_current.php — v5.1 (2026-03-20)
 * 三层选产品：大类下拉 → 关键字过滤 → 产品下拉
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$template_type = 'strong_current';
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $input               = json_decode(file_get_contents('php://input'), true);
        $customer_id         = intval($input['customer_id'] ?? 0);
        $contact_person      = $input['contact_person'] ?? '';
        $contact_phone       = $input['contact_phone'] ?? '';
        $quote_date          = $input['quote_date'] ?? date('Y-m-d');
        $valid_days          = intval($input['valid_days'] ?? 3);
        $project_name        = $input['project_name'] ?? '';
        $project_location    = $input['project_location'] ?? '';
        $construction_period = $input['construction_period'] ?? '';
        $terms               = $input['terms'] ?? '';
        $remark              = $input['remark'] ?? '';
        $final_amount        = floatval($input['final_amount'] ?? 0);
        $discount            = floatval($input['discount'] ?? 0);
        $unit_qty            = intval($input['unit_qty'] ?? 1);
        $status              = $input['status'] ?? '草稿';
        $items               = $input['items'] ?? [];
        $new_customer_name    = trim($input['new_customer_name'] ?? '');
        $new_customer_contact = trim($input['new_customer_contact'] ?? '');
        $new_customer_phone   = trim($input['new_customer_phone'] ?? '');
        $user_id             = $_SESSION['user_id'] ?? 0;

        if ($customer_id === -1 && !empty($new_customer_name)) {
            $stmt = $conn->prepare("INSERT INTO customers (company_name,contact_name,phone,created_at) VALUES (?,?,?,NOW())");
            $stmt->bind_param("sss", $new_customer_name, $new_customer_contact, $new_customer_phone);
            if ($stmt->execute()) $customer_id = $conn->insert_id;
            else throw new Exception('客户创建失败');
            $stmt->close();
        }

        $prefix = 'QT' . date('Ymd');
        $r2 = $conn->query("SELECT MAX(CAST(SUBSTRING(quote_no, -4) AS UNSIGNED)) as maxn FROM quotes WHERE quote_no LIKE '{$prefix}%'");
        $cnt2 = ($r2->fetch_assoc()['maxn'] ?? 0) + 1;
        $quote_no = $prefix . str_pad($cnt2, 4, '0', STR_PAD_LEFT);

        $template_type = 'strong_current';
        $stmt = $conn->prepare("INSERT INTO quotes (quote_no,customer_id,contact_person,contact_phone,project_name,project_location,construction_period,template_type,quote_date,valid_days,final_amount,discount,unit_qty,terms,remark,status,user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->bind_param("sisssssssiiddsssi",
            $quote_no,$customer_id,$contact_person,$contact_phone,
            $project_name,$project_location,$construction_period,$template_type,
            $quote_date,$valid_days,$final_amount,$discount,$unit_qty,
            $terms,$remark,$status,$user_id
        );
        if (!$stmt->execute()) throw new Exception('创建报价单失败: ' . $stmt->error);
        $quote_id = $conn->insert_id;
        $stmt->close();

        foreach ($items as $item) {
            $seq          = intval($item['seq'] ?? 0);
            $product_id   = isset($item['product_id']) && $item['product_id'] !== '' ? intval($item['product_id']) : null;
            $category     = $item['category'] ?? '';
            $product_name = $item['product_name'] ?? '';
            $spec         = $item['spec'] ?? '';
            $unit         = $item['unit'] ?? '项';
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
                if ($upd) {
                    $uid = $_SESSION['user_id'];
                    $upd->bind_param('ddii', $price, $writeback_cost, $product_id, $uid);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
        $conn->close();
        echo json_encode(['success'=>true,'message'=>'保存成功，单号：'.$quote_no,'quote_id'=>$quote_id]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name");
$tpl = $conn->query("SELECT * FROM quote_templates WHERE template_code = '$template_type' AND user_id = {$_SESSION['user_id']}");
$template = $tpl ? $tpl->fetch_assoc() : null;
// 产品大类→次级类级联数据（与 quote_create.php / quote_create_brand_pc.php 的加载逻辑一致）
$_cats_all = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE is_active=1 ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
$_npc_parents = array_values(array_filter($_cats_all, fn($c) => $c['parent_id'] == 0));
// 强电工程报价单要求的大类展示顺序：强弱电工程优先，其余依次；仅本页排序，不改全局 sort_order
$_npc_order_ids = [83, 2, 1, 3, 4, 6, 7, 5]; // 强弱电工程,整机设备,硬件配件,网络设备,外设周边,软件授权,技术服务,监控安防
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
<?php $page_title = '强电工程报价单 v5.1'; include __DIR__ . '/head.php'; ?>
<style>
.navbar-actions{display:flex;gap:12px;}
.section-title{font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #e2e8f0;}
.cost-col{display:none;}
body.cost-mode .cost-col{display:table-cell;}
.cost-row{display:none;}
body.cost-mode .cost-row{display:flex;}
.btn-cost-toggle{padding:8px 18px;border:2px solid #94a3b8;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:#f1f5f9;color:#64748b;transition:all .2s;white-space:nowrap;}
.btn-cost-toggle.on{background:#16a34a;border-color:#16a34a;color:#fff;}
.btn-cost-toggle:hover{opacity:.85;}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px;}
.form-label.required::after{content:'*';color:#ef4444;margin-left:4px;}
.form-input,.form-select,.form-textarea{padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;transition:all .2s;}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.form-textarea{resize:vertical;min-height:80px;}
.items-wrap{border:1px solid #e2e8f0;border-radius:8px;overflow:auto;margin-bottom:12px;}
.items-table{width:100%;border-collapse:collapse;min-width:700px;}
.items-table th{padding:10px 8px;background:#f7fafc;font-size:12px;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;text-align:center;}
.items-table td{padding:8px;border-bottom:1px solid #f0f4f8;}
.items-table input,.items-table select{width:100%;padding:5px 7px;border:1px solid #e2e8f0;border-radius:4px;font-size:13px;}
.items-table input:focus,.items-table select:focus{outline:none;border-color:#1f6dad;}
.btn-add-row{padding:10px 20px;background:#1f6dad;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;margin-bottom:16px;}
.btn-add-row:hover{background:#135286;}
.btn-delete-row{padding:4px 8px;background:#ef4444;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;}
.summary-box{background:#f7fafc;border-radius:8px;padding:20px;margin-top:20px;}
.summary-row{display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;}
.summary-row.total{font-size:18px;font-weight:700;padding-top:12px;border-top:2px solid #e2e8f0;}
.sel-cat{background:#eff6ff!important;border-color:#93c5fd!important;font-weight:600!important;color:#135286!important;margin-bottom:4px!important;}
.custom-input-row{background:#fffbeb!important;}
.custom-inputs{display:none;padding:6px;background:#fef3c7;border-radius:5px;margin-top:5px;}
.custom-inputs.active{display:block;}
.custom-inputs input{border:1px solid #fbbf24!important;background:white;}
.loading-tip{color:#94a3b8;font-size:12px;padding:4px 0;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="page-header">
  <h1 class="page-title">📝 创建强电工程报价单</h1>
  <div style="display:flex;gap:10px;">
    <a href="quotes.php" class="btn btn-secondary">← 返回列表</a>
    <button class="btn btn-primary" onclick="saveQuote('草稿')" style="min-width:120px;">💾 保存草稿</button>
  </div>
</div>
<div style="max-width:1200px;margin:0 auto;">
<form id="quoteForm">
  <input type="hidden" name="template_type" value="strong_current">

  <!-- 页面标题居中 -->
  <div style="text-align:center;margin-bottom:24px;">
    <h1 style="font-size:24px;font-weight:700;color:#1a202c;">强电工程报价单</h1>
    <p style="font-size:13px;color:#718096;margin-top:4px;">填写完成后点击右上角「保存草稿」，审核后可生成送货单</p>
  </div>

  <div class="form-card">
    <h2 class="section-title">基本信息</h2>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label required">客户</label>
          <select id="customerSelect" class="form-select" onchange="fillCustomer(this)">
            <option value="">-- 选择已有客户 / 或下方手动输入 --</option>
            <?php if($customers) while($c=$customers->fetch_assoc()): ?>
            <option value="<?=$c['id']?>"
              data-contact="<?=htmlspecialchars($c['contact_name']??'')?>"
              data-phone="<?=htmlspecialchars($c['phone']??'')?>">
              <?=htmlspecialchars($c['company_name'])?><?=$c['contact_name']?' ('.htmlspecialchars($c['contact_name']).')':''?>
            </option>
            <?php endwhile; ?>
          </select>
          <input type="hidden" name="customer_id" id="customerId">
          <input type="text" name="customer_name_manual" id="customerNameManual"
                 class="form-input" style="margin-top:6px;"
                 placeholder="或手动输入新客户名称">
        </div>
        <div class="form-group" style="margin:0;"><label class="form-label required">报价日期</label><input type="date" name="quote_date" class="form-input" value="<?=date('Y-m-d')?>" required></div>
        <div class="form-group" style="margin:0;"><label class="form-label">有效期(天)</label><input type="number" name="valid_days" class="form-input" value="3"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
        <div class="form-group" style="margin:0;"><label class="form-label">联系人</label><input type="text" name="contact_person" class="form-input" placeholder="联系人姓名"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">联系电话</label><input type="text" name="contact_phone" class="form-input" placeholder="联系电话"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">报价单号</label><input type="text" name="quote_no" class="form-input" placeholder="留空自动生成"></div>
      </div>
  </div>

  <div class="form-card">
    <h2 class="section-title" style="display:flex;justify-content:space-between;align-items:center;">产品清单<button type="button" id="costToggleBtn" class="btn-cost-toggle" onclick="toggleCostMode()">💰 利润核算 OFF</button></h2>
    <div class="alert alert-info">
      💡 <strong>选产品四步走：</strong>
      ① 选<b>产品大类</b> → ② 选<b>次级类</b> → ③ 可选输入<b>关键字</b>过滤 → ④ 选<b>具体项目</b>
    </div>
    <button type="button" class="btn-add-row" onclick="addRow()">+ 添加行</button>
    <table class="items-table">
      <thead>
        <tr>
          <th style="width:40px;white-space:nowrap">序号</th>
          <th style="width:200px">产品类型</th>

          <th>配置参数</th>
          <th style="width:58px">单位</th>
          <th style="width:68px">数量</th>
          <th style="width:105px">单价</th>
          <th class="cost-col" style="width:100px">成本价</th>
          <th style="width:88px">小计</th>
          <th style="width:66px">操作</th>
        </tr>
      </thead>
      <tbody id="itemsBody"></tbody>
    </table>
    <div class="summary-box">
      <div class="summary-row"><span>小计:</span><span id="subtotal">¥0.00</span></div>
      <div class="summary-row">
        <span>折扣金额:</span>
        <input type="number" name="discount" class="form-input" style="width:140px;text-align:right" value="0" step="any" onchange="calcTotal()">
      </div>
      <div class="summary-row total"><span>合计:</span><span id="totalAmount">¥0.00</span></div>
      <div class="summary-row cost-row" style="color:#888;"><span>成本合计:</span><span id="totalCost">¥0.00</span></div>
      <div class="summary-row cost-row" style="color:#16a34a;font-weight:600;"><span>毛利润:</span><span id="totalProfit">¥0.00</span></div>
    </div>
  </div>

  <div class="form-card">
    <h2 class="section-title">条款说明</h2>
    <textarea id="terms" name="terms" class="form-textarea" rows="6"
        style="width:100%;min-height:130px;resize:vertical;display:block;font-size:14px;line-height:1.6;"
    ><?=htmlspecialchars($template['default_terms']??'')?></textarea>
  </div>

  <div class="form-card" style="margin-top:20px;">
    <h2 class="section-title">备注</h2>
    <textarea id="remark" name="remark" class="form-textarea" rows="3" placeholder="补充说明、特殊要求、交货条件等..." style="width:100%;"></textarea>
  </div>
</form>
</div><!-- end max-width -->
</main>

<script>
let rowIdx = 0;

function esc(s){ return (s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

/* ══════════════════════════════════════════════════
   产品库级联选择（大类→次级类→关键字→项目），与 quote_create.php 一致
   ══════════════════════════════════════════════════ */
const NPC_PARENT_CATS    = <?php echo $npcParentsJson; ?>;
const NPC_CATS_BY_PARENT = <?php echo $npcByParentJson; ?>;
const _npcParentOpts = NPC_PARENT_CATS.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
const _npcProdCache = {};

function npcParentChange(idx, parentId) {
    const sub = document.getElementById('npc_sub_'+idx);
    const kw  = document.getElementById('npc_kw_'+idx);
    const ps  = document.getElementById('npc_ps_'+idx);
    sub.innerHTML = '<option value="">② 选次级类</option>'; sub.disabled = true;
    kw.value = ''; kw.disabled = true;
    ps.innerHTML = '<option value="">④ 选具体项目</option>'; ps.disabled = true;
    document.getElementById('npc_catid_'+idx).value  = '';
    document.getElementById('npc_catlbl_'+idx).value = '';
    npcClearRow(idx);
    if (!parentId) return;
    const children = NPC_CATS_BY_PARENT[parentId] || [];
    if (!children.length) {
        const pc = NPC_PARENT_CATS.find(c => c.id == parentId);
        document.getElementById('npc_catid_'+idx).value  = parentId;
        document.getElementById('npc_catlbl_'+idx).value = pc ? pc.name : '';
        npcLoadProds(idx, parentId);
    } else {
        children.forEach(c => sub.add(new Option(c.name, c.id)));
        sub.disabled = false;
    }
}

function npcSubChange(idx, sel) {
    const catId = sel.value;
    const catName = sel.options[sel.selectedIndex]?.text || '';
    document.getElementById('npc_catid_'+idx).value  = catId;
    document.getElementById('npc_catlbl_'+idx).value = catName;
    const kw = document.getElementById('npc_kw_'+idx);
    const ps = document.getElementById('npc_ps_'+idx);
    kw.value = ''; kw.disabled = true;
    ps.innerHTML = '<option value="">加载中...</option>'; ps.disabled = true;
    npcClearRow(idx);
    if (!catId) return;
    npcLoadProds(idx, catId);
}

async function npcLoadProds(idx, catId) {
    if (!_npcProdCache[catId]) {
        const r = await fetch('api_products_by_category.php?cat_id=' + catId);
        _npcProdCache[catId] = await r.json();
    }
    npcRenderSel(idx, _npcProdCache[catId], '');
}

function npcRenderSel(idx, prods, kw) {
    const ps   = document.getElementById('npc_ps_'+idx);
    const kwEl = document.getElementById('npc_kw_'+idx);
    const kl   = (kw||'').toLowerCase().trim();
    const filtered = kl ? prods.filter(p =>
        (p.name||'').toLowerCase().includes(kl) ||
        (p.spec||'').toLowerCase().includes(kl) ||
        (p.supplier_name||'').toLowerCase().includes(kl)
    ) : prods;
    let html = `<option value="">④ 选具体项目 (${filtered.length})</option>`;
    html += '<option value="" data-custom="1" style="background:#fef3c7;font-weight:bold;">✏️ 自定义输入（不在产品库）</option>';
    filtered.forEach(p => {
        const tag   = p.product_type === 'service' ? '[服务] ' : '[设备] ';
        const stock = p.product_type === 'device'
            ? (p.stock_quantity==0?' [缺货]':(p.stock_quantity<5?` [库存:${p.stock_quantity}]`:'')) : '';
        html += `<option value="${p.id}"
            data-name="${esc(p.name)}"
            data-spec="${esc(p.spec||'')}"
            data-supplier="${esc(p.supplier_name||'')}"
            data-price="${p.default_price||0}"
            data-cost="${p.cost_price||0}"
            data-unit="${esc(p.unit||'项')}">
            ${tag}${esc(p.name)}${stock} — ¥${p.default_price||0}
        </option>`;
    });
    ps.innerHTML = html; ps.disabled = false; kwEl.disabled = false;
}

function npcFilter(idx, kw) {
    const catId = document.getElementById('npc_catid_'+idx).value;
    if (catId && _npcProdCache[catId]) npcRenderSel(idx, _npcProdCache[catId], kw);
}

function npcProdSel(idx, sel) {
    const opt = sel.options[sel.selectedIndex];
    const row = document.getElementById('row_'+idx);
    const ci  = document.getElementById('ci_'+idx);
    document.getElementById('npc_pid_'+idx).value = '';

    if (opt && opt.dataset.custom === '1') {
        row.classList.add('custom-input-row');
        ci.classList.add('active');
        document.getElementById('pr_'+idx).value = '';
        const inp = ci.querySelector('input');
        inp.value = '';
        setTimeout(()=>inp.focus(),80);
        return;
    }

    row.classList.remove('custom-input-row');
    ci.classList.remove('active');
    ci.querySelector('input').value = '';

    const val = sel.value;
    if (!val) { npcClearRow(idx); return; }

    document.getElementById('npc_pid_'+idx).value = val;
    document.getElementById('pr_'+idx).value = opt.dataset.price || 0;
    const ctEl = document.getElementById('ct_'+idx);
    if (ctEl) ctEl.value = opt.dataset.cost || 0;
    const unitEl = document.querySelector(`input[name="items[${idx}][unit]"]`);
    if (unitEl && opt.dataset.unit) unitEl.value = opt.dataset.unit;
    const rmEl = document.getElementById('rm_'+idx);
    if (rmEl) {
        const nameStr = opt.dataset.name || '';
        const specStr = opt.dataset.spec || '';
        rmEl.value = specStr ? nameStr + ' ' + specStr : nameStr;
    }
    calcRow(idx);
}

function npcClearRow(idx) {
    document.getElementById('npc_pid_'+idx).value = '';
    ['pr_','rm_','ct_'].forEach(p => { const el=document.getElementById(p+idx); if(el) el.value=''; });
    const sub = document.getElementById('sub_'+idx);
    if (sub) sub.textContent = '0.00';
    calcTotal();
}

function addRow() {
    rowIdx++;
    const id = rowIdx;
    const tr = document.createElement('tr');
    tr.id = 'row_' + id;
    tr.innerHTML = `
      <td style="text-align:center">${id}</td>
      <td style="width:200px">
        <select id="npc_cs_${id}" class="sel-cat" onchange="npcParentChange(${id}, this.value)">
          <option value="">① 选产品大类</option>${_npcParentOpts}
        </select>
        <select id="npc_sub_${id}" class="sel-filter" onchange="npcSubChange(${id}, this)" disabled>
          <option value="">② 选次级类</option>
        </select>
        <input id="npc_kw_${id}" type="text" placeholder="③ 关键字过滤（可选）"
               oninput="npcFilter(${id}, this.value)" disabled
               style="margin-bottom:4px;font-size:12px;">
        <select id="npc_ps_${id}" onchange="npcProdSel(${id}, this)" disabled>
          <option value="">④ 选具体项目</option>
        </select>
        <input type="hidden" id="npc_pid_${id}"    name="items[${id}][product_id]"  value="">
        <input type="hidden" id="npc_catid_${id}"  name="items[${id}][category_id]" value="">
        <input type="hidden" id="npc_catlbl_${id}" name="items[${id}][category]"    value="">
        <div id="ci_${id}" class="custom-inputs">
          <input type="text" name="items[${id}][custom_name]" placeholder="项目名称 *">
        </div>
      </td>

      <td><input type="text" id="rm_${id}" name="items[${id}][spec]" placeholder="产品名称及配置参数..." style="width:100%"></td>
      <td><input type="text" name="items[${id}][unit]" value="项"></td>
      <td><input type="number" name="items[${id}][quantity]" value="1" min="1" oninput="calcRow(${id})"></td>
      <td><input type="number" id="pr_${id}" name="items[${id}][price]" step="any" oninput="calcRow(${id})"></td>
      <td class="cost-col"><input type="number" id="ct_${id}" step="any" min="0" oninput="calcRow(${id})" style="background:#fff8e1;width:100%;"></td>
      <td style="text-align:right"><span id="sub_${id}">0.00</span></td>
      <td><button type="button" class="btn-delete-row" onclick="delRow(${id})">删除</button></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    reSeq();
}

function delRow(id) {
    if (!confirm('确定删除？')) return;
    document.getElementById('row_'+id)?.remove();
    reSeq(); calcTotal();
}

function reSeq() {
    document.querySelectorAll('#itemsBody tr').forEach((tr,i) => {
        if (tr.cells[0]) tr.cells[0].textContent = i+1;
    });
}

function calcRow(id) {
    const q = parseFloat(document.querySelector(`input[name="items[${id}][quantity]"]`)?.value)||0;
    const p = parseFloat(document.getElementById('pr_'+id)?.value)||0;
    const s = document.getElementById('sub_'+id);
    if (s) s.textContent = (q*p).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let sum = 0, totalCost = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const id = tr.id.replace('row_','');
        sum += parseFloat(document.getElementById('sub_'+id)?.textContent)||0;
        const q = parseFloat(document.querySelector(`input[name="items[${id}][quantity]"]`)?.value)||0;
        const c = parseFloat(document.getElementById('ct_'+id)?.value)||0;
        totalCost += q * c;
    });
    const disc = parseFloat(document.querySelector('input[name="discount"]')?.value)||0;
    document.getElementById('subtotal').textContent    = '¥' + sum.toFixed(2);
    document.getElementById('totalAmount').textContent = '¥' + (sum-disc).toFixed(2);
    if (document.getElementById('totalCost'))   document.getElementById('totalCost').textContent   = '¥' + totalCost.toFixed(2);
    if (document.getElementById('totalProfit')) document.getElementById('totalProfit').textContent = '¥' + (sum-disc-totalCost).toFixed(2);
}

function toggleCostMode() {
    const on = document.body.classList.toggle('cost-mode');
    const btn = document.getElementById('costToggleBtn');
    btn.textContent = on ? '💰 利润核算 ON' : '💰 利润核算 OFF';
    btn.classList.toggle('on', on);
    localStorage.setItem('costMode', on ? '1' : '0');
}


function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customerId').value = opt.value || '';
    if (opt.value) {
        document.getElementById('customerNameManual').value = '';
        const cp = document.querySelector('input[name="contact_person"]');
        const ph = document.querySelector('input[name="contact_phone"]');
        if (cp) cp.value = opt.dataset.contact || '';
        if (ph) ph.value = opt.dataset.phone   || '';
    }
}
function saveQuote(status) {
    const items = [];
    document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
        const rowId = tr.id.replace('row_','');
        const productSel = tr.querySelector('select[id^="npc_ps_"]');
        const pid = productSel?.value || '';
        const isCustom = tr.classList.contains('custom-input-row');
        const customInp = tr.querySelector('.custom-inputs input');
        const catName = document.getElementById('npc_catlbl_'+rowId)?.value || '';
        items.push({
            seq:          i + 1,
            product_id:   pid,
            category:     catName,
            product_name: isCustom
                ? (customInp?.value || '')
                : (document.getElementById('rm_'+rowId)?.value || ''),
            spec:         '',
            unit:         tr.querySelector('input[name*="[unit]"]')?.value || '项',
            quantity:     parseInt(tr.querySelector('input[name*="[quantity]"]')?.value) || 1,
            price:        parseFloat(document.getElementById('pr_'+rowId)?.value) || 0,
            cost:         parseFloat(document.getElementById('ct_'+rowId)?.value) || 0,
            remark:       ''
        });
    });
    if (items.length === 0) { sapToast('请至少添加一个产品', 'warning'); return; }
    const sum  = parseFloat(document.getElementById('subtotal').textContent.replace('¥',''))||0;
    const disc = parseFloat(document.querySelector('input[name="discount"]')?.value)||0;
    const payload = {
        status,
        customer_id:         parseInt(document.getElementById('customerId')?.value) || 0,
        contact_person:      document.querySelector('input[name="contact_person"]')?.value || '',
        contact_phone:       document.querySelector('input[name="contact_phone"]')?.value || '',
        quote_date:          document.querySelector('input[name="quote_date"]')?.value || '',
        valid_days:          parseInt(document.querySelector('input[name="valid_days"]')?.value) || 3,
        project_name:        document.querySelector('input[name="project_name"]')?.value || '',
        project_location:    document.querySelector('input[name="project_location"]')?.value || '',
        construction_period: document.querySelector('input[name="construction_period"]')?.value || '',
        terms:               (document.querySelector('textarea[name="terms"]') || document.getElementById('terms'))?.value || '',
        remark:              (document.querySelector('textarea[name="remark"]') || document.getElementById('remark'))?.value || '',
        discount:     disc,
        final_amount: items.reduce((s,i) => s + i.quantity * i.price, 0) - disc,
        unit_qty:     1,
        items
    };
    const btns = document.querySelectorAll('.btn-primary');
    btns.forEach(b=>{ b.disabled=true; b.textContent='保存中...'; });
    fetch('quote_create_strong_current.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r=>r.json())
    .then(d=>{
        if (d.success) { sapToast(d.message, 'info'); location.href='quotes.php'; }
        else { sapToast('保存失败：'+d.message, 'error'); btns.forEach(b=>{ b.disabled=false; b.textContent=b.textContent.replace('保存中...',''); }); }
    })
    .catch(e=>{ sapToast('出错：'+e.message, 'error'); btns.forEach(b=>{ b.disabled=false; b.textContent=b.textContent.replace('保存中...',''); }); });
}

window.onload = function() {
    addRow();
    if (localStorage.getItem('costMode') === '1') {
        document.body.classList.add('cost-mode');
        const btn = document.getElementById('costToggleBtn');
        if (btn) { btn.textContent = '💰 利润核算 ON'; btn.classList.add('on'); }
    }
};
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
