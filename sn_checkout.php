<?php
/**
 * 文件名：sn_checkout.php
 * 版本: 2.0
 * 功能: 销售出库核销（统一布局风格）
 */
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';
$message = $error = '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── AJAX 接口 ──
if (isset($_GET['action'])) {
    session_write_close(); // 释放session锁，避免AJAX请求互相等待
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'get_categories') {
        $pid = intval($_GET['type'] ?? 0);
        $stmt = $conn->prepare("SELECT id, name FROM product_categories WHERE parent_id=? ORDER BY sort_order ASC");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    } elseif ($_GET['action'] === 'get_products') {
        $cid = intval($_GET['cat_id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, name, sku, stock_quantity FROM products WHERE category_id=? AND is_active=1 AND (user_id=0 OR user_id=" . intval($_SESSION['user_id']) . ") ORDER BY name ASC");
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }
    writeLog($conn, 'serials', 'status', '序列号出库');
    exit;
}

// ── 大类 ──
$parent_cats = $conn->query("SELECT id, name FROM product_categories WHERE parent_id=0 ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);

// ── 客户列表 ──
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name ASC")->fetch_all(MYSQLI_ASSOC);

// ── 最近送货单号 ──
$delivery_nos = $conn->query("SELECT delivery_no FROM deliveries ORDER BY id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

// ── 处理出库 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id  = intval($_POST['product_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $out_qty     = intval($_POST['out_qty'] ?? 0);
    $host_sn     = trim($_POST['host_sn'] ?? '');
    $sale_date   = $_POST['sale_date'] ?? date('Y-m-d');
    $delivery_no = !empty($_POST['delivery_no_manual'])
                   ? trim($_POST['delivery_no_manual'])
                   : trim($_POST['delivery_no_select'] ?? '');
    $sn_list_raw = trim($_POST['sn_list'] ?? '');
    $sns = array_unique(array_filter(array_map('trim', preg_split('/[\n\r,]+/', $sn_list_raw))));

    if ($product_id <= 0) {
        $error = "请选择出库产品";
    } elseif ($customer_id <= 0) {
        $error = "请选择销售客户";
    } elseif ($out_qty <= 0) {
        $error = "出库数量必须大于 0";
    } elseif (!empty($sns) && count($sns) > $out_qty) {
        $error = "序列号数量（" . count($sns) . "）不能超过出库数量（$out_qty）";
    } else {
        // 检查库存是否充足
        $stock_row = $conn->query("SELECT stock_quantity FROM products WHERE id=$product_id AND (user_id=0 OR user_id=" . intval($_SESSION['user_id']) . ")") ->fetch_assoc();
        $cur_stock = $stock_row ? intval($stock_row['stock_quantity']) : 0;
        if ($cur_stock < $out_qty) {
            $error = "库存不足：当前库存 $cur_stock 件，出库数量 $out_qty 件";
        } else {
            $conn->begin_transaction();
            try {
                // 按出库数量扣减库存
                $stk = $conn->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=? AND stock_quantity>=? AND (user_id=0 OR user_id=" . intval($_SESSION['user_id']) . ")");
                $stk->bind_param("iii", $out_qty, $product_id, $out_qty);
                $stk->execute();
                if ($stk->affected_rows < 1) {
                    throw new Exception("库存扣减失败，请刷新后重试");
                }
                $stk->close();

                // 若有序列号，逐条标记为已售出
                if (!empty($sns)) {
                    $invalid_sns = [];
                    $check_stmt  = $conn->prepare("SELECT id FROM product_serials WHERE sn_code=? AND product_id=? AND status='in_stock'");
                    $update_stmt = $conn->prepare("UPDATE product_serials SET status='sold',customer_id=?,sale_date=?,host_sn=?,delivery_no=?,out_date=NOW() WHERE sn_code=?");
                    foreach ($sns as $sn) {
                        $check_stmt->bind_param('si', $sn, $product_id);
                        $check_stmt->execute();
                        $res = $check_stmt->get_result();
                        if ($res && $res->fetch_assoc()) {
                            $update_stmt->bind_param('issss', $customer_id, $sale_date, $host_sn, $delivery_no, $sn);
                            $update_stmt->execute();
                        } else {
                            $invalid_sns[] = $sn;
                        }
                    }
                    if (!empty($invalid_sns)) {
                        throw new Exception("以下SN无效或不属于该产品：" . implode('、', $invalid_sns));
                    }
                    $sn_count = count($sns);
                    $message = "成功出库 $out_qty 件，已核销 $sn_count 条序列号！";
                } else {
                    $message = "成功出库 $out_qty 件（无序列号）！";
                }
                $conn->commit();
            } catch (Exception $ex) {
                $conn->rollback();
                $error = "出库失败：" . $ex->getMessage();
            }
        }
    }
}

$conn->close();
?>
<?php $page_title = '销售出库 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

/* 导航栏 */

/* 侧边栏 */

/* 主内容 */
.
.btn-nav { padding:8px 16px; background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; color:#4a5568; font-size:13px; text-decoration:none; transition:all .2s; }
.btn-nav:hover { background:#edf2f7; }

/* 提示 */
.alert-error   { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }

/* 两栏布局 */
.layout { display:block; }

/* 表单卡片 */
.card-header h2 { font-size:16px; font-weight:700; color:#1a202c; }
.card-

/* 表单元素 */
.form-group label { display:block; font-size:12px; font-weight:700; color:#4a5568; margin-bottom:8px; letter-spacing:.3px; }
.step-num { display:inline-block; width:20px; height:20px; background:linear-gradient(135deg,#1f6dad,#135286); color:white; border-radius:50%; font-size:11px; line-height:20px; text-align:center; margin-right:6px; }
.form-group select,
.form-group input[type="text"],
.form-group input[type="date"] {
    width:100%; padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; background:white; transition:all .2s; font-family:inherit;
}
.form-group select:focus,
.form-group input:focus { border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
.input-gap { margin-top:8px; }

/* SN 输入区 */
.sn-card { background:white; border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; margin-top:24px; }
.sn-area { width:100%; height:220px; padding:14px; border:none; border-top:2px dashed #f59e0b; background:#fffbeb; font-family:monospace; font-size:14px; outline:none; resize:none; line-height:1.8; }
.sn-area:focus { background:#faf9ff; border-color:#1f6dad; }
.sn-footer { padding:12px 18px; border-top:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
.sn-count { font-size:13px; color:#1f6dad; font-weight:600; }
.sn-hint { font-size:11px; color:#a0aec0; }

/* 提交按钮 */
.btn-submit { width:100%; padding:15px; background:linear-gradient(135deg,#1f6dad,#135286); color:white; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; transition:all .2s; letter-spacing:.5px; }
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(31,109,173,.4); }

/* 说明框 */
.info-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:14px 16px; margin-top:16px; font-size:12px; color:#0369a1; line-height:1.8; }
.info-box strong { display:block; margin-bottom:4px; color:#0c4a6e; }
</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容 -->
<div class="main">
    <div style="max-width:800px;margin:0 auto;">

    <div class="page-header" style="margin-bottom:24px;">
        <div class="page-title">📤 销售出库核销</div>
        <a href="products.php"   class="btn-nav">← 返回产品管理</a>
        <a href="sn_tracker.php" class="btn-nav">🔍 序列号管理</a>
        <a href="sn_import.php"  class="btn-nav">🚚 采购入库</a>
    </div>

    <?php if($message): ?>
    <div class="alert alert-success"><span>✅</span><span><?=e($message)?></span></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert alert-error"><span>❌</span><span><?=e($error)?></span></div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">

        <div class="form-card">
            <div class="card-header">
                <span style="font-size:18px;">📋</span>
                <h2>出库信息填写</h2>
            </div>
            <div class="card-body">

                <!-- 产品选择：大类 + 子类 + 型号 -->
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:18px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">1</span>产品大类</label>
                        <select id="m_type" required onchange="fetchCats()">
                            <option value="">请选择大类...</option>
                            <?php foreach($parent_cats as $c): ?>
                            <option value="<?=$c['id']?>"><?=e($c['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">2</span>产品子类</label>
                        <select id="s_cat" required onchange="fetchProds()" disabled>
                            <option value="">请先选大类...</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">3</span>搜索并选择型号</label>
                        <input type="text" id="p_search" placeholder="输入名称/SKU过滤..."
                            style="margin-bottom:6px;border:1px solid #1f6dad;background:#f8f7ff;"
                            oninput="filterProducts()" disabled>
                        <select id="p_id" name="product_id" required size="4" disabled onchange="updateStock()">
                            <option value="">请先选子类...</option>
                        </select>
                        <div id="stock_tip" style="font-size:12px;color:#718096;margin-top:4px;"></div>
                    </div>
                </div>

                <!-- 第二行：客户 + 日期 + 出库数量 -->
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">4</span>销售客户 *</label>
                        <select name="customer_id" required style="width:100%;">
                            <option value="">请选择客户...</option>
                            <?php foreach($customers as $c): ?>
                            <option value="<?=$c['id']?>"><?=e($c['company_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">5</span>销售日期</label>
                        <input type="date" name="sale_date" value="<?=date('Y-m-d')?>" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">6</span>出库数量 <span style="font-size:11px;color:#a0aec0;font-weight:400;">（必填）</span></label>
                        <input type="number" name="out_qty" id="out_qty" min="1" value=""
                            style="width:100%;padding:11px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;"
                            oninput="onQtyInput()" onfocus="this.select()" placeholder="数量">
                        <div style="margin-top:4px;font-size:12px;">
                            <span id="qty_warn" style="color:#be123c;font-weight:600;"></span>
                        </div>
                    </div>
                </div>

                <!-- 第三行：送货单 + 整机SN + 备注 -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:0;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">7</span>关联送货单号</label>
                        <select name="delivery_no_select" style="width:100%;margin-bottom:6px;">
                            <option value="">从已有送货单选择...</option>
                            <?php foreach($delivery_nos as $d): ?>
                            <option value="<?=e($d['delivery_no'])?>"><?=e($d['delivery_no'])?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="delivery_no_manual" placeholder="或手动输入新单号..." style="width:100%;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">8</span>整机/套装 SN <span style="font-weight:400;color:#a0aec0;font-size:11px;">（选填）</span></label>
                        <input type="text" name="host_sn" placeholder="整机序列号..." style="width:100%;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">9</span>备注 <span style="font-weight:400;color:#a0aec0;font-size:11px;">（选填）</span></label>
                        <input type="text" name="notes" placeholder="如合同号、项目名等..." style="width:100%;">
                    </div>
                </div>

            </div>
        </div>

        <!-- 第七项：SN扫码区 -->
        <div class="form-card">
            <div class="card-header">
                <h2><span class="step-num">7</span>批量扫入序列号
                    <span style="font-size:12px;color:#a0aec0;font-weight:400;margin-left:8px;">（可选，无序列号产品留空即可）</span>
                </h2>
            </div>
            <textarea class="sn-area" name="sn_list" id="sn_list"
                placeholder="有序列号时在此扫入，每行一个；无序列号产品留空即可&#10;&#10;SN20240001&#10;SN20240002&#10;..."
                oninput="countSN()" autofocus></textarea>
            <div class="sn-footer">
                <span class="sn-count" id="sn_count">尚未扫入序列号</span>
                <span class="sn-hint">支持换行 / 逗号分隔 / 自动去重</span>
            </div>
        </div>

        <button type="submit" class="btn-submit" onclick="return confirmSubmit()">
            ✅ 确认核销并扣减库存
        </button>

        <div class="info-box" style="margin-top:16px;">
            <strong>📌 操作说明</strong>
            · 系统自动校验每个 SN 是否状态为「在库」<br>
            · 任意 SN 无效则整批回滚，不做部分提交<br>
            · 出库成功后库存数量自动减少，状态变为「已售出」
        </div>

    </form>
    </div>
</div>

<script>
let allProducts = [];

function fetchCats() {
    const tid = document.getElementById('m_type').value;
    const sCat = document.getElementById('s_cat');
    const pSearch = document.getElementById('p_search');
    const pId = document.getElementById('p_id');
    sCat.innerHTML = '<option>加载中...</option>'; sCat.disabled = true;
    pSearch.disabled = true; pSearch.value = '';
    pId.disabled = true; pId.innerHTML = '<option value="">请先选子类...</option>';
    document.getElementById('stock_tip').textContent = '';
    allProducts = [];
    if (!tid) return;
    fetch(`sn_checkout.php?action=get_categories&type=${tid}`)
        .then(r=>r.json()).then(data=>{
            sCat.innerHTML = '<option value="">请选择子类...</option>';
            data.forEach(c=>sCat.add(new Option(c.name,c.id)));
            sCat.disabled = false;
        });
}

function fetchProds() {
    const cid = document.getElementById('s_cat').value;
    const pId = document.getElementById('p_id');
    const pSearch = document.getElementById('p_search');
    pId.innerHTML = '<option>加载中...</option>'; pId.disabled = true;
    pSearch.disabled = true; pSearch.value = '';
    document.getElementById('stock_tip').textContent = '';
    allProducts = [];
    if (!cid) return;
    fetch(`sn_checkout.php?action=get_products&cat_id=${cid}`)
        .then(r=>r.json()).then(data=>{
            allProducts = data;
            renderProducts(data);
            pId.disabled = false; pSearch.disabled = false;
        });
}

function renderProducts(data) {
    const pId = document.getElementById('p_id');
    pId.innerHTML = data.length
        ? data.map(p=>`<option value="${p.id}" data-stock="${p.stock_quantity}">${p.name}${p.sku?' ['+p.sku+']':''} （库存:${p.stock_quantity}）</option>`).join('')
        : '<option value="">无匹配产品</option>';
    pId.value = '';
    document.getElementById('stock_tip').textContent = '';
}

function filterProducts() {
    const kw = document.getElementById('p_search').value.toLowerCase();
    renderProducts(kw ? allProducts.filter(p=>
        (p.name||'').toLowerCase().includes(kw)||(p.sku||'').toLowerCase().includes(kw)
    ) : allProducts);
}

function updateStock() {
    const pId = document.getElementById('p_id');
    const opt = pId.options[pId.selectedIndex];
    const tip = document.getElementById('stock_tip');
    if (opt && opt.value) {
        const stock = parseInt(opt.dataset.stock) || 0;
        tip.textContent = `当前库存：${stock} 件`;
        tip.style.color = stock > 0 ? '#15803d' : '#be123c';
    } else {
        tip.textContent = '';
    }
    onQtyInput();
}

function onQtyInput() {
    const qty = parseInt(document.getElementById('out_qty').value) || 0;
    const pId = document.getElementById('p_id');
    const opt = pId.options[pId.selectedIndex];
    const warn = document.getElementById('qty_warn');
    if (opt && opt.value && qty > 0) {
        const stock = parseInt(opt.dataset.stock) || 0;
        warn.textContent = qty > stock ? `⚠️ 超出库存 ${stock} 件` : '';
    } else {
        warn.textContent = '';
    }
    countSN();
}

function countSN() {
    const raw = document.getElementById('sn_list').value;
    const sns = [...new Set(raw.split(/[\n\r,]+/).map(s=>s.trim()).filter(Boolean))];
    const el  = document.getElementById('sn_count');
    const qty = parseInt(document.getElementById('out_qty').value) || 0;
    if (sns.length) {
        const over = qty > 0 && sns.length > qty;
        el.style.color = over ? '#be123c' : '#1f6dad';
        el.textContent = `已扫入 ${sns.length} 个序列号（自动去重）` + (over ? ` ⚠️ 超过出库数量 ${qty}` : '');
    } else {
        el.textContent = '尚未扫入序列号（无序列号产品留空即可）';
        el.style.color = '#a0aec0';
    }
}

function confirmSubmit() {
    const pid = document.getElementById('p_id').value;
    if (!pid) { sapToast('请选择出库产品', 'warning'); return false; }
    const qty = parseInt(document.getElementById('out_qty').value) || 0;
    if (qty <= 0) { sapToast('请填写出库数量', 'warning'); return false; }
    const pId = document.getElementById('p_id');
    const opt = pId.options[pId.selectedIndex];
    const stock = opt ? parseInt(opt.dataset.stock) || 0 : 0;
    if (qty > stock) { sapToast(`库存不足：当前库存 ${stock} 件，出库数量 ${qty} 件`, 'info'); return false; }
    const sns = [...new Set(document.getElementById('sn_list').value.split(/[\n\r,]+/).map(s=>s.trim()).filter(Boolean))];
    if (sns.length > qty) { sapToast(`序列号数量（${sns.length}）不能超过出库数量（${qty}）`, 'error'); return false; }
    if (sns.length > 0) {
        return confirm(`确认出库 ${qty} 件，并核销 ${sns.length} 条序列号？`);
    } else {
        return confirm(`确认出库 ${qty} 件（无序列号）？`);
    }
}
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
