<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$error    = '';

// 默认类型（rental/loan）
$default_type = $_GET['type'] ?? 'rental';

// 客户列表
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name, phone FROM customers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type           = $_POST['type'] ?? 'rental';
    $customer_id    = intval($_POST['customer_id'] ?? 0);
    $customer_name  = trim($_POST['customer_name'] ?? '');
    $contact_name   = trim($_POST['contact_name']  ?? '');
    $contact_phone  = trim($_POST['contact_phone'] ?? '');
    $start_date     = $_POST['start_date'] ?? '';
    $end_date       = $_POST['end_date']   ?? '';
    $rent_cycle     = $_POST['rent_cycle'] ?? 'month';
    $rent_price     = floatval($_POST['rent_price'] ?? 0);
    $deposit        = floatval($_POST['deposit']    ?? 0);
    $deposit_status = $deposit > 0 ? ($_POST['deposit_status'] ?? 'unpaid') : 'unpaid';
    $deposit_paid   = $deposit > 0 && $deposit_status === 'paid' ? $deposit : 0;
    $notes          = trim($_POST['notes'] ?? '');

    // 设备明细
    $item_names    = $_POST['item_name']    ?? [];
    $item_serials  = $_POST['item_serial']  ?? [];
    $item_specs    = $_POST['item_spec']    ?? [];
    $item_labels   = $_POST['item_label']   ?? [];
    $item_costs    = $_POST['item_cost']    ?? [];
    $item_markets  = $_POST['item_market']  ?? [];
    $item_qtys     = $_POST['item_qty']     ?? [];
    $item_rents    = $_POST['item_rent']    ?? [];
    $item_notes    = $_POST['item_notes']   ?? [];

    if (!$customer_name) { $error = '请填写客户名称'; }
    elseif (!$start_date || !$end_date) { $error = '请填写租借起止日期'; }
    elseif ($end_date <= $start_date) { $error = '结束日期必须晚于开始日期'; }
    elseif (empty(array_filter($item_names))) { $error = '请至少添加一台设备'; }
    else {
        // 生成合同编号
        $prefix = $type === 'rental' ? 'RT' : 'LN';
        $contract_no = $prefix . date('YmdHis');

        // 计算总租金
        $total_rent = 0;
        if ($type === 'rental' && $rent_price > 0) {
            $diff_days = max(1, round((strtotime($end_date) - strtotime($start_date)) / 86400));
            if ($rent_cycle === 'day') {
                $total_rent = $rent_price * $diff_days;
            } elseif ($rent_cycle === 'week') {
                $total_rent = $rent_price * max(1, round($diff_days / 7));
            } elseif ($rent_cycle === 'month') {
                $total_rent = $rent_price * max(1, round($diff_days / 30));
            } elseif ($rent_cycle === 'year') {
                $total_rent = $rent_price * max(1, round($diff_days / 365));
            } else {
                $total_rent = $rent_price * $diff_days;
            }
        }

        $conn->begin_transaction();
        try {
            $rent_uid = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO rental_contracts
                (contract_no, type, customer_id, customer_name, contact_name, contact_phone,
                 start_date, end_date, rent_cycle, rent_price, deposit, deposit_status,
                 deposit_paid_amount, total_rent, paid_rent, status, notes, created_by, user_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,'active',?,?,?)");
            $stmt->bind_param('ssissssssddsddsii',
                $contract_no, $type, $customer_id, $customer_name, $contact_name, $contact_phone,
                $start_date, $end_date, $rent_cycle, $rent_price, $deposit, $deposit_status,
                $deposit_paid, $total_rent, $notes, $rent_uid, $rent_uid);
            $stmt->execute();
            $contract_id = $conn->insert_id;

            // 插入设备明细
            $istmt = $conn->prepare("INSERT INTO rental_items
                (contract_id, product_name, serial_no, label_no, spec, cost_price, market_price,
                 quantity, rent_price)
                VALUES (?,?,?,?,?,?,?,?,?)"); // 修复：加入 label_no
            foreach ($item_names as $i => $name) {
                $name = trim($name);
                if (!$name) continue;
                $serial = trim($item_serials[$i] ?? '');
                $label  = trim($item_labels[$i]  ?? '');  // 修复：读取 label
                $spec   = trim($item_specs[$i]   ?? '');
                $cost   = floatval($item_costs[$i]   ?? 0);
                $market = floatval($item_markets[$i] ?? 0);
                $qty    = intval($item_qtys[$i]   ?? 1);
                $irent  = floatval($item_rents[$i]  ?? 0);
                $istmt->bind_param("issssddid",
                    $contract_id, $name, $serial, $label, $spec, $cost, $market, $qty, $irent);
                $istmt->execute();
            }

            $conn->commit();
            writeLog($conn, 'rentals', 'create', '新建租借合同');
            header('Location: rental_view.php?id=' . $contract_id . '&created=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = '保存失败：' . $e->getMessage();
        }
    }
}

function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
$conn->close();
?>
<?php $page_title = ($default_type==='loan'?'新建借用':'新建租赁') . ' - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.back-btn{padding:8px 16px;background:white;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;}
.form-card-head{padding:16px 24px;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#1a202c;display:flex;align-items:center;gap:8px;}
.form-card-
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.form-grid.cols3{grid-template-columns:1fr 1fr 1fr;}
.form-label .req{color:#e53e3e;margin-left:2px;}
.form-label .hint{font-weight:400;color:#a0aec0;font-size:12px;margin-left:6px;}
.form-control{padding:9px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
select.form-control{background:white;}

/* 类型切换 */
.type-toggle{display:flex;gap:12px;margin-bottom:4px;}
.type-btn{flex:1;padding:14px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;text-align:center;transition:all .2s;background:white;}
.type-btn:hover{border-color:#1f6dad;}
.type-btn.active-rental{border-color:#1f6dad;background:#eef2ff;}
.type-btn.active-loan{border-color:#135286;background:#ecfdf5;}
.type-btn .type-icon{font-size:24px;margin-bottom:6px;}
.type-btn .type-name{font-size:14px;font-weight:600;color:#1a202c;}
.type-btn .type-desc{font-size:12px;color:#718096;margin-top:2px;}

/* 设备明细表格 */
.items-
.items-table
.items-table
.items-table td input{width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;}
.items-table td input:focus{outline:none;border-color:#1f6dad;}
.btn-del-row{padding:5px 10px;background:#fff5f5;color:#c53030;border:1px solid #feb2b2;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap;}
.btn-add-row{padding:8px 16px;background:#f7fafc;border:1px dashed #cbd5e0;border-radius:8px;font-size:13px;color:#4a5568;cursor:pointer;transition:all .2s;width:100%;margin-top:8px;}
.btn-add-row:hover{border-color:#1f6dad;color:#1f6dad;background:#f0f0ff;}

/* 租金区域（仅租赁显示） */
.rental-only{transition:all .3s;}
.rental-only.hidden{display:none;}

.btn-submit{padding:10px 28px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;}
.btn-submit.loan{background:linear-gradient(135deg,#135286,#047857);}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
.btn-cancel{padding:10px 24px;background:white;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#4a5568;text-decoration:none;}
.alert-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;}

/* 租金预览 */
.rent-preview{background:#eef2ff;border-radius:8px;padding:12px 16px;font-size:14px;color:#4338ca;margin-top:8px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div style="max-width:960px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title" id="pageTitle">
            <?=$default_type==='loan'?'🤝 新建借用':'📋 新建租赁'?>
        </h1>
            <a href="rentals.php" class="back-btn">← 返回租借管理</a>
    </div>

    <?php if ($error): ?>
    <div class="alert-error">❌ <?=e($error)?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- 类型选择 -->
        <div class="form-card">
            <div class="form-card-head">📌 合同类型</div>
            <div class="form-card-body">
                <input type="hidden" name="type" id="typeInput" value="<?=e($default_type)?>">
                <div class="type-toggle">
                    <div class="type-btn <?=$default_type==='rental'?'active-rental':''?>"
                         id="btn-rental" onclick="setType('rental')">
                        <div class="type-icon">💰</div>
                        <div class="type-name">收费租赁</div>
                        <div class="type-desc">按天或按月收租金，可收押金</div>
                    </div>
                    <div class="type-btn <?=$default_type==='loan'?'active-loan':''?>"
                         id="btn-loan" onclick="setType('loan')">
                        <div class="type-icon">🤝</div>
                        <div class="type-name">免费借用</div>
                        <div class="type-desc">IT外包客户免费借用，记录设备价值</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 客户信息 -->
        <div class="form-card">
            <div class="form-card-head">👤 客户信息</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">选择客户<span class="hint">（选择后自动填入信息）</span></label>
                        <select class="form-control" onchange="fillCustomer(this)">
                            <option value="">-- 手动填写 --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?=$c['id']?>"
                                data-name="<?=e($c['company_name']??$c['contact_name'])?>"
                                data-contact="<?=e($c['contact_name']??'')?>"
                                data-phone="<?=e($c['phone']??'')?>">
                                <?=e($c['company_name']??$c['contact_name'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">客户名称<span class="req">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="客户公司或姓名" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">联系人<span style="color:#a0aec0;font-size:12px;font-weight:400;margin-left:6px;">（选填，会显示在合同上）</span></label>
                        <input type="text" name="contact_name" id="contact_name" class="form-control" placeholder="联系人姓名">
                    </div>
                    <div class="form-group">
                        <label class="form-label">联系电话</label>
                        <input type="text" name="contact_phone" id="contact_phone" class="form-control" placeholder="手机号">
                    </div>
                </div>
                <input type="hidden" name="customer_id" id="customer_id" value="0">
            </div>
        </div>

        <!-- 租借信息 -->
        <div class="form-card">
            <div class="form-card-head">📅 租借信息</div>
            <div class="form-card-body">
                <!-- 第一行：开始日期 + 到期日期 + 押金（三列，两种模式都显示） -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label">开始日期<span class="req">*</span></label>
                        <input type="date" name="start_date" id="start_date" class="form-control"
                               value="<?=date('Y-m-d')?>" onchange="calcRent()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">到期日期<span class="req">*</span></label>
                        <input type="date" name="end_date" id="end_date" class="form-control"
                               value="<?=date('Y-m-d', strtotime('+1 month'))?>" onchange="calcRent()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="depositLabel">押金（元）<span class="hint">（0则无押金）</span></label>
                        <input type="number" name="deposit" id="deposit" class="form-control"
                               step="any" min="0" value="0" oninput="toggleDepositStatus()">
                    </div>
                </div>

                <!-- 第二行：租金周期 + 租金单价 + 押金状态（仅租赁/有押金时显示） -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:18px;">
                    <div class="form-group rental-only" id="cycleGroup">
                        <label class="form-label">租金周期<span class="rent-req" style="color:#e53e3e;display:none;">*</span></label>
                        <select name="rent_cycle" id="rent_cycle" class="form-control" onchange="calcRent()">
                            <option value="day">按天</option>
                            <option value="week">按周</option>
                            <option value="month">按月</option>
                            <option value="year">按年</option>
                        </select>
                    </div>
                    <div class="form-group rental-only" id="rentGroup">
                        <label class="form-label">租金单价（元/周期）<span class="rent-req" style="color:#e53e3e;display:none;">*</span><span class="rent-req" style="color:#a0aec0;font-size:11px;font-weight:400;display:none;"> 租赁类型必填</span></label>
                        <input type="number" name="rent_price" id="rent_price" class="form-control"
                               step="any" min="0" value="0" oninput="calcRent()">
                    </div>
                    <div class="form-group" id="depositStatusGroup" style="display:none;">
                        <label class="form-label">押金状态</label>
                        <select name="deposit_status" class="form-control">
                            <option value="unpaid">未收</option>
                            <option value="paid">已收</option>
                        </select>
                    </div>
                </div>

                <!-- 租金预览（仅租赁） -->
                <div class="rental-only">
                    <div class="rent-preview" id="rentPreview">
                        📊 请填写租金单价和日期以预览总金额
                    </div>
                </div>
            </div>
        </div>

        <!-- 设备明细 -->
        <div class="form-card">
            <div class="form-card-head">💻 设备明细</div>
            <div class="form-card-body" style="padding:16px;">
                <table class="items-table" id="itemsTable" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:28%;">设备名称<span style="color:#e53e3e;">*</span></th>
                            <th style="width:42%;">规格参数（处理器/内存/硬盘/显卡等）<span style="color:#e53e3e;">*</span></th>
                            <th style="width:12%;">市场估价(¥)</th>
                            <th style="width:8%;">数量</th>
                            <th style="width:10%;text-align:right;">小计(¥)</th>
                            <th style="width:60px;text-align:center;">删除</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr>
                            <td><input type="text" name="item_name[]" placeholder="如：联想ThinkPad X1" required style="width:100%;"></td>
                            <td><input type="text" name="item_spec[]" placeholder="如：i7-1260P / 16GB / 512GB SSD / 核显" required style="width:100%;"></td>
                            <td><input type="number" name="item_market[]" placeholder="0.00" step="any" min="0" value="0" style="width:100%;" oninput="calcRow(this)"></td>
                            <td><input type="number" name="item_qty[]" value="1" min="1" max="99999" style="width:100%;" oninput="calcRow(this)"></td>
                            <td class="subtotal-cell" style="text-align:right;font-weight:600;color:#3b82f6;padding-right:8px;">0.00</td>
                            <td style="text-align:center;"><button type="button" class="btn-del-row" onclick="delRow(this)">删除</button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn-add-row" onclick="addRow()">➕ 添加设备</button>
                <div style="display:flex;justify-content:flex-end;align-items:center;margin-top:10px;padding:10px 16px;background:#f0f4ff;border-radius:8px;font-size:14px;">
                    <span style="color:#4a5568;margin-right:12px;">合计金额：</span>
                    <span id="totalAmount" style="font-size:20px;font-weight:700;color:#1f6dad;font-family:'Courier New',monospace;">¥0.00</span>
                </div>
            </div>
        </div>

        <!-- 备注 -->
        <div class="form-card">
            <div class="form-card-head">📝 备注</div>
            <div class="form-card-body" style="padding:16px 24px;">
                <textarea name="notes" class="form-control" rows="5" placeholder="合同备注、特殊约定等..." style="width:100%;resize:vertical;min-height:120px;"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit" id="submitBtn">💾 创建合同</button>
            <a href="rentals.php" class="btn-cancel">取消</a>
        </div>
    </form>
</div>
</main>
<script>
let currentType = '<?=e($default_type)?>';
// 页面加载后立即触发一次，显示正确的必填标记
document.addEventListener('DOMContentLoaded', () => setType(currentType));

function setType(type) {
    currentType = type;
    document.getElementById('typeInput').value = type;

    // 动态必填标记
    const isRental = type === 'rental';
    const isLoan   = type === 'loan';
    document.querySelectorAll('.rent-req').forEach(el => el.style.display = isRental ? 'inline' : 'none');
    document.querySelectorAll('.loan-req').forEach(el => el.style.display = isLoan   ? 'inline' : 'none');
    document.getElementById('rent_price').required = isRental;
    // 类型按钮样式
    document.getElementById('btn-rental').className = 'type-btn' + (type==='rental' ? ' active-rental' : '');
    document.getElementById('btn-loan').className   = 'type-btn' + (type==='loan'   ? ' active-loan'   : '');

    // 页面标题
    document.getElementById('pageTitle').textContent = type==='rental' ? '📋 新建租赁' : '🤝 新建借用';

    // 提交按钮
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn-submit' + (type==='loan' ? ' loan' : '');

    // 显示/隐藏租赁专属字段
    document.querySelectorAll('.rental-only').forEach(el => {
        el.classList.toggle('hidden', type !== 'rental');
    });

    // 押金标签文字随类型切换
    const depLabel = document.getElementById('depositLabel');
    if(depLabel) depLabel.innerHTML = type==='loan'
        ? '押金（元）<span class="hint">（免费借用可选收押金）</span>'
        : '押金（元）<span class="hint">（留空或填0则无押金）</span>';

    // 租金表头列
    // 单价列常驻显示
    }

function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customer_id').value    = opt.value || 0;
    document.getElementById('customer_name').value  = opt.dataset.name    || '';
    document.getElementById('contact_name').value   = opt.dataset.contact || '';
    document.getElementById('contact_phone').value  = opt.dataset.phone   || '';
}

function calcRent() {
    const start = document.getElementById('start_date').value;
    const end   = document.getElementById('end_date').value;
    const price = parseFloat(document.getElementById('rent_price').value) || 0;
    const cycle = document.getElementById('rent_cycle').value;
    const preview = document.getElementById('rentPreview');
    if (!start || !end || !preview) return;
    const days   = Math.max(1, Math.round((new Date(end) - new Date(start)) / 86400000));
    const months = Math.max(1, Math.round(days / 30));
    let total = 0, desc = '';
    const weeks = Math.max(1, Math.round(days / 7));
    const years = Math.max(1, Math.round(days / 365));
    if (cycle === 'day') {
        total = price * days;
        desc  = `${days} 天 × ¥${price.toFixed(2)}/天`;
    } else if (cycle === 'week') {
        total = price * weeks;
        desc  = `${weeks} 周 × ¥${price.toFixed(2)}/周`;
    } else if (cycle === 'month') {
        total = price * months;
        desc  = `${months} 个月 × ¥${price.toFixed(2)}/月`;
    } else if (cycle === 'year') {
        total = price * years;
        desc  = `${years} 年 × ¥${price.toFixed(2)}/年`;
    }
    if (price > 0) {
        preview.innerHTML = `📊 租期 <strong>${days} 天</strong>　${desc} = <strong style="color:#1f6dad;font-size:16px;">¥${total.toFixed(2)}</strong>`;
    } else {
        preview.innerHTML = `📊 租期 <strong>${days} 天</strong>（请填写租金单价）`;
    }
}

function toggleDepositStatus() {
    const val = parseFloat(document.getElementById('deposit').value) || 0;
    document.getElementById('depositStatusGroup').style.display = val > 0 ? 'block' : 'none';
}


function addRow() {
    const tbody = document.getElementById('itemsBody');
    const first = tbody.rows[0];
    const tr = first.cloneNode(true);
    // 新行继承当前类型的 required 规则
    const t = document.getElementById('typeInput').value;
    tr.querySelector('input[name="item_spec[]"]').required = true;

    tr.querySelectorAll('input').forEach(inp => {
        if (inp.name.includes('qty')) inp.value = '1';
        else if (inp.type === 'number') inp.value = '0';
        else inp.value = '';
    });
    // 小计归零
    const sub = tr.querySelector('.subtotal-cell');
    if (sub) sub.textContent = '0.00';
    tbody.appendChild(tr);
    calcTotal();
}


function calcRow(input) {
    const tr  = input.closest('tr');
    const qty   = parseFloat(tr.querySelector('input[name="item_qty[]"]')?.value) || 0;
    const price = parseFloat(tr.querySelector('input[name="item_market[]"]')?.value) || 0;
    const sub   = tr.querySelector('.subtotal-cell');
    if (sub) sub.textContent = (qty * price).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('#itemsBody .subtotal-cell').forEach(td => {
        total += parseFloat(td.textContent) || 0;
    });
    const el = document.getElementById('totalAmount');
    if (el) el.textContent = '¥' + total.toFixed(2);
}

function delRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) btn.closest('tr').remove();
}

// 初始化
setType(currentType);
calcRent();
</script>
</body>
</html>
