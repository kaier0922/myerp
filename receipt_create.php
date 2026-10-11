<?php
/**
 * 文件名：receipt_create.php
 * 功能：新建收据（散客/客户），保存后跳转查看页
 */
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';
if (!function_exists('getDBConnection')) { die('❌ config.php 未提供 getDBConnection()'); }
$conn = getDBConnection();
if (!($conn instanceof mysqli)) { die('❌ 数据库连接未建立'); }

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id    = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? intval($_POST['customer_id']) : NULL;
    $walkin_name    = trim($_POST['walkin_name'] ?? '');
    $walkin_phone   = trim($_POST['walkin_phone'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $tax_rate       = floatval($_POST['tax_rate'] ?? 0);
    $paid_amount    = isset($_POST['paid_amount']) && $_POST['paid_amount'] !== '' ? floatval($_POST['paid_amount']) : NULL;
    $seal_id        = isset($_POST['seal_id']) && $_POST['seal_id'] !== '' ? intval($_POST['seal_id']) : NULL;
    $note           = trim($_POST['note'] ?? '');

    $datePart = date('Ymd');
    $cntStmt  = $conn->prepare("SELECT COUNT(*) AS cnt FROM receipts WHERE DATE(created_at)=CURDATE()");
    if (!$cntStmt) die('❌ SQL 预处理失败：' . $conn->error);
    $cntStmt->execute();
    $cntRes    = $cntStmt->get_result()->fetch_assoc();
    $seq       = str_pad((int)($cntRes['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    $receipt_no = "R{$datePart}-{$seq}";

    $names = $_POST['item_name'] ?? [];
    $specs = $_POST['spec']      ?? [];
    $qtys  = $_POST['qty']       ?? [];
    $units = $_POST['unit']      ?? [];
    $prices= $_POST['unit_price']?? [];

    $subtotal = 0.0; $items = [];
    $count = max(count($names), count($specs), count($qtys), count($units), count($prices));
    for ($i=0; $i<$count; $i++) {
        $n = trim($names[$i] ?? ''); if ($n === '') continue;
        $q = (float)($qtys[$i]  ?? 1);
        $p = (float)($prices[$i]?? 0);
        $u = trim($units[$i] ?? '');
        $s = trim($specs[$i] ?? '');
        $a = $q * $p;
        $items[] = ['name'=>$n,'spec'=>$s,'qty'=>$q,'unit'=>$u,'price'=>$p,'amount'=>$a];
        $subtotal += $a;
    }
    $tax_amount   = round($subtotal * $tax_rate / 100, 2);
    $total_amount = $subtotal + $tax_amount;
    if ($paid_amount === NULL) $paid_amount = $total_amount;

    $sql  = "INSERT INTO receipts (receipt_no, customer_id, walkin_name, walkin_phone, related_type, related_id,
               subtotal, tax_amount, total_amount, paid_amount, payment_method, note, seal_id, created_by, user_id)
             VALUES (?, ?, ?, ?, 'direct', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die('❌ SQL 预处理失败：' . $conn->error);
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('sissddddssiii',
        $receipt_no, $customer_id, $walkin_name, $walkin_phone,
        $subtotal, $tax_amount, $total_amount, $paid_amount, $payment_method, $note, $seal_id, $uid, $uid
    );
    if (!$stmt->execute()) die('❌ 保存收据失败：' . $stmt->error);
    $rid = $conn->insert_id;

    if (!empty($items)) {
        $isql = "INSERT INTO receipt_items (receipt_id, item_name, spec, qty, unit, unit_price, amount, user_id) VALUES (?,?,?,?,?,?,?,?)";
        $ist  = $conn->prepare($isql);
        if (!$ist) die('❌ 明细预处理失败：' . $conn->error);
        foreach ($items as $it) {
            $ist->bind_param('isssdddi', $rid, $it['name'], $it['spec'], $it['qty'], $it['unit'], $it['price'], $it['amount'], $uid);
            if (!$ist->execute()) die('❌ 写入明细失败：' . $ist->error);
        }
    }

    // 同步更新关联送货单的已收金额
    if (!empty($_POST['related_delivery_id'])) {
        $did = intval($_POST['related_delivery_id']);
        $conn->query("UPDATE deliveries d SET 
            paid_amount = (SELECT COALESCE(SUM(ar.paid_amount),0) FROM accounts_receivable ar WHERE ar.related_type='delivery' AND ar.related_id={$did})
            WHERE d.id={$did}");
    }
    writeLog($conn, 'receipts', 'create', '新建收据');
    header('Location: receipt_view.php?id=' . $rid);
    exit;
}

$customers = $conn->query("SELECT id, COALESCE(company_name, contact_name) AS display_name FROM customers WHERE " . tenantWhere() . " ORDER BY id DESC");
$seals     = $conn->query("SELECT id, seal_name FROM seals WHERE " . tenantWhere() . " ORDER BY id DESC");
?>
<?php $page_title = '新建收据 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
/* 居中布局 */
.main-content{display:flex;flex-direction:column;align-items:center;}
.page-header{width:100%;max-width:1100px;}
.form-card{width:100%;max-width:1100px;}

.btn-danger    { background:#f56565; color:white; border:none; }
.btn-danger:hover  { background:#e53e3e; }

.form-card-header { padding:16px 24px; border-bottom:1px solid #e2e8f0; font-size:15px; font-weight:600; color:#1a202c; display:flex; align-items:center; justify-content:space-between; }
.form-card-body { padding:24px; }

.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; }
.form-input, .form-select {
    padding:10px 12px; border:1px solid #e2e8f0; border-radius:6px;
    font-size:14px; transition:all .2s; font-family:inherit; background:white; color:#2d3748;
}
.form-input:focus, .form-select:focus { outline:none; border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }

/* 支付方式 combo */
.payment-combo input {
    width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:6px;
    font-size:14px; font-family:inherit; color:#2d3748; background:white; transition:all .2s;
}
.payment-combo input:focus { outline:none; border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
.payment-combo input::-webkit-calendar-picker-indicator { display:none; }

.items-table-wrap { overflow-x:auto; }
.items-table { width:100%; border-collapse:collapse; font-size:14px; }
.items-table th { background:#f7fafc; padding:10px 12px; text-align:left; font-size:12px; font-weight:600; color:#718096; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.items-table td { padding:8px 6px; border-bottom:1px solid #f0f4f8; vertical-align:middle; }
.items-table tbody tr:hover { background:#f7fafc; }
.items-table td input { width:100%; padding:7px 9px; border:1px solid #e2e8f0; border-radius:5px; font-size:13px; font-family:inherit; transition:border-color .2s; }
.items-table td input:focus { outline:none; border-color:#1f6dad; box-shadow:0 0 0 2px rgba(31,109,173,.1); }
.amount-cell { text-align:right; font-size:14px; font-weight:600; color:#135286; padding:0 10px; white-space:nowrap; }

</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区（功能逻辑完全不变） -->
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">➕ 新建收据</h1>
        <a href="receipts.php" class="btn btn-secondary">← 返回收据列表</a>
    </div>

    <form method="post">

        <!-- 客户信息 -->
        <div class="form-card">
            <div class="form-card-header">👤 客户信息</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">客户（可选）</label>
                        <select name="customer_id" class="form-select">
                            <option value="">—— 散客 ——</option>
                            <?php while($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['display_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">客户姓名</label>
                        <input name="walkin_name" class="form-input" placeholder="无客户时可填写">
                    </div>
                    <div class="form-group">
                        <label class="form-label">客户电话</label>
                        <input name="walkin_phone" class="form-input" placeholder="联系方式">
                    </div>
                </div>
            </div>
        </div>

        <!-- 收款信息 -->
        <div class="form-card">
            <div class="form-card-header">💰 收款信息</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">支付方式</label>
                        <div class="payment-combo">
                            <input type="text" name="payment_method"
                                   list="payment-methods-list"
                                   placeholder="选择或输入支付方式"
                                   autocomplete="off">
                            <datalist id="payment-methods-list">
                                <option value="现金">
                                <option value="微信支付">
                                <option value="支付宝">
                                <option value="银行转账">
                                <option value="刷卡（POS）">
                                <option value="对公转账">
                                <option value="月结">
                            </datalist>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">税率（%）</label>
                        <input name="tax_rate" class="form-input" value="0" type="number" step="0.01" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">实收金额</label>
                        <input name="paid_amount" class="form-input" placeholder="默认 = 合计" type="number" step="any">
                    </div>
                    <div class="form-group">
                        <label class="form-label">盖章（可选）</label>
                        <select name="seal_id" class="form-select">
                            <option value="">—— 不盖章 ——</option>
                            <?php while($s = $seals->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['seal_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">备注</label>
                        <input name="note" class="form-input" placeholder="可选填写">
                    </div>
                </div>
            </div>
        </div>

        <!-- 收据明细 -->
        <div class="form-card">
            <div class="form-card-header">
                <span>📋 收据明细</span>
                <button class="btn btn-secondary btn-sm" type="button" onclick="addRow()">➕ 添加一行</button>
            </div>
            <div class="form-card-body" style="padding:0;">
                <div class="items-table-wrap">
                    <datalist id="unitList">
                        <option value="个"><option value="台"><option value="套"><option value="件">
                        <option value="块"><option value="条"><option value="根"><option value="张">
                        <option value="包"><option value="箱"><option value="米"><option value="千克">
                        <option value="吨"><option value="升"><option value="卷"><option value="颗">
                        <option value="支"><option value="盒"><option value="批"><option value="次">
                        <option value="小时"><option value="天"><option value="月"><option value="年">
                        <option value="项">
                    </datalist>
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width:28%; padding-left:16px;">项目名称</th>
                                <th>规格</th>
                                <th style="width:10%;">数量</th>
                                <th style="width:9%;">单位</th>
                                <th style="width:12%;">单价</th>
                                <th style="width:12%;">金额</th>
                                <th style="width:8%; text-align:center;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding-left:10px;"><input name="item_name[]" required></td>
                                <td><input name="spec[]"></td>
                                <td><input name="qty[]" value="1" oninput="calcRow(this)" type="number" step="0.01"></td>
                                <td><input name="unit[]" list="unitList" placeholder="个/小时"></td>
                                <td><input name="unit_price[]" value="0.00" oninput="calcRow(this)" type="number" step="any"></td>
                                <td class="amount-cell">0.00</td>
                                <td style="text-align:center;"><button type="button" class="btn btn-danger btn-sm" onclick="delRow(this)">删除</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-actions">
                <a href="receipts.php" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">💾 保存收据</button>
            </div>
        </div>

    </form>
</div>

<script>
function addRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    const tr = tbody.rows[0].cloneNode(true);
    tr.querySelectorAll('input').forEach(inp => {
        if (inp.name.includes('qty')) inp.value = '1';
        else if (inp.name.includes('unit_price')) inp.value = '0.00';
        else inp.value = '';
    });
    tr.querySelector('.amount-cell').innerText = '0.00';
    tbody.appendChild(tr);
}
function delRow(btn) {
    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody.rows.length > 1) btn.closest('tr').remove();
}
function calcRow(inp) {
    const tr = inp.closest('tr');
    const q = parseFloat(tr.querySelector('input[name="qty[]"]').value || 0);
    const p = parseFloat(tr.querySelector('input[name="unit_price[]"]').value || 0);
    tr.querySelector('.amount-cell').innerText = (q * p).toFixed(2);
}
</script>
</body>
</html>
