<?php
/**
 * =====================================================
 * 文件名：payment_add_receivable.php
 * 版本：  1.1
 * 日期：  2026-03-17
 * 功能：  新增应收账款 / 为已有应收账款登记收款
 * 说明：  ?id=N → 为指定应收账款登记收款
 *         无 id  → 手动新建一条应收账款记录
 * =====================================================
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();


$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];

$conn = getDBConnection();

$payment_id = intval($_GET['id'] ?? 0);
$mode = $payment_id ? 'receive' : 'create'; // receive=登记收款, create=新建应收

$ar = null;
if ($mode === 'receive') {
    $stmt = $conn->prepare("
        SELECT ar.*, COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name
        FROM accounts_receivable ar
        LEFT JOIN customers c ON ar.customer_id = c.id
        WHERE ar.id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $ar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ar) {
        $conn->close();
        die('<div style="font-family:sans-serif;padding:40px;color:#f56565;">找不到应收账款记录（ID='.$payment_id.'）<br><a href="finance.php">返回财务管理</a></div>');
    }
}

// 客户列表（新建模式用）
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name ASC");

$conn->close();
?>
<?php $page_title = ($mode==='receive' ? '登记收款' : '新增应收账款') . ' - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
/* 主内容区：居中布局 */
.main-content{display:flex;flex-direction:column;align-items:center;}
.page-header{width:100%;max-width:720px;}
.page-subtitle{color:#718096;font-size:14px;}

/* 表单卡片：居中固定宽度 */
.form-card{width:100%;max-width:720px;}

/* AR 信息摘要 */
.ar-summary{background:#f0f4ff;border-radius:10px;padding:16px 20px;margin-bottom:24px;border-left:4px solid #1f6dad;display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;}
.as-row{display:flex;gap:8px;font-size:14px;}
.as-label{color:#718096;min-width:70px;}
.as-value{color:#1a202c;font-weight:500;}
.as-value.neg{color:#e53e3e;font-family:'Courier New',monospace;}
.as-value.pos{color:#135286;font-family:'Courier New',monospace;}

/* 表单项 */
.form-group label{display:block;font-size:13px;font-weight:600;color:#4a5568;margin-bottom:6px;}
.form-group label .required{color:#e53e3e;margin-left:2px;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.form-group textarea{resize:vertical;min-height:90px;}
.form-hint{font-size:12px;color:#a0aec0;margin-top:4px;}

/* 按钮 */
.btn-group-action{display:flex;gap:12px;margin-top:24px;}

/* 客户搜索框 */
#customerSearch{border-radius:8px 8px 0 0 !important;border-bottom:none !important;}
#customerSelect{border-radius:0 0 8px 8px !important;font-size:14px;min-height:120px;}
</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区 -->
<div class="main-content">
    <div class="page-header">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h1 class="page-title"><?php echo $mode==='receive' ? '💰 登记收款' : '📥 新增应收账款'; ?></h1>
                <p class="page-subtitle">
                    <?php echo $mode==='receive'
                        ? '为应收账款 '.$ar['bill_no'].' 登记一笔收款记录'
                        : '手动新建一条应收账款记录'; ?>
                </p>
            </div>
            <a href="finance.php" style="padding:8px 16px;background:white;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;">← 返回财务管理</a>
        </div>
    </div>

    <div class="form-card">

    <?php if ($mode === 'receive'): ?>
        <!-- ── 应收账款信息摘要 ── -->
        <div class="ar-summary">
            <div class="as-row"><span class="as-label">账单编号</span><span class="as-value"><?php echo htmlspecialchars($ar['bill_no']); ?></span></div>
            <div class="as-row"><span class="as-label">客户</span><span class="as-value"><?php echo htmlspecialchars($ar['company_name'] ?: $ar['contact_name'] ?: '-'); ?></span></div>
            <div class="as-row"><span class="as-label">应收金额</span><span class="as-value neg">¥<?php echo number_format($ar['total_amount'], 2); ?></span></div>
            <div class="as-row"><span class="as-label">已收金额</span><span class="as-value pos">¥<?php echo number_format($ar['paid_amount'], 2); ?></span></div>
            <div class="as-row"><span class="as-label">未收金额</span><span class="as-value neg">¥<?php echo number_format($ar['outstanding_amount'], 2); ?></span></div>
            <div class="as-row"><span class="as-label">账单状态</span><span class="as-value"><?php echo htmlspecialchars($ar['status']); ?></span></div>
        </div>

        <!-- 登记收款表单 -->
        <form action="payment_receive_save.php" method="POST">
            <input type="hidden" name="ar_id" value="<?php echo $ar['id']; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>收款金额 <span class="required">*</span></label>
                    <input type="number" name="amount" step="any" min="0.01"
                           max="<?php echo $ar['outstanding_amount']; ?>"
                           value="<?php echo $ar['outstanding_amount']; ?>" required>
                    <div class="form-hint">最多可收 ¥<?php echo number_format($ar['outstanding_amount'], 2); ?></div>
                </div>
                <div class="form-group">
                    <label>收款日期 <span class="required">*</span></label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>支付方式 <span class="required">*</span></label>
                <select name="payment_method" required>
                    <option value="现金">现金</option>
                    <option value="银行转账" selected>银行转账</option>
                    <option value="支付宝">支付宝</option>
                    <option value="微信">微信</option>
                    <option value="对公转账">对公转账</option>
                    <option value="月结">月结</option>
                </select>
            </div>

            <div class="form-group">
                <label>是否生成收据</label>
                <select name="generate_receipt">
                    <option value="1">是，自动生成收据</option>
                    <option value="0">否</option>
                </select>
            </div>

            <div class="form-group">
                <label>备注</label>
                <textarea name="notes" placeholder="收款备注（可选）"></textarea>
            </div>

            <div class="btn-group-action">
                <button type="submit" class="btn-primary">💾 确认收款</button>
                <a href="finance.php" class="btn-secondary">取消</a>
            </div>
        </form>

    <?php else: ?>
        <!-- 手动新建应收账款 -->
        <form action="payment_receivable_save.php" method="POST">

            <div class="form-group">
                <label>客户 <span class="required">*</span></label>
                <!-- 搜索过滤输入 -->
                <input type="text" id="customerSearch" placeholder="🔍 输入名称快速筛选..."
                       oninput="filterCustomers(this.value)"
                       style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px 8px 0 0;font-size:14px;border-bottom:none;">
                <select name="customer_id" id="customerSelect" size="5"
                        style="width:100%;padding:6px 0;border:1px solid #e2e8f0;border-radius:0 0 8px 8px;font-size:14px;min-height:120px;">
                    <?php while ($c = $customers->fetch_assoc()): ?>
                    <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['company_name'] ?: $c['contact_name']); ?>">
                        <?php echo htmlspecialchars($c['company_name'] ?: $c['contact_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <!-- 手动输入切换 -->
                <div style="margin-top:8px;">
                    <label style="font-size:13px;color:#1f6dad;cursor:pointer;font-weight:400;display:inline-flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="useManual" onchange="toggleManual(this)" style="width:14px;height:14px;cursor:pointer;flex-shrink:0;">
                        <span>客户不在列表中，手动输入</span>
                    </label>
                </div>
                <div id="manualNameWrap" style="display:none;margin-top:8px;">
                    <input type="text" name="manual_customer_name" id="manualName"
                           placeholder="输入客户名称"
                           style="width:100%;padding:10px 14px;border:1px solid #1f6dad;border-radius:8px;font-size:14px;">
                    <div class="form-hint">若客户库中无同名客户，将自动创建客户档案</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>账单金额 <span class="required">*</span></label>
                    <input type="number" name="total_amount" step="any" min="0.01" required>
                </div>
                <div class="form-group">
                    <label>账单日期 <span class="required">*</span></label>
                    <input type="date" name="bill_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>到期日期</label>
                <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                <div class="form-hint">留空则无到期限制</div>
            </div>

            <div class="form-group">
                <label>备注</label>
                <textarea name="notes" placeholder="账单备注（可选）"></textarea>
            </div>

            <div class="btn-group-action">
                <button type="submit" class="btn-primary">💾 创建应收账款</button>
                <a href="finance.php" class="btn-secondary">取消</a>
            </div>
        </form>
    <?php endif; ?>

    </div>
</div>

<script>
/* 客户搜索过滤 */
function filterCustomers(kw) {
    const sel = document.getElementById('customerSelect');
    const opts = sel.querySelectorAll('option');
    const lower = kw.toLowerCase();
    opts.forEach(o => {
        o.style.display = (!lower || o.dataset.name.toLowerCase().includes(lower)) ? '' : 'none';
    });
    // 自动选中第一个可见项
    const first = Array.from(opts).find(o => o.style.display !== 'none');
    if (first) sel.value = first.value;
}

/* 手动输入切换 */
function toggleManual(cb) {
    const wrap   = document.getElementById('manualNameWrap');
    const sel    = document.getElementById('customerSelect');
    const search = document.getElementById('customerSearch');
    const manual = document.getElementById('manualName');
    if (cb.checked) {
        wrap.style.display = 'block';
        sel.disabled = true;
        search.disabled = true;
        sel.style.opacity = '0.4';
        search.style.opacity = '0.4';
        manual.required = true;
        sel.required = false;
    } else {
        wrap.style.display = 'none';
        sel.disabled = false;
        search.disabled = false;
        sel.style.opacity = '1';
        search.style.opacity = '1';
        manual.required = false;
        manual.value = '';
        sel.required = true;
    }
}
</script>
</body>
</html>
