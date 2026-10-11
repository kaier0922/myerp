<?php
/**
 * 文件名: sn_import.php
 * 版本: 2.0
 * 功能: 采购扫码入库（统一布局风格）
 */
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── AJAX 接口 ──
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'get_categories') {
        $pid = intval($_GET['type'] ?? 0);
        $stmt = $conn->prepare("SELECT id, name FROM product_categories WHERE parent_id=? ORDER BY sort_order ASC");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    } elseif ($_GET['action'] === 'get_products') {
        $cid = intval($_GET['cat_id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, name, sku, spec, default_price as price, unit, stock_quantity as stock FROM products WHERE category_id=? AND is_active=1 AND (user_id=0 OR user_id=" . intval($_SESSION['user_id']) . ") ORDER BY name ASC");
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }
    writeLog($conn, 'serials', 'import', '导入序列号');
    exit;
}

// ── 供应商 ──
$suppliers = $conn->query("SELECT id, company_name FROM suppliers WHERE is_active=1 AND " . tenantWhere() . " ORDER BY company_name ASC")->fetch_all(MYSQLI_ASSOC);

// ── 大类 ──
$parent_cats = $conn->query("SELECT id, name FROM product_categories WHERE parent_id=0 ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);

// ── 处理提交 ──
$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id    = intval($_POST['product_id'] ?? 0);
    $supplier_id   = intval($_POST['supplier_id'] ?? 0);
    $purchase_price= floatval($_POST['purchase_price'] ?? 0);
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d H:i:s');
    $manual_qty    = intval($_POST['manual_qty'] ?? 0);
    $sn_list_raw   = trim($_POST['sn_list'] ?? '');
    $sns = array_unique(array_filter(array_map('trim', preg_split('/[\n\r,]+/', $sn_list_raw))));

    if ($product_id <= 0) {
        $error = "❌ 请选择产品型号";
    } elseif ($manual_qty <= 0) {
        $error = "❌ 入库数量必须大于 0";
    } elseif (!empty($sns) && count($sns) > $manual_qty) {
        $error = "❌ 序列号数量（" . count($sns) . "）不能超过入库数量（$manual_qty）";
    } else {
        // 入库数量始终以 manual_qty 为准；序列号独立记录
        $conn->begin_transaction();
        try {
            $op = $nickname;
            // 更新库存（以填写的入库数量为准）
            $stmt_tmp = $conn->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=? AND (user_id=0 OR user_id=" . intval($_SESSION['user_id']) . ")");
            $stmt_tmp->bind_param("ii", $manual_qty, $product_id);
            $stmt_tmp->execute();
            $stmt_tmp->close();
            // 若有序列号，逐条写入
            if (!empty($sns)) {
                $stmt = $conn->prepare("INSERT INTO product_serials (product_id,sn_code,supplier_id,purchase_price,operator,status,in_date) VALUES (?,?,?,?,?,'in_stock',?)");
                foreach ($sns as $sn) {
                    $stmt->bind_param('isidss', $product_id, $sn, $supplier_id, $purchase_price, $op, $purchase_date);
                    $stmt->execute();
                }
                $sn_count = count($sns);
                $message = "✅ 成功入库 $manual_qty 件，已录入 $sn_count 条序列号！<a href='stock_list.php' style='margin-left:12px;color:#1f6dad'>查看入库记录</a>";
            } else {
                $message = "✅ 成功入库 $manual_qty 件（无序列号）！<a href='stock_list.php' style='margin-left:12px;color:#1f6dad'>查看入库记录</a>";
            }
            // ── 始终写入入库日志 ──
            $sn_codes_str = !empty($sns) ? implode("
", $sns) : '';
            $pname_stmt = $conn->prepare("SELECT name FROM products WHERE id=? LIMIT 1");
            $pname_stmt->bind_param('i', $product_id);
            $pname_stmt->execute();
            $pname_row = $pname_stmt->get_result()->fetch_assoc();
            $pname_str = $pname_row['name'] ?? "产品ID:{$product_id}";
            $pname_stmt->close();
            $log_stmt = $conn->prepare("INSERT INTO stock_in_log (user_id,product_id,product_name,quantity,unit_price,total_amount,supplier_id,supplier_name,purchase_date,sn_codes,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $log_total = $purchase_price * $manual_qty;
            // 查供应商名称
            $log_sup = '';
            if ($supplier_id > 0) {
                $sn_sp = $conn->prepare('SELECT company_name FROM suppliers WHERE id=? LIMIT 1');
                $sn_sp->bind_param('i', $supplier_id);
                $sn_sp->execute();
                $sn_row = $sn_sp->get_result()->fetch_assoc();
                $log_sup = $sn_row['company_name'] ?? '';
                $sn_sp->close();
            }
            $log_note = $_POST['notes'] ?? '';
            $log_uid = intval($_SESSION['user_id']);
            $log_stmt->bind_param('iisiddissssi', $log_uid, $product_id, $pname_str, $manual_qty, $purchase_price, $log_total, $supplier_id, $log_sup, $purchase_date, $sn_codes_str, $log_note, $log_uid);
            $log_stmt->execute();
            $log_stmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ 入库失败：" . ($conn->errno == 1062 ? "存在重复序列号，请检查后重试" : $e->getMessage());
        }
    }
}

$conn->close();
?>
<?php $page_title = '采购入库 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

/* 导航栏 */

/* 侧边栏 */

/* 主内容 */
.
.btn-back { padding:8px 16px; background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; color:#4a5568; font-size:13px; text-decoration:none; transition:all .2s; }
.btn-back:hover { background:#edf2f7; }

/* 表单卡片 */
.form-card-header { padding:20px 28px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; }
.form-card-header h2 { font-size:16px; font-weight:700; color:#1a202c; }
.form-card-

/* 步骤标识 */
.steps { display:flex; gap:0; margin-bottom:28px; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0; }
.step { flex:1; padding:12px 16px; text-align:center; font-size:13px; font-weight:600; color:#a0aec0; background:#f7fafc; border-right:1px solid #e2e8f0; transition:all .3s; }
.step:last-child { border-right:none; }
.step.done { background:#f0fdf4; color:#15803d; }
.step.active { background:linear-gradient(135deg,#1f6dad,#135286); color:white; }

/* 表单网格 */
.form-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
.span2 { grid-column:span 2; }
.span3 { grid-column:span 3; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#4a5568; margin-bottom:8px; letter-spacing:.3px; }
.form-group .step-num { display:inline-block; width:20px; height:20px; background:linear-gradient(135deg,#1f6dad,#135286); color:white; border-radius:50%; font-size:11px; line-height:20px; text-align:center; margin-right:6px; }
.form-group select,
.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="date"] {
    width:100%; padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; background:white; transition:all .2s; font-family:inherit;
}
.form-group select:focus,
.form-group input:focus { border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
.form-group select[size] { height:130px; }
.kw-input { margin-bottom:8px; border:1px solid #1f6dad !important; background:#f8f7ff !important; }

/* SN 输入区 */
.sn-area { width:100%; height:180px; padding:14px; border:2px dashed #f59e0b; border-radius:10px; background:#fffbeb; font-family:monospace; font-size:14px; outline:none; resize:vertical; line-height:1.6; transition:all .2s; }
.sn-area:focus { border-color:#1f6dad; background:#faf9ff; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
.sn-hint { font-size:12px; color:#92400e; margin-top:6px; }
.sn-count { font-size:12px; color:#1f6dad; font-weight:600; margin-top:4px; }

/* 提交按钮 */
.btn-submit { width:100%; padding:16px; background:linear-gradient(135deg,#1f6dad,#135286); color:white; border:none; border-radius:10px; font-size:16px; font-weight:700; cursor:pointer; transition:all .2s; margin-top:24px; letter-spacing:.5px; }
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(31,109,173,.4); }

/* 提示 */
.alert-error   { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }

/* 历史记录区 */
.divider { display:flex; align-items:center; gap:16px; margin:32px 0; }
.divider::before, .divider::after { content:''; flex:1; height:1px; background:#e2e8f0; }
.divider span { font-size:13px; color:#a0aec0; font-weight:600; white-space:nowrap; }
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

    <div class="page-header">
        <div class="page-title">🚚 采购扫码入库</div>
        <a href="stock_list.php" style="margin-left:auto;padding:9px 18px;background:linear-gradient(135deg,#1f6dad,#135286);color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500">📋 入库记录</a>
        <a href="products.php"   class="btn-back">← 返回产品管理</a>
        <a href="sn_tracker.php" class="btn-back">🔍 序列号管理</a>
    </div>

    <?php if($message): ?>
    <div class="alert alert-success"><?=$message?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert alert-error"><?=$error?></div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-card-header">
            <span style="font-size:18px;">📦</span>
            <h2>批量序列号入库</h2>
            <span style="font-size:13px;color:#a0aec0;margin-left:8px;">选择产品 → 填写采购信息 → 扫入序列号 → 提交</span>
        </div>
        <div class="form-card-body">
            <form method="POST" id="importForm">

                <!-- 第一行：大类 + 子类 -->
                <div class="form-grid" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label><span class="step-num">1</span>产品大类</label>
                        <select id="m_type" required onchange="fetchCats()">
                            <option value="">请选择大类...</option>
                            <?php foreach($parent_cats as $c): ?>
                            <option value="<?=$c['id']?>"><?=e($c['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="step-num">2</span>产品子类</label>
                        <select id="s_cat" required onchange="fetchProds()" disabled>
                            <option value="">请先选大类...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="step-num">3</span>搜索并选择型号</label>
                        <input type="text" id="p_search" class="kw-input" placeholder="输入产品名/SKU过滤..." oninput="filterProducts()" disabled>
                        <select id="p_id" name="product_id" required size="4" disabled>
                            <option value="">请先选子类...</option>
                        </select>
                    </div>
                </div>

                <!-- 第二行：供应商 + 单价 + 日期 + 数量 四列一行 -->
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:16px;margin-bottom:18px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">4</span>来源供应商</label>
                        <select name="supplier_id" style="width:100%;">
                            <option value="">不指定供应商</option>
                            <?php foreach($suppliers as $s): ?>
                            <option value="<?=$s['id']?>"><?=e($s['company_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">5</span>采购单价 (¥)</label>
                        <input type="number" step="any" name="purchase_price" placeholder="0.00" oninput="onQtyInput()" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">6</span>入库日期</label>
                        <input type="date" name="purchase_date" value="<?=date('Y-m-d')?>" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><span class="step-num">7</span>入库数量 <span style="font-size:11px;color:#a0aec0;font-weight:400;">（必填）</span></label>
                        <input type="number" name="manual_qty" id="manual_qty" min="1" value=""
                            style="width:100%;padding:11px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;"
                            oninput="onQtyInput()" onfocus="this.select()" placeholder="数量">
                        <div style="margin-top:4px;font-size:12px;color:#1f6dad;font-weight:600;">
                            <span id="qty_hint"></span>
                            <span id="total_hint"></span>
                        </div>
                    </div>
                </div>

                <!-- 备注 -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label><span class="step-num">8</span>备注
                        <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:8px;">（可选，如来源渠道、批次号等）</span>
                    </label>
                    <input type="text" name="notes" class="form-control" placeholder="备注信息..." style="width:100%;padding:11px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;">
                </div>
                <!-- 第九项：SN扫码区 -->
                <div class="form-group">
                    <label><span class="step-num">9</span>批量扫入序列号
                        <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:8px;">（可选，无序列号产品留空即可）</span>
                    </label>
                    <textarea class="sn-area" name="sn_list" id="sn_list"
                        placeholder="有序列号时在此扫入，每行一个；无序列号产品留空，填写上方数量即可&#10;SN20240001&#10;SN20240002&#10;..."
                        oninput="countSN()"></textarea>
                    <div style="display:flex;justify-content:space-between;margin-top:6px;">
                        <span class="sn-hint">💡 支持扫码枪连续扫入，自动去重；每行一个或逗号分隔均可</span>
                        <span class="sn-count" id="sn_count" style="font-size:13px;color:#1f6dad;font-weight:600;"></span>
                    </div>
                </div>

                <button type="submit" class="btn-submit" onclick="return confirmSubmit()">
                    ✅ 确认入库并更新库存数量
                </button>

            </form>
        </div>
    </div>
    </div>
</div>

<script>
let allProducts = [];

function fetchCats() {
    const tid = document.getElementById('m_type').value;
    const sCat = document.getElementById('s_cat');
    const pSearch = document.getElementById('p_search');
    const pId = document.getElementById('p_id');

    sCat.innerHTML = '<option>加载中...</option>';
    sCat.disabled = true;
    pSearch.disabled = true; pSearch.value = '';
    pId.disabled = true;
    pId.innerHTML = '<option value="">请先选子类...</option>';
    allProducts = [];

    if (!tid) return;

    fetch(`sn_import.php?action=get_categories&type=${tid}`)
        .then(r => r.json())
        .then(data => {
            sCat.innerHTML = '<option value="">请选择子类...</option>';
            data.forEach(c => sCat.add(new Option(c.name, c.id)));
            sCat.disabled = false;
        });
}

function fetchProds() {
    const cid = document.getElementById('s_cat').value;
    const pId = document.getElementById('p_id');
    const pSearch = document.getElementById('p_search');

    pId.innerHTML = '<option>加载中...</option>';
    pId.disabled = true;
    pSearch.disabled = true; pSearch.value = '';
    allProducts = [];

    if (!cid) return;

    fetch(`sn_import.php?action=get_products&cat_id=${cid}`)
        .then(r => r.json())
        .then(data => {
            allProducts = data;
            renderProducts(data);
            pId.disabled = false;
            pSearch.disabled = false;
        });
}

function renderProducts(data) {
    const pId = document.getElementById('p_id');
    pId.innerHTML = data.length
        ? data.map(p => `<option value="${p.id}">${p.name}${p.sku?' ['+p.sku+']':''}</option>`).join('')
        : '<option value="">无匹配产品</option>';
    pId.value = '';
}

function filterProducts() {
    const kw = document.getElementById('p_search').value.toLowerCase();
    renderProducts(kw ? allProducts.filter(p =>
        (p.name||'').toLowerCase().includes(kw) || (p.sku||'').toLowerCase().includes(kw)
    ) : allProducts);
}

function countSN() {
    const raw = document.getElementById('sn_list').value;
    const sns = [...new Set(raw.split(/[\n\r,]+/).map(s=>s.trim()).filter(Boolean))];
    const el = document.getElementById('sn_count');
    const qty = parseInt(document.getElementById('manual_qty').value) || 0;
    const price = parseFloat(document.querySelector('input[name=purchase_price]').value) || 0;
    const totalHint = document.getElementById('total_hint');
    if (totalHint) {
        totalHint.textContent = (qty > 0 && price > 0) ? '合计 ¥' + (qty * price).toFixed(2) : '';
    }
    if (sns.length > 0) {
        const over = qty > 0 && sns.length > qty;
        el.style.color = over ? '#be123c' : '#1f6dad';
        el.textContent = `已输入 ${sns.length} 个序列号（自动去重）` + (over ? ` ⚠️ 超过入库数量 ${qty}` : '');
    } else {
        el.textContent = '';
    }
}

function onQtyInput() {
    const qty = document.getElementById('manual_qty');
    if (qty.value < 0) qty.value = 0;
    // 重新检查序列号数量提示
    countSN();
    // 更新件数提示
    const v = parseInt(qty.value) || 0;
    document.getElementById('qty_hint').textContent = v > 0 ? `件` : '件';
}

function confirmSubmit() {
    const pid = document.getElementById('p_id').value;
    if (!pid) { sapToast('请选择具体产品型号', 'warning'); return false; }
    const qty = parseInt(document.getElementById('manual_qty').value) || 0;
    if (qty <= 0) { sapToast('请填写入库数量（必须大于0）', 'warning'); return false; }
    const sn  = document.getElementById('sn_list').value.trim();
    const sns = sn ? [...new Set(sn.split(/[\n\r,]+/).map(s=>s.trim()).filter(Boolean))] : [];
    if (sns.length > qty) {
        sapToast(`序列号数量（${sns.length}）不能超过入库数量（${qty}），请修正后再提交`, 'error');
        return false;
    }
    if (sns.length > 0) {
        return confirm(`确定入库 ${qty} 件，并录入 ${sns.length} 条序列号吗？`);
    } else {
        return confirm(`确定入库 ${qty} 件（无序列号）吗？`);
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
