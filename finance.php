<?php
/**
 * =====================================================
 * 文件名：finance.php
 * 版本：  2.0
 * 日期：  2026-03-17
 * 功能：  财务管理主页面
 * 说明：  应收/应付账款管理、收付款记录、
 *         右侧抽屉详情面板（AJAX）、AP 编辑/删除/复制
 * =====================================================
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];

$conn = getDBConnection();

/* ============================================================
   AJAX 接口（顶部拦截，返回 JSON 后 exit）
   ============================================================ */
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// AJAX 请求统一关闭错误显示，防止 warning 污染 JSON
$ajax_actions = ['detail','delete_ar','delete_ap','copy_ap','edit_ap','edit_ar_note','list_deliveries_for_ar'];
if (in_array($action, $ajax_actions)) {
    ini_set('display_errors', 0);
    error_reporting(0);
}

if ($action === 'list_deliveries_for_ar') {
    header('Content-Type: application/json; charset=utf-8');
    $rows = $conn->query("
        SELECT d.id, d.delivery_no, d.customer_id, d.customer_name,
               d.delivery_date, d.total_amount, d.approved,
               (SELECT COUNT(*) FROM accounts_receivable ar
                WHERE ar.bill_no = CONCAT('AR-', DATE_FORMAT(d.delivery_date,'%Y%m%d'), '-', LPAD(d.id,4,'0'))
               ) as has_ar
        FROM deliveries d
        WHERE d.status NOT IN ('cancelled','failed') AND " . tenantWhere("d") . "
        ORDER BY d.delivery_date DESC
        LIMIT 100
    ");
    $list = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode(['success'=>true,'deliveries'=>$list], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

if ($action === 'detail') {
    header('Content-Type: application/json; charset=utf-8');
    $id   = intval($_GET['id'] ?? 0);
    $type = $_GET['type'] ?? '';

    if ($type === 'receivable') {
        $stmt = $conn->prepare("
            SELECT ar.*,
                   COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name, c.phone, c.address,
                   q.quote_no
            FROM accounts_receivable ar
            LEFT JOIN customers c ON ar.customer_id = c.id
            LEFT JOIN quotes    q ON ar.quote_id    = q.id
            WHERE ar.id = ?");
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'查询失败：'.$conn->error], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        if (!$bill) { echo json_encode(['success'=>false,'message'=>'账单不存在'], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }

        $pr = $conn->prepare("SELECT pr.*, u.nickname as operator FROM payment_records pr LEFT JOIN users u ON pr.operator_id = u.id WHERE pr.related_type='应收账款' AND pr.related_id=? ORDER BY pr.payment_date DESC");
        $pr->bind_param('i', $id);
        $pr->execute();
        $payments = $pr->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success'=>true,'bill'=>$bill,'payments'=>$payments,'type'=>'receivable'], JSON_UNESCAPED_UNICODE);

    } elseif ($type === 'payable') {
        $stmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'查询失败：'.$conn->error], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        if (!$bill) { echo json_encode(['success'=>false,'message'=>'账单不存在'], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }

        $pr = $conn->prepare("SELECT pr.*, u.nickname as operator FROM payment_records pr LEFT JOIN users u ON pr.operator_id = u.id WHERE pr.related_type='应付账款' AND pr.related_id=? ORDER BY pr.payment_date DESC");
        $pr->bind_param('i', $id);
        $pr->execute();
        $payments = $pr->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success'=>true,'bill'=>$bill,'payments'=>$payments,'type'=>'payable'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success'=>false,'message'=>'类型错误'], JSON_UNESCAPED_UNICODE);
    }
    $conn->close(); exit;
}

if ($action === 'delete_ar' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    $id  = intval($_POST['id'] ?? 0);
    // 修复：查询包含 paid_amount
    $del_uid = (int)$_SESSION['user_id'];
    $stmt_tmp = $conn->prepare("SELECT total_amount, paid_amount, outstanding_amount FROM accounts_receivable WHERE id=? AND user_id=?");
    $stmt_tmp->bind_param("ii",$id,$del_uid); $stmt_tmp->execute();
    $row = $stmt_tmp->get_result()->fetch_assoc(); $stmt_tmp->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'记录不存在'], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }
    if (floatval($row['paid_amount']) > 0) {
        echo json_encode(['success'=>false,'message'=>'已有收款记录（已收 ¥'.number_format($row['paid_amount'],2).'），无法删除'], JSON_UNESCAPED_UNICODE);
        $conn->close(); exit;
    }
    $stmt_tmp=$conn->prepare("DELETE FROM accounts_receivable WHERE id=? AND user_id=" . intval($_SESSION['user_id'])); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $stmt_tmp->close();
    echo json_encode(['success'=>true,'message'=>'已删除'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

if ($action === 'delete_ap' && $_SERVER['REQUEST_METHOD']==='POST') {
    // ── 删除应付（仅未付款） ──
    header('Content-Type: application/json; charset=utf-8');
    $id  = intval($_POST['id']);
    $stmt_tmp=$conn->prepare("SELECT status FROM accounts_payable WHERE id=? AND user_id=" . intval($_SESSION['user_id'])); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $row=$stmt_tmp->get_result()->fetch_assoc(); $stmt_tmp->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'记录不存在'], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }
    if ($row['status'] !== '未付款') {
        echo json_encode(['success'=>false,'message'=>'仅未付款账单可删除'], JSON_UNESCAPED_UNICODE);
        $conn->close(); exit;
    }
    $stmt_tmp=$conn->prepare("DELETE FROM accounts_payable WHERE id=? AND user_id=" . intval($_SESSION['user_id'])); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $stmt_tmp->close();
    echo json_encode(['success'=>true,'message'=>'已删除'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

if ($action === 'copy_ap' && $_SERVER['REQUEST_METHOD']==='POST') {
    // ── 复制应付（生成新草稿） ──
    header('Content-Type: application/json; charset=utf-8');
    $id  = intval($_POST['id']);
    $stmt_tmp=$conn->prepare("SELECT * FROM accounts_payable WHERE id=? AND user_id=" . intval($_SESSION['user_id'])); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $row=$stmt_tmp->get_result()->fetch_assoc(); $stmt_tmp->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'记录不存在'], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }
    $new_no = 'AP-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
    $today  = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO accounts_payable
        (user_id,bill_no,supplier_name,category,total_amount,paid_amount,outstanding_amount,bill_date,status,notes)
        VALUES (?,?,?,?,?,0,?,?,?,?)");
    $status = '未付款';
    $stmt->bind_param('isssdss' . 'ss',
        (int)$_SESSION['user_id'],
        $new_no, $row['supplier_name'], $row['category'],
        $row['total_amount'], $row['total_amount'], $today, $status, $row['notes']);
    $stmt->execute();
    $new_id = $conn->insert_id;
    echo json_encode(['success'=>true,'message'=>'已复制，新账单号：'.$new_no,'new_id'=>$new_id], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

if ($action === 'edit_ap' && $_SERVER['REQUEST_METHOD']==='POST') {
    // ── 编辑应付（仅未付款可改所有字段，已部分付款只改备注/到期日）
    header('Content-Type: application/json; charset=utf-8');
    $id = intval($_POST['id']);
    $stmt_tmp=$conn->prepare("SELECT status FROM accounts_payable WHERE id=? AND user_id=" . intval($_SESSION['user_id'])); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $row=$stmt_tmp->get_result()->fetch_assoc(); $stmt_tmp->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'记录不存在'], JSON_UNESCAPED_UNICODE); $conn->close(); exit; }

    $notes    = $conn->real_escape_string($_POST['notes']    ?? '');
    $due_date = $conn->real_escape_string($_POST['due_date'] ?? '');

    if ($row['status'] === '未付款') {
        $supplier = $conn->real_escape_string($_POST['supplier_name'] ?? '');
        $category = $conn->real_escape_string($_POST['category']      ?? '');
        $amount   = floatval($_POST['total_amount'] ?? 0);
        $conn->query("UPDATE accounts_payable SET
            supplier_name='$supplier', category='$category',
            total_amount=$amount, outstanding_amount=$amount,
            due_date=" . ($due_date ? "'$due_date'" : 'NULL') . ",
            notes='$notes'
            WHERE id=$id");
    } else {
        // 部分付款 / 已逾期：只改备注和到期日
        $conn->query("UPDATE accounts_payable SET
            due_date=" . ($due_date ? "'$due_date'" : 'NULL') . ",
            notes='$notes'
            WHERE id=$id");
    }
    echo json_encode(['success'=>true,'message'=>'保存成功'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

if ($action === 'edit_ar_note' && $_SERVER['REQUEST_METHOD']==='POST') {
    // ── 应收：只允许改备注和到期日 ──
    header('Content-Type: application/json; charset=utf-8');
    $id       = intval($_POST['id']);
    $notes    = $conn->real_escape_string($_POST['notes']    ?? '');
    $due_date = $conn->real_escape_string($_POST['due_date'] ?? '');
    $conn->query("UPDATE accounts_receivable SET
        due_date=" . ($due_date ? "'$due_date'" : 'NULL') . ", notes='$notes'
        WHERE id=$id");
    echo json_encode(['success'=>true,'message'=>'保存成功'], JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

/* ============================================================
   页面数据查询
   ============================================================ */
$receivable_stats = $conn->query("
    SELECT COUNT(*) as total_count, SUM(total_amount) as total_amount,
           SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount
    FROM accounts_receivable WHERE status IN ('未收款','部分收款','已逾期') AND " . tenantWhere() . "
")->fetch_assoc();

$payable_stats = $conn->query("
    SELECT COUNT(*) as total_count, SUM(total_amount) as total_amount,
           SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount
    FROM accounts_payable WHERE status IN ('未付款','部分付款','已逾期') AND " . tenantWhere() . "
")->fetch_assoc();

$ar_kw     = trim($_GET['ar_kw']  ?? '');
$ap_kw     = trim($_GET['ap_kw']  ?? '');
$rec_kw    = trim($_GET['rec_kw'] ?? '');

$ar_kw_sql = $ar_kw  ? "AND (ar.bill_no LIKE '%" . $conn->real_escape_string($ar_kw)  . "%' OR IFNULL(COALESCE(c.short_name, c.company_name),'') LIKE '%" . $conn->real_escape_string($ar_kw)  . "%')" : '';
$ap_kw_sql = $ap_kw  ? "AND (bill_no LIKE '%" . $conn->real_escape_string($ap_kw)  . "%' OR supplier_name LIKE '%" . $conn->real_escape_string($ap_kw)  . "%' OR category LIKE '%" . $conn->real_escape_string($ap_kw)  . "%')" : '';
$rec_kw_sql= $rec_kw ? "AND (pr.record_no LIKE '%" . $conn->real_escape_string($rec_kw) . "%' OR pr.payment_method LIKE '%" . $conn->real_escape_string($rec_kw) . "%' OR pr.notes LIKE '%" . $conn->real_escape_string($rec_kw) . "%')" : '';

$receivables = $conn->query("
    SELECT ar.*, COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name,
        (SELECT r.id FROM receipts r
         WHERE r.related_type='receivable' AND r.related_id=ar.id
         ORDER BY r.created_at DESC LIMIT 1) as receipt_payment_id
    FROM accounts_receivable ar
    LEFT JOIN customers c ON ar.customer_id = c.id
    WHERE " . tenantWhere("ar") . " $ar_kw_sql
    ORDER BY ar.id DESC LIMIT 200
");

$payables = $conn->query("
    SELECT ap.*, COALESCE(s.short_name, s.company_name, ap.supplier_name) AS display_supplier
    FROM accounts_payable ap
    LEFT JOIN suppliers s ON s.id=ap.supplier_id AND s.user_id=ap.user_id
    WHERE " . tenantWhere("ap") . " $ap_kw_sql ORDER BY ap.id DESC LIMIT 200
");

$recent_payments = $conn->query("
    SELECT pr.*, u.nickname as operator_name,
        CASE pr.related_type
          WHEN '应收账款' THEN COALESCE(
            (SELECT COALESCE(c.short_name, c.company_name)
             FROM accounts_receivable ar LEFT JOIN customers c ON c.id=ar.customer_id
             WHERE ar.id=pr.related_id LIMIT 1), '-')
          WHEN '应付账款' THEN COALESCE(
            (SELECT COALESCE(s.short_name, s.company_name, ap.supplier_name)
             FROM accounts_payable ap LEFT JOIN suppliers s ON s.id=ap.supplier_id AND s.user_id=ap.user_id
             WHERE ap.id=pr.related_id LIMIT 1), '-')
          ELSE '-'
        END AS source_name
    FROM payment_records pr
    LEFT JOIN users u ON pr.operator_id = u.id
    WHERE " . tenantWhere("pr") . " $rec_kw_sql
    ORDER BY pr.payment_date DESC, pr.created_at DESC LIMIT 200
");

// ── 收据列表（整合自 receipts.php）──
$receipt_kw     = trim($_GET['receipt_kw'] ?? '');
$receipt_kw_sql = $receipt_kw ? '%' . $conn->real_escape_string($receipt_kw) . '%' : null;

$receipt_stats = $conn->query("
    SELECT COUNT(*) as total_count, SUM(total_amount) as total_amount FROM receipts WHERE " . tenantWhere() . "
")->fetch_assoc();

if ($receipt_kw_sql) {
    $receipts = $conn->query("
        SELECT r.*, COALESCE(c.short_name, c.company_name, c.contact_name) AS customer_name, u.nickname AS creator_name
        FROM receipts r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.receipt_no LIKE '$receipt_kw_sql'
           OR IFNULL(COALESCE(c.short_name, c.company_name),'') LIKE '$receipt_kw_sql'
           OR IFNULL(c.contact_name,'') LIKE '$receipt_kw_sql'
           OR IFNULL(r.walkin_name,'') LIKE '$receipt_kw_sql'
        ORDER BY r.created_at DESC LIMIT 100
    ");
} else {
    $receipts = $conn->query("
        SELECT r.*, COALESCE(c.short_name, c.company_name, c.contact_name) AS customer_name, u.nickname AS creator_name
        FROM receipts r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE " . tenantWhere("r") . "
        ORDER BY r.created_at DESC LIMIT 100
    ");
}

// ── 利润报表数据 ──────────────────────────────────────────
$p_view  = in_array($_GET['profit_view'] ?? '', ['order','day','week','month','year'])
           ? $_GET['profit_view'] : 'month';
$p_year  = intval($_GET['profit_year']  ?? date('Y'));
$p_month = intval($_GET['profit_month'] ?? 0);

$p_yr_res   = $conn->query("SELECT DISTINCT YEAR(delivery_date) y FROM deliveries WHERE user_id=$user_id AND approved=1 ORDER BY y DESC");
$p_years    = array_column($p_yr_res->fetch_all(MYSQLI_ASSOC), 'y');
if (!in_array($p_year, $p_years)) array_unshift($p_years, $p_year);
rsort($p_years);

$p_conds = ["d.approved=1", "d.user_id=$user_id"];
$p_params = []; $p_types = '';
if ($p_year)    { $p_conds[] = "YEAR(d.delivery_date)=?";  $p_params[] = $p_year;  $p_types .= 'i'; }
if ($p_month>0) { $p_conds[] = "MONTH(d.delivery_date)=?"; $p_params[] = $p_month; $p_types .= 'i'; }
$p_where = implode(' AND ', $p_conds);

$p_sql = "SELECT d.id, d.delivery_no, d.delivery_date, d.quote_id,
                 COALESCE((SELECT COALESCE(c.short_name, c.company_name) FROM customers c WHERE c.id=d.customer_id LIMIT 1), d.customer_name) AS customer_name,
                 d.goods_amount AS revenue,
                 ROUND(COALESCE(SUM(qi.cost_subtotal),0)*COALESCE(q.unit_qty,1),2) AS cost
          FROM deliveries d
          LEFT JOIN quotes      q  ON q.id=d.quote_id
          LEFT JOIN quote_items qi ON qi.quote_id=d.quote_id
          WHERE $p_where GROUP BY d.id ORDER BY d.delivery_date DESC, d.id DESC";
if ($p_types) {
    $p_stmt = $conn->prepare($p_sql); $p_stmt->bind_param($p_types,...$p_params); $p_stmt->execute();
    $p_rows = $p_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $p_stmt->close();
} else {
    $p_rows = $conn->query($p_sql)->fetch_all(MYSQLI_ASSOC);
}
foreach ($p_rows as &$pr) {
    $pr['has_cost'] = $pr['quote_id'] && $pr['cost'] > 0;
    $pr['profit']   = $pr['has_cost'] ? round($pr['revenue'] - $pr['cost'], 2) : null;
    $pr['margin']   = ($pr['has_cost'] && $pr['revenue'] > 0) ? round($pr['profit']/$pr['revenue']*100,1) : null;
}
unset($pr);
$p_total_rev    = array_sum(array_column($p_rows, 'revenue'));
$p_cost_rows    = array_filter($p_rows, fn($r) => $r['has_cost']);
$p_total_cost   = array_sum(array_column($p_cost_rows, 'cost'));
$p_total_profit = $p_total_rev - $p_total_cost;
$p_total_margin = $p_total_rev > 0 ? round($p_total_profit/$p_total_rev*100,1) : 0;
$p_no_cost_cnt  = count(array_filter($p_rows, fn($r) => !$r['has_cost']));

$p_periods = [];
foreach ($p_rows as $r) {
    $ts=$ts=strtotime($r['delivery_date']); $dow=(int)date('N',$ts);
    switch ($p_view) {
        case 'day':   $key=$r['delivery_date']; $label=date('Y-m-d',$ts).'（'.['','一','二','三','四','五','六','日'][$dow].'）'; break;
        case 'week':  $m0=$ts-($dow-1)*86400; $key=date('oW',$ts); $label=date('Y',$ts).'年第'.date('W',$ts).'周（'.date('m/d',$m0).'~'.date('m/d',$m0+6*86400).'）'; break;
        case 'month': $key=date('Ym',$ts); $label=date('Y年n月',$ts); break;
        case 'year':  $key=date('Y',$ts);  $label=date('Y年',$ts); break;
        default: continue 2;
    }
    if (!isset($p_periods[$key])) $p_periods[$key]=['label'=>$label,'count'=>0,'revenue'=>0,'cost'=>0,'cost_count'=>0];
    $p_periods[$key]['count']++;
    $p_periods[$key]['revenue']=round($p_periods[$key]['revenue']+$r['revenue'],2);
    if ($r['has_cost']) { $p_periods[$key]['cost']=round($p_periods[$key]['cost']+$r['cost'],2); $p_periods[$key]['cost_count']++; }
}
foreach ($p_periods as &$pp) { $pp['profit']=round($pp['revenue']-$pp['cost'],2); $pp['margin']=$pp['revenue']>0?round($pp['profit']/$pp['revenue']*100,1):0; } unset($pp);
krsort($p_periods);

$conn->close();
?>
<?php $page_title = '财务管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

.main-content.drawer-open { margin-right: 420px; }

.page-subtitle { color: #718096; font-size: 14px; }

.tabs {
    background: white; border-radius: 12px; padding: 8px;
    margin-bottom: 24px; display: flex; gap: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.tab-btn {
    flex: 1; padding: 12px 24px; background: transparent; border: none;
    border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 500;
    color: #718096; transition: all 0.2s;
}
.tab-btn:hover  { background: #f7fafc; }
.tab-btn.active { background: linear-gradient(135deg,#1f6dad 0%,#135286 100%); color: white; }
.tab-content        { display: none; }
.tab-content.active { display: block; }

.stats-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }
.stat-box { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.stat-box-label { font-size: 13px; color: #718096; margin-bottom: 8px; }
.stat-box-value { font-size: 24px; font-weight: 700; color: #1a202c; }
.positive { color: #1f6dad !important; }
.negative { color: #f56565 !important; }

.card-title  { font-size: 18px; font-weight: 600; color: #1a202c; }

.amount { font-weight: 600; font-family: 'Courier New', monospace; }
.overdue { color: #f56565; font-weight: 600; }

.badge.margin-high { background: #d1fae5; color: #065f46; }
.badge.margin-mid  { background: #fef3c7; color: #92400e; }
.badge.margin-low  { background: #fee2e2; color: #991b1b; }
.badge.margin-na   { background: #f1f5f9; color: #94a3b8; }
.profit-views { display:flex; gap:4px; }
.profit-views a { padding:5px 14px; border-radius:6px; font-size:13px; font-weight:500; color:#718096; text-decoration:none; border:1px solid #e2e8f0; background:#fff; transition:all .15s; }
.profit-views a.on { background:linear-gradient(135deg,#1f6dad,#135286); color:#fff; border-color:transparent; }

.btn-group { display: flex; gap: 6px; flex-wrap: wrap; }

/* ============================================================
   详情抽屉（右侧滑入面板）
   ============================================================ */
.drawer-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.3);
    z-index: 299; display: none;
}
.drawer-overlay.open { display: block; }

.detail-drawer {
    position: fixed; top: 0; right: -420px; width: 420px; height: 100vh;
    background: white; box-shadow: -4px 0 24px rgba(0,0,0,0.12);
    z-index: 300; transition: right 0.3s ease;
    display: flex; flex-direction: column;
}
.detail-drawer.open { right: 0; }

.drawer-header {
    padding: 20px 24px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
    background: linear-gradient(135deg,#1f6dad 0%,#135286 100%);
}
.drawer-title { font-size: 16px; font-weight: 600; color: white; }
.drawer-close {
    width: 32px; height: 32px; background: rgba(255,255,255,0.2); border: none;
    border-radius: 6px; color: white; font-size: 18px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.drawer-close:hover { background: rgba(255,255,255,0.35); }

.drawer-

/* 抽屉内信息块 */
.dw-section { margin-bottom: 20px; }
.dw-section-title {
    font-size: 11px; font-weight: 600; color: #a0aec0;
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;
    padding-bottom: 6px; border-bottom: 1px solid #f0f0f0;
}
.dw-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; font-size: 14px; }
.dw-label { color: #718096; flex-shrink: 0; width: 90px; }
.dw-value { color: #1a202c; font-weight: 500; text-align: right; flex: 1; }
.dw-value.amount { font-family: 'Courier New',monospace; }

/* 金额大显示 */
.dw-amount-box {
    background: #f7fafc; border-radius: 10px; padding: 16px;
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;
    margin-bottom: 20px; text-align: center;
}
.dw-amount-item .av { font-size: 18px; font-weight: 700; font-family: 'Courier New',monospace; }
.dw-amount-item .al { font-size: 11px; color: #718096; margin-top: 4px; }

/* 收付款历史 */
.payment-item {
    background: #f7fafc; border-radius: 8px; padding: 12px;
    margin-bottom: 8px; border-left: 3px solid #1f6dad;
}
.payment-item .pi-top { display: flex; justify-content: space-between; margin-bottom: 4px; }
.payment-item .pi-amount { font-size: 15px; font-weight: 600; color: #1f6dad; font-family: 'Courier New',monospace; }
.payment-item .pi-date   { font-size: 12px; color: #718096; }
.payment-item .pi-method { font-size: 12px; color: #4a5568; }
.payment-item .pi-note   { font-size: 12px; color: #718096; margin-top: 4px; }

.no-payment { text-align: center; padding: 24px; color: #a0aec0; font-size: 14px; }

/* 抽屉底部操作按钮 */
.drawer-footer {
    padding: 16px 24px; border-top: 1px solid #e2e8f0; flex-shrink: 0;
    display: flex; gap: 8px; flex-wrap: wrap;
}
.dw-btn {
    padding: 8px 14px; border: none; border-radius: 6px; font-size: 13px;
    font-weight: 500; cursor: pointer; transition: all 0.2s;
}
.dw-btn-primary  { background: linear-gradient(135deg,#1f6dad 0%,#135286 100%); color: white; }
.dw-btn-primary:hover  { transform: translateY(-1px); }
.dw-btn-amber    { background: #d97706; color: white; }
.dw-btn-amber:hover    { background: #b45309; }
.dw-btn-red      { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.dw-btn-red:hover      { background: #fecaca; }
.dw-btn-gray     { background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; }
.dw-btn-gray:hover     { background: #edf2f7; }

.edit-form { display: none; background: #f7fafc; border-radius: 8px; padding: 16px; margin-top: 12px; }
.edit-form.show { display: block; }
.ef-row { margin-bottom: 12px; }
.ef-row label { display: block; font-size: 12px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.ef-row input, .ef-row textarea, .ef-row select {
    width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 13px; font-family: inherit;
}
.ef-row input:focus, .ef-row textarea:focus { outline: none; border-color: #1f6dad; }
.ef-actions { display: flex; gap: 8px; }
</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 遮罩 -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>

<!-- 从送货单生成应收 弹窗 -->
<div id="genModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:400;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:14px;width:700px;max-width:95vw;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,0.2);">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#135286,#047857);border-radius:14px 14px 0 0;">
            <span style="font-size:16px;font-weight:600;color:white;">📦 从送货单生成应收账款</span>
            <button onclick="closeGenModal()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:30px;height:30px;border-radius:6px;font-size:18px;cursor:pointer;">×</button>
        </div>
        <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;">
            <input type="text" id="genSearch" placeholder="🔍 搜索客户名称或送货单号..."
                   oninput="filterGenList()"
                   style="width:100%;padding:9px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
        </div>
        <div style="flex:1;overflow-y:auto;padding:16px 24px;" id="genList">
            <div style="text-align:center;padding:30px;color:#a0aec0;">加载中...</div>
        </div>
        <div style="padding:14px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
            <button onclick="closeGenModal()" class="btn-sm">取消</button>
            <button onclick="batchGenAR()" class="btn-primary" style="padding:8px 20px;">✅ 生成选中的应收</button>
        </div>
    </div>
</div>

<!-- 详情抽屉 -->
<div class="detail-drawer" id="detailDrawer">
    <div class="drawer-header">
        <span class="drawer-title" id="drawerTitle">账单详情</span>
        <button class="drawer-close" onclick="closeDrawer()">×</button>
    </div>
    <div class="drawer-body" id="drawerBody">
        <div style="text-align:center;padding:40px;color:#a0aec0;">加载中...</div>
    </div>
    <div class="drawer-footer" id="drawerFooter"></div>
</div>

<!-- 主内容区 -->
<main class="main-content" id="mainContent">
    <div class="page-header">
        <h1 class="page-title">💰 财务管理</h1>
        <p class="page-subtitle">管理应收应付账款，查看收付款记录，财务数据统计</p>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('receivable',this)">📥 应收账款</button>
        <button class="tab-btn" onclick="switchTab('payable',this)">📤 应付账款</button>
        <button class="tab-btn" onclick="switchTab('records',this)">📋 收付款记录</button>
        <button class="tab-btn" id="tabReceipts" onclick="switchTab('receipts',this)">🧾 收据管理</button>
        <button class="tab-btn" id="tabProfit"   onclick="switchTab('profit',this)">📈 利润报表</button>
    </div>

    <!-- ── 应收账款 ── -->
    <div id="receivable" class="tab-content active">
        <div class="stats-row">
            <div class="stat-box"><div class="stat-box-label">待收账单</div><div class="stat-box-value"><?php echo $receivable_stats['total_count']??0; ?></div></div>
            <div class="stat-box"><div class="stat-box-label">应收总额</div><div class="stat-box-value positive">¥<?php echo number_format($receivable_stats['total_amount']??0,2); ?></div></div>
            <div class="stat-box"><div class="stat-box-label">已收金额</div><div class="stat-box-value">¥<?php echo number_format($receivable_stats['paid_amount']??0,2); ?></div></div>
            <div class="stat-box"><div class="stat-box-label">未收金额</div><div class="stat-box-value negative">¥<?php echo number_format($receivable_stats['outstanding_amount']??0,2); ?></div></div>
        </div>
        <div class="content-card">
            <div class="card-header" style="justify-content:flex-start;gap:16px;">
                <h2 class="card-title">应收账款列表</h2>
                <form method="GET" style="display:flex;align-items:center;gap:8px;">
                    <input type="hidden" name="tab" value="receivable">
                    <input type="text" name="ar_kw" placeholder="搜索账单编号、客户名称..."
                           value="<?php echo htmlspecialchars($ar_kw); ?>"
                           style="padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;min-width:220px;">
                    <button type="submit" class="btn-sm btn-secondary">🔍 搜索</button>
                    <?php if ($ar_kw): ?><a href="finance.php?tab=receivable" class="btn-sm btn-secondary">清除</a><?php endif; ?>
                </form>
                <div style="display:flex;gap:8px;margin-left:8px;">
                    <button class="btn-primary" onclick="showGenFromDelivery()">🚚 从送货单生成</button>
                    <button class="btn-primary" onclick="window.location.href='payment_add_receivable.php'">+ 手动新增</button>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>账单编号</th><th>客户名称</th><th>联系人</th><th>账单日期</th>
                        <th>应收金额</th><th>已收金额</th><th>未收金额</th><th>到期日期</th><th>状态</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($receivables->num_rows > 0): ?>
                    <?php while ($row = $receivables->fetch_assoc()):
                        $is_overdue = $row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] != '已收款';
                        $status_class = ['未收款'=>'warning','部分收款'=>'info','已收款'=>'success','已逾期'=>'danger'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($row['bill_no']); ?></strong>
                            <?php if (strpos($row['bill_no'],'AR-RPR-')===0): ?>
                            <span style="display:inline-block;margin-left:6px;padding:1px 7px;background:#fef3c7;color:#92400e;border-radius:10px;font-size:11px;font-weight:600;">🔧 维修</span>
                            <?php elseif (strpos($row['bill_no'],'AR-')===0): ?>
                            <span style="display:inline-block;margin-left:6px;padding:1px 7px;background:#dbeafe;color:#135286;border-radius:10px;font-size:11px;font-weight:600;">🚚 送货</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['company_name']??''); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_name']??'-'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['bill_date'])); ?></td>
                        <td class="amount">¥<?php echo number_format($row['total_amount'],2); ?></td>
                        <td class="amount positive">¥<?php echo number_format($row['paid_amount'],2); ?></td>
                        <td class="amount negative">¥<?php echo number_format($row['outstanding_amount'],2); ?></td>
                        <td><?php if($is_overdue): ?><span class="overdue">⚠️ <?php echo date('Y-m-d',strtotime($row['due_date'])); ?></span>
                            <?php else: echo $row['due_date'] ? date('Y-m-d',strtotime($row['due_date'])) : '-'; endif; ?></td>
                        <td><span class="badge <?php echo $status_class[$row['status']]??'info'; ?>"><?php echo $row['status']; ?></span></td>
                        <td>
                            <div class="btn-group">
                                <?php if ($row['outstanding_amount'] > 0): ?>
                                    <button class="btn-sm" onclick="window.location.href='payment_receive.php?id=<?php echo $row['id']; ?>'">💰 收款</button>
                                <?php endif; ?>
                                <?php if ($row['receipt_payment_id']): ?>
                                    <button class="btn-sm" style="background:#fef3c7;color:#92400e;" onclick="window.open('receipt_view.php?id=<?php echo $row['receipt_payment_id']; ?>','_blank')">🧾 收据</button>
                                <?php endif; ?>
                                <button class="btn-sm" onclick="viewDetail(<?php echo $row['id']; ?>,'receivable')">📋 详情</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center;color:#a0aec0;padding:40px;">暂无应收账款数据</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── 应付账款 ── -->
    <div id="payable" class="tab-content">
        <div class="stats-row">
            <div class="stat-box"><div class="stat-box-label">待付账单</div><div class="stat-box-value"><?php echo $payable_stats['total_count']??0; ?></div></div>
            <div class="stat-box"><div class="stat-box-label">应付总额</div><div class="stat-box-value negative">¥<?php echo number_format($payable_stats['total_amount']??0,2); ?></div></div>
            <div class="stat-box"><div class="stat-box-label">已付金额</div><div class="stat-box-value">¥<?php echo number_format($payable_stats['paid_amount']??0,2); ?></div></div>
            <div class="stat-box"><div class="stat-box-label">未付金额</div><div class="stat-box-value negative">¥<?php echo number_format($payable_stats['outstanding_amount']??0,2); ?></div></div>
        </div>
        <div class="content-card">
            <div class="card-header" style="justify-content:flex-start;gap:16px;">
                <h2 class="card-title">应付账款列表</h2>
                <form method="GET" style="display:flex;align-items:center;gap:8px;">
                    <input type="hidden" name="tab" value="payable">
                    <input type="text" name="ap_kw" placeholder="搜索账单编号、供应商、费用类别..."
                           value="<?php echo htmlspecialchars($ap_kw); ?>"
                           style="padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;min-width:220px;">
                    <button type="submit" class="btn-sm btn-secondary">🔍 搜索</button>
                    <?php if ($ap_kw): ?><a href="finance.php?tab=payable" class="btn-sm btn-secondary">清除</a><?php endif; ?>
                </form>
                <button class="btn-primary" style="margin-left:8px;" onclick="window.location.href='payment_add_payable.php'">+ 新增应付</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>账单编号</th><th>供应商名称</th><th>费用类别</th><th>账单日期</th>
                        <th>应付金额</th><th>已付金额</th><th>未付金额</th><th>到期日期</th><th>状态</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($payables->num_rows > 0): ?>
                    <?php while ($row = $payables->fetch_assoc()):
                        $is_overdue = $row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] != '已付款';
                        $status_class = ['未付款'=>'danger','部分付款'=>'warning','已付款'=>'success','已逾期'=>'danger'];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['bill_no']); ?></strong></td>
                        <td title="<?php echo htmlspecialchars($row['supplier_name']); ?>"><?php echo htmlspecialchars($row['display_supplier'] ?: $row['supplier_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category']??'-'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['bill_date'])); ?></td>
                        <td class="amount">¥<?php echo number_format($row['total_amount'],2); ?></td>
                        <td class="amount positive">¥<?php echo number_format($row['paid_amount'],2); ?></td>
                        <td class="amount negative">¥<?php echo number_format($row['outstanding_amount'],2); ?></td>
                        <td><?php if($is_overdue): ?><span class="overdue">⚠️ <?php echo date('Y-m-d',strtotime($row['due_date'])); ?></span>
                            <?php else: echo $row['due_date'] ? date('Y-m-d',strtotime($row['due_date'])) : '-'; endif; ?></td>
                        <td><span class="badge <?php echo $status_class[$row['status']]??'info'; ?>"><?php echo $row['status']; ?></span></td>
                        <td>
                            <div class="btn-group">
                                <?php if ($row['outstanding_amount'] > 0): ?>
                                    <button class="btn-sm" onclick="window.location.href='payment_pay.php?id=<?php echo $row['id']; ?>'">💸 付款</button>
                                <?php endif; ?>
                                <button class="btn-sm" onclick="viewDetail(<?php echo $row['id']; ?>,'payable')">📋 详情</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center;color:#a0aec0;padding:40px;">暂无应付账款数据</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── 收付款记录 ── -->
    <div id="records" class="tab-content">
        <div class="content-card">
            <div class="card-header" style="justify-content:flex-start;gap:16px;">
                <h2 class="card-title">收付款记录</h2>
                <form method="GET" style="display:flex;align-items:center;gap:8px;">
                    <input type="hidden" name="tab" value="records">
                    <input type="text" name="rec_kw" placeholder="搜索记录编号、付款方式、备注..."
                           value="<?php echo htmlspecialchars($rec_kw); ?>"
                           style="padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;min-width:220px;">
                    <button type="submit" class="btn-sm btn-secondary">🔍 搜索</button>
                    <?php if ($rec_kw): ?><a href="finance.php?tab=records" class="btn-sm btn-secondary">清除</a><?php endif; ?>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>记录编号</th><th>类型</th><th>来源</th><th>金额</th>
                        <th>付款方式</th><th>日期</th><th>操作员</th><th>备注</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recent_payments->num_rows > 0): ?>
                    <?php while ($row = $recent_payments->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['record_no']); ?></strong></td>
                        <td><span class="badge <?php echo in_array($row['payment_type'],['收款','income'])?'success':'warning'; ?>"><?php echo in_array($row['payment_type'],['收款','income']) ? '收款' : '付款'; ?></span></td>
                        <td><?php echo htmlspecialchars($row['source_name'] ?? '-'); ?></td>
                        <td class="amount <?php echo in_array($row['payment_type'],['收款','income'])?'positive':'negative'; ?>">
                            <?php echo in_array($row['payment_type'],['收款','income'])?'+':'-'; ?>¥<?php echo number_format($row['amount'],2); ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['payment_method']??'-'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['operator_name']??'-'); ?></td>
                        <td><?php echo htmlspecialchars($row['notes']??'-'); ?></td>
                        <td>
                            <?php if (!empty($row['generate_receipt'])): ?>
                                <button class="btn-sm" onclick="window.open('receipt_view.php?id=<?php echo $row['id']; ?>','_blank')">🧾 收据</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align:center;color:#a0aec0;padding:40px;">暂无收付款记录</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- end records tab -->

    <!-- ── 收据管理（整合自 receipts.php）── -->
    <div id="receipts" class="tab-content">
        <!-- 统计 -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-label">收据总数</div>
                <div class="stat-box-value"><?php echo $receipt_stats['total_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">收据总金额</div>
                <div class="stat-box-value positive">¥<?php echo number_format($receipt_stats['total_amount'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- 搜索栏 -->
        <form method="GET" action="finance.php" style="margin-bottom:24px;">
            <input type="hidden" name="tab" value="receipts">
            <div class="filter-bar" style="background:white;padding:16px 20px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);display:flex;gap:12px;align-items:center;">
                <input type="text" name="receipt_kw"
                       placeholder="🔍 搜索收据号 / 客户名称 / 散客名称..."
                       value="<?php echo htmlspecialchars($receipt_kw); ?>"
                       style="padding:10px 16px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;min-width:280px;">
                <button type="submit" class="btn-primary" style="padding:10px 20px;font-size:14px;">搜索</button>
                <?php if ($receipt_kw): ?>
                    <a href="finance.php?tab=receipts" class="btn-sm">清除</a>
                <?php endif; ?>
                <a href="receipt_create.php" class="btn-primary" style="padding:10px 20px;font-size:14px;text-decoration:none;">➕ 新建收据</a>
            </div>
        </form>

        <!-- 收据列表 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">收据列表</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>收据号</th><th>客户/散客</th><th>金额</th>
                        <th>支付方式</th><th>来源</th><th>创建人</th><th>创建时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($receipts && $receipts->num_rows > 0): ?>
                    <?php while ($rpt = $receipts->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($rpt['receipt_no']); ?></strong></td>
                        <td><?php echo $rpt['customer_name'] ?: htmlspecialchars($rpt['walkin_name'] ?: '散客'); ?></td>
                        <td class="amount positive">¥<?php echo number_format((float)$rpt['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($rpt['payment_method'] ?: '-'); ?></td>
                        <td>
                            <?php if ($rpt['related_type'] === 'receivable'): ?>
                                <span class="badge success">应收生成</span>
                            <?php else: ?>
                                <span class="badge info">独立创建</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($rpt['creator_name'] ?: '-'); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($rpt['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="receipt_view.php?id=<?php echo (int)$rpt['id']; ?>" class="btn-sm" style="background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;text-decoration:none;">查看</a>
                                <a href="receipt_edit.php?id=<?php echo (int)$rpt['id']; ?>" class="btn-sm" style="text-decoration:none;">编辑</a>
                                <a href="receipt_delete.php?id=<?php echo (int)$rpt['id']; ?>"
                                   onclick="return confirm('确定要删除这张收据吗？此操作不可恢复！')"
                                   class="btn-sm" style="color:#dc2626;text-decoration:none;">删除</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;color:#a0aec0;padding:40px;">暂无收据记录</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- end receipts tab -->

    <!-- ── 利润报表 ── -->
    <div id="profit" class="tab-content">
        <!-- 筛选栏 -->
        <form method="GET" action="finance.php" style="margin-bottom:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="profit">
            <div class="profit-views">
                <?php foreach(['order'=>'按单','day'=>'按日','week'=>'按周','month'=>'按月','year'=>'按年'] as $v=>$vl): ?>
                <a href="finance.php?tab=profit&profit_view=<?=$v?>&profit_year=<?=$p_year?>&profit_month=<?=$p_month?>"
                   class="<?=$p_view===$v?'on':''?>"><?=$vl?></a>
                <?php endforeach; ?>
            </div>
            <select name="profit_year" onchange="this.form.submit()" style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;">
                <?php foreach ($p_years as $y): ?>
                <option value="<?=$y?>" <?=$y==$p_year?'selected':''?>><?=$y?>年</option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="profit_view" value="<?=htmlspecialchars($p_view)?>">
            <?php if (in_array($p_view, ['order','day','week'])): ?>
            <select name="profit_month" onchange="this.form.submit()" style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;">
                <option value="0" <?=$p_month==0?'selected':''?>>全年</option>
                <?php foreach([1=>'一月',2=>'二月',3=>'三月',4=>'四月',5=>'五月',6=>'六月',7=>'七月',8=>'八月',9=>'九月',10=>'十月',11=>'十一月',12=>'十二月'] as $mn=>$ml): ?>
                <option value="<?=$mn?>" <?=$mn==$p_month?'selected':''?>><?=$ml?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="profit_month" value="0">
            <?php endif; ?>
        </form>

        <?php if ($p_no_cost_cnt > 0): ?>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:10px 16px;font-size:13px;color:#92400e;margin-bottom:16px;">
            ⚠️ 本期 <strong><?=$p_no_cost_cnt?></strong> 张送货单无报价单关联或成本为零，利润数据不含这些单据，营业额仍完整统计。
        </div>
        <?php endif; ?>

        <!-- 汇总卡片 -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-label">总营业额</div>
                <div class="stat-box-value positive">¥<?=number_format($p_total_rev,2)?></div>
                <div style="font-size:12px;color:#a0aec0;margin-top:4px;"><?=count($p_rows)?> 张审核送货单</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">总成本</div>
                <div class="stat-box-value">¥<?=number_format($p_total_cost,2)?></div>
                <div style="font-size:12px;color:#a0aec0;margin-top:4px;"><?=count($p_cost_rows)?> 张有成本数据</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">毛利润</div>
                <div class="stat-box-value <?=$p_total_profit>=0?'positive':'negative'?>">¥<?=number_format($p_total_profit,2)?></div>
                <div style="font-size:12px;color:#a0aec0;margin-top:4px;">营业额 - 成本</div>
            </div>
            <div class="stat-box" <?=$p_no_cost_cnt>0?'style="border:1px solid #fcd34d;"':''?>>
                <div class="stat-box-label">毛利率</div>
                <div class="stat-box-value <?=$p_total_margin>=20?'positive':($p_total_margin>=10?'':'negative')?>"><?=$p_total_margin?>%</div>
                <div style="font-size:12px;color:#a0aec0;margin-top:4px;">毛利润 ÷ 总营业额</div>
            </div>
        </div>

        <!-- 数据表格 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">
                    <?=htmlspecialchars(['order'=>'逐单明细','day'=>'按日汇总','week'=>'按周汇总','month'=>'按月汇总','year'=>'按年汇总'][$p_view])?>
                </h2>
            </div>
            <table>
                <?php if ($p_view === 'order'): ?>
                <thead><tr>
                    <th>送货单号</th><th>日期</th><th>客户</th>
                    <th style="text-align:right">营业额</th>
                    <th style="text-align:right">成本</th>
                    <th style="text-align:right">毛利润</th>
                    <th style="text-align:center">毛利率</th>
                    <th style="text-align:center">操作</th>
                </tr></thead>
                <tbody>
                <?php if (empty($p_rows)): ?>
                    <tr><td colspan="8" style="text-align:center;color:#a0aec0;padding:40px;">暂无数据</td></tr>
                <?php else: foreach ($p_rows as $r): ?>
                <tr>
                    <td><strong><?=htmlspecialchars($r['delivery_no'])?></strong></td>
                    <td><?=htmlspecialchars($r['delivery_date'])?></td>
                    <td><?=htmlspecialchars($r['customer_name'])?></td>
                    <td class="amount" style="text-align:right">¥<?=number_format($r['revenue'],2)?></td>
                    <td style="text-align:right">
                        <?php if ($r['has_cost']): ?>
                            <span class="amount">¥<?=number_format($r['cost'],2)?></span>
                        <?php else: ?>
                            <span class="badge margin-na">—</span>
                            <?php if (!$r['quote_id']): ?><span style="font-size:11px;color:#d97706;"> ⚠️无报价单</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="amount" style="text-align:right;color:<?=$r['profit']===null?'#cbd5e0':($r['profit']>=0?'#1f6dad':'#f56565')?>">
                        <?=$r['profit']!==null?'¥'.number_format($r['profit'],2):'—'?>
                    </td>
                    <td style="text-align:center">
                        <?php if ($r['margin']!==null): $mc=$r['margin']>=20?'margin-high':($r['margin']>=10?'margin-mid':'margin-low'); ?>
                            <span class="badge <?=$mc?>"><?=$r['margin']?>%</span>
                        <?php else: ?>
                            <span class="badge margin-na">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <a href="delivery_view.php?id=<?=$r['id']?>" class="btn-sm" style="text-decoration:none;">查看</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>

                <?php else: ?>
                <thead><tr>
                    <th><?=htmlspecialchars(['day'=>'日期','week'=>'周次','month'=>'月份','year'=>'年份'][$p_view])?></th>
                    <th style="text-align:center">单数</th>
                    <th style="text-align:right">营业额</th>
                    <th style="text-align:right">成本</th>
                    <th style="text-align:right">毛利润</th>
                    <th style="text-align:center">毛利率</th>
                    <th style="text-align:center">成本覆盖</th>
                </tr></thead>
                <tbody>
                <?php if (empty($p_periods)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#a0aec0;padding:40px;">暂无数据</td></tr>
                <?php else: foreach ($p_periods as $pp): ?>
                <tr>
                    <td><strong><?=htmlspecialchars($pp['label'])?></strong></td>
                    <td style="text-align:center"><?=$pp['count']?></td>
                    <td class="amount" style="text-align:right">¥<?=number_format($pp['revenue'],2)?></td>
                    <td style="text-align:right"><?=$pp['cost']>0?'<span class="amount">¥'.number_format($pp['cost'],2).'</span>':'<span style="color:#cbd5e0">—</span>'?></td>
                    <td class="amount" style="text-align:right;color:<?=$pp['cost']>0?($pp['profit']>=0?'#1f6dad':'#f56565'):'#cbd5e0'?>">
                        <?=$pp['cost']>0?'¥'.number_format($pp['profit'],2):'—'?>
                    </td>
                    <td style="text-align:center">
                        <?php if ($pp['cost']>0): $mc=$pp['margin']>=20?'margin-high':($pp['margin']>=10?'margin-mid':'margin-low'); ?>
                            <span class="badge <?=$mc?>"><?=$pp['margin']?>%</span>
                        <?php else: ?>
                            <span class="badge margin-na">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;font-size:12px;color:#718096;"><?=$pp['cost_count']?>/<?=$pp['count']?> 张</td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div><!-- end profit tab -->
</main>

<!-- ── 从送货单生成应收 弹窗 ── -->
<div id="genArOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:400;" onclick="if(event.target===this)closeGenAr()"></div>
<div id="genArModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:600px;max-width:95vw;max-height:80vh;background:white;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,0.2);z-index:401;overflow:hidden;flex-direction:column;">
    <div style="padding:20px 24px;background:linear-gradient(135deg,#1f6dad,#135286);display:flex;justify-content:space-between;align-items:center;">
        <span style="color:white;font-size:16px;font-weight:600;">🚚 从送货单生成应收账款</span>
        <button onclick="closeGenAr()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:30px;height:30px;border-radius:6px;font-size:18px;cursor:pointer;">×</button>
    </div>
    <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;gap:10px;">
        <input id="genArSearch" type="text" placeholder="搜索送货单号或客户名称..."
               style="flex:1;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;"
               oninput="filterDeliveries(this.value)">
    </div>
    <div id="genArList" style="overflow-y:auto;max-height:calc(80vh - 160px);padding:12px 24px;">
        <div style="text-align:center;padding:30px;color:#a0aec0;">⏳ 加载中...</div>
    </div>
</div>

<script>
/* ── 标签页切换 ── */
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(name).classList.add('active');
    if (btn) btn.classList.add('active');
    else if (event && event.target) event.target.classList.add('active');
    closeDrawer();
    try { sessionStorage.setItem('finance_tab', name); } catch(e) {}
}

// 恢复上次 tab（含 URL 参数跳转）
(function() {
    const urlTab = new URLSearchParams(location.search).get('tab');
    const saved  = urlTab || sessionStorage.getItem('finance_tab') || 'receivable';
    const tabMap = { receivable:'receivable', payable:'payable', records:'records', receipts:'receipts', profit:'profit' };
    const target = tabMap[saved] || 'receivable';
    if (target !== 'receivable') {
        const btn = document.querySelector(`.tab-btn[onclick*="'${target}'"]`);
        switchTab(target, btn);
    }
})();

/* ── 打开/关闭抽屉 ── */
function openDrawer() {
    document.getElementById('detailDrawer').classList.add('open');
    document.getElementById('drawerOverlay').classList.add('open');
    document.getElementById('mainContent').classList.add('drawer-open');
}
function closeDrawer() {
    document.getElementById('detailDrawer').classList.remove('open');
    document.getElementById('drawerOverlay').classList.remove('open');
    document.getElementById('mainContent').classList.remove('drawer-open');
}

/* ── 查看详情 ── */
let currentDetail = null;

async function viewDetail(id, type) {
    document.getElementById('drawerTitle').textContent = type === 'receivable' ? '应收账款详情' : '应付账款详情';
    document.getElementById('drawerBody').innerHTML = '<div style="text-align:center;padding:40px;color:#a0aec0;">⏳ 加载中...</div>';
    document.getElementById('drawerFooter').innerHTML = '';
    openDrawer();

    try {
        const r = await fetch(`finance.php?action=detail&id=${id}&type=${type}`);
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch(je) {
            document.getElementById('drawerBody').innerHTML = `<div style="color:#f56565;padding:20px;font-size:12px;"><strong>JSON解析失败</strong><br><pre style="margin-top:8px;white-space:pre-wrap;word-break:break-all;">${text.substring(0,300)}</pre></div>`;
            return;
        }
        if (!d.success) {
            document.getElementById('drawerBody').innerHTML = `<div style="color:#f56565;padding:20px;">❌ ${d.message || '加载失败'}</div>`;
            return;
        }
        currentDetail = d;
        renderDrawer(d);
    } catch(e) {
        document.getElementById('drawerBody').innerHTML = '<div style="color:#f56565;padding:20px;">网络错误：' + e.message + '</div>';
    }
}

function renderDrawer(d) {
    const b = d.bill;
    const isAR = d.type === 'receivable';
    let html = '';

    // 金额概览
    html += `<div class="dw-amount-box">
        <div class="dw-amount-item"><div class="av">${fmt(b.total_amount)}</div><div class="al">${isAR?'应收':'应付'}金额</div></div>
        <div class="dw-amount-item"><div class="av positive">${fmt(b.paid_amount)}</div><div class="al">${isAR?'已收':'已付'}</div></div>
        <div class="dw-amount-item"><div class="av negative">${fmt(b.outstanding_amount)}</div><div class="al">待${isAR?'收':'付'}</div></div>
    </div>`;

    // 基本信息
    html += `<div class="dw-section"><div class="dw-section-title">账单信息</div>`;
    html += drow('账单编号', b.bill_no);
    html += drow('状态', `<span class="badge ${statusClass(b.status,isAR)}">${b.status}</span>`);
    html += drow('账单日期', b.bill_date ? b.bill_date.substring(0,10) : '-');
    html += drow('到期日期', b.due_date  ? b.due_date.substring(0,10)  : '-');
    if (isAR) {
        html += drow('客户名称', b.company_name || '-');
        html += drow('联系人',   b.contact_name || '-');
        html += drow('联系电话', b.phone || '-');
        if (b.quote_no) html += drow('关联报价单', `<a href="quote_view.php?id=${b.quote_id}" target="_blank" style="color:#1f6dad;">${b.quote_no} ↗</a>`);
    } else {
        html += drow('供应商',   b.supplier_name || '-');
        html += drow('费用类别', b.category || '-');
    }
    if (b.notes) html += drow('备注', b.notes);
    html += '</div>';

    // 收付款历史
    html += `<div class="dw-section"><div class="dw-section-title">收付款记录（${d.payments.length}笔）</div>`;
    if (d.payments.length > 0) {
        d.payments.forEach(p => {
            html += `<div class="payment-item">
                <div class="pi-top">
                    <span class="pi-amount">+¥${Number(p.amount).toFixed(2)}</span>
                    <span class="pi-date">${p.payment_date ? p.payment_date.substring(0,10) : ''}</span>
                </div>
                <div class="pi-method">💳 ${p.payment_method||'-'} &nbsp;·&nbsp; 经办：${p.operator||'-'}</div>
                ${p.notes ? `<div class="pi-note">💬 ${p.notes}</div>` : ''}
            </div>`;
        });
    } else {
        html += '<div class="no-payment">📭 暂无收付款记录</div>';
    }
    html += '</div>';

    // 编辑表单（内联，用 data-* 传值避免 id 冲突）
    const canEdit = true; // 应收改到期/备注，应付视状态
    const fullEdit = !isAR && b.status === '未付款';
    const dueVal  = b.due_date  ? b.due_date.substring(0,10)  : '';
    const notesVal = esc(b.notes || '');

    html += `<div class="dw-section">
        <div class="dw-section-title">快速编辑</div>
        <div class="edit-form" id="editForm" data-id="${b.id}" data-type="${d.type}" data-full="${fullEdit}">
            ${fullEdit ? `
            <div class="ef-row"><label>供应商名称</label><input class="ef-supplier" value="${esc(b.supplier_name||'')}"></div>
            <div class="ef-row"><label>费用类别</label><input class="ef-category" value="${esc(b.category||'')}"></div>
            <div class="ef-row"><label>应付金额</label><input class="ef-amount" type="number" step="any" value="${b.total_amount}"></div>
            ` : (!isAR ? '<p style="font-size:12px;color:#718096;margin-bottom:10px;">该账单已有付款记录，仅可修改到期日和备注</p>' : '')}
            <div class="ef-row"><label>到期日期</label><input class="ef-due" type="date" value="${dueVal}"></div>
            <div class="ef-row"><label>备注</label><textarea class="ef-notes" rows="3">${notesVal}</textarea></div>
            <div class="ef-actions">
                <button class="dw-btn dw-btn-primary" onclick="saveEdit()">💾 保存</button>
                <button class="dw-btn dw-btn-gray"    onclick="document.getElementById('editForm').classList.remove('show')">取消</button>
            </div>
        </div>
    </div>`;

    document.getElementById('drawerBody').innerHTML = html;

    // 底部按钮
    let footer = '';
    if (isAR) {
        if (parseFloat(b.outstanding_amount) > 0)
            footer += `<button class="dw-btn dw-btn-primary" onclick="window.location.href='payment_receive.php?id=${b.id}'">💰 收款</button>`;
        footer += `<button class="dw-btn dw-btn-amber" onclick="toggleEdit()">✏️ 编辑</button>`;
        if (parseFloat(b.paid_amount) == 0)
            footer += `<button class="dw-btn dw-btn-red" onclick="deleteAR(${b.id})">🗑️ 删除</button>`;
    } else {
        if (parseFloat(b.outstanding_amount) > 0)
            footer += `<button class="dw-btn dw-btn-primary" onclick="window.location.href='payment_pay.php?id=${b.id}'">💸 付款</button>`;
        footer += `<button class="dw-btn dw-btn-amber" onclick="toggleEdit()">✏️ 编辑</button>`;
        footer += `<button class="dw-btn dw-btn-gray" onclick="copyAP(${b.id})">📋 复制</button>`;
        if (b.status === '未付款')
            footer += `<button class="dw-btn dw-btn-red" onclick="deleteAP(${b.id})">🗑️ 删除</button>`;
    }
    document.getElementById('drawerFooter').innerHTML = footer;
}

/* ── 工具函数 ── */
function drow(label, value) {
    return `<div class="dw-row"><span class="dw-label">${label}</span><span class="dw-value">${value}</span></div>`;
}
function fmt(v) { return '¥' + Number(v||0).toFixed(2); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function statusClass(s, isAR) {
    if (isAR) return {未收款:'warning',部分收款:'info',已收款:'success',已逾期:'danger'}[s]||'info';
    return {未付款:'danger',部分付款:'warning',已付款:'success',已逾期:'danger'}[s]||'info';
}

function toggleEdit() {
    const f = document.getElementById('editForm');
    if (f) f.classList.toggle('show');
}

async function saveEdit() {
    const form = document.getElementById('editForm');
    if (!form) return;
    const id       = form.dataset.id;
    const type     = form.dataset.type;
    const fullEdit = form.dataset.full === 'true';

    const due   = form.querySelector('.ef-due')?.value   || '';
    const notes = form.querySelector('.ef-notes')?.value || '';
    const body  = new URLSearchParams({ id, due_date: due, notes });

    let url;
    if (type === 'receivable') {
        url = 'finance.php?action=edit_ar_note';
    } else {
        url = 'finance.php?action=edit_ap';
        if (fullEdit) {
            body.append('supplier_name', form.querySelector('.ef-supplier')?.value || '');
            body.append('category',      form.querySelector('.ef-category')?.value || '');
            body.append('total_amount',  form.querySelector('.ef-amount')?.value   || 0);
        }
    }

    try {
        const r = await fetch(url, { method: 'POST', body });
        const d = await r.json();
        if (d.success) { sapToast('✅ ' + d.message, 'info'); viewDetail(id, type); }
        else sapToast('❌ ' + d.message, 'info');
    } catch(e) { sapToast('❌ 保存失败：' + e.message, 'error'); }
}

async function deleteAR(id) {
    if (!confirm('确定删除此应收账款吗？仅未收款账单可删除。')) return;
    const r = await fetch('finance.php?action=delete_ar', { method:'POST', body: new URLSearchParams({id}) });
    const d = await r.json();
    if (d.success) { sapToast('✅ 已删除', 'success'); closeDrawer(); location.reload(); }
    else sapToast('❌ ' + d.message, 'info');
}

async function deleteAP(id) {
    if (!confirm('确定删除此应付账款吗？仅未付款账单可删除。')) return;
    const r = await fetch('finance.php?action=delete_ap', { method:'POST', body: new URLSearchParams({id}) });
    const d = await r.json();
    if (d.success) { sapToast('✅ 已删除', 'success'); closeDrawer(); location.reload(); }
    else sapToast('❌ ' + d.message, 'info');
}

async function copyAP(id) {
    if (!confirm('复制此应付账款？将创建一条今日日期的新账单。')) return;
    const r = await fetch('finance.php?action=copy_ap', { method:'POST', body: new URLSearchParams({id}) });
    const d = await r.json();
    if (d.success) { sapToast('✅ ' + d.message, 'info'); closeDrawer(); location.reload(); }
    else sapToast('❌ ' + d.message, 'info');
}

// ESC 关闭抽屉
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeDrawer(); closeGenAr(); }
});

/* ── 从送货单生成应收 ── */
let allDeliveries = [];

async function showGenFromDelivery() {
    document.getElementById('genArOverlay').style.display = 'block';
    const modal = document.getElementById('genArModal');
    modal.style.display = 'flex';
    document.getElementById('genArList').innerHTML = '<div style="text-align:center;padding:30px;color:#a0aec0;">⏳ 加载中...</div>';

    try {
        const r = await fetch('finance.php?action=list_deliveries_for_ar');
        const d = await r.json();
        if (!d.success) { document.getElementById('genArList').innerHTML = '<div style="color:#f56565;padding:20px;">加载失败：' + d.message + '</div>'; return; }
        allDeliveries = d.deliveries;
        renderDeliveryList(allDeliveries);
    } catch(e) {
        document.getElementById('genArList').innerHTML = '<div style="color:#f56565;padding:20px;">网络错误：' + e.message + '</div>';
    }
}

function closeGenAr() {
    document.getElementById('genArOverlay').style.display = 'none';
    document.getElementById('genArModal').style.display = 'none';
}

function filterDeliveries(kw) {
    const filtered = kw ? allDeliveries.filter(d =>
        d.delivery_no.includes(kw) || d.customer_name.includes(kw)
    ) : allDeliveries;
    renderDeliveryList(filtered);
}

function renderDeliveryList(list) {
    if (!list.length) {
        document.getElementById('genArList').innerHTML = '<div style="text-align:center;padding:30px;color:#a0aec0;">暂无符合条件的送货单</div>';
        return;
    }
    let html = '';
    list.forEach(d => {
        const hasAr = d.has_ar == 1;
        html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f0f0;">
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:14px;">${d.delivery_no}</div>
                <div style="font-size:12px;color:#718096;margin-top:2px;">${d.customer_name} &nbsp;·&nbsp; ${d.delivery_date} &nbsp;·&nbsp; <span style="font-family:'Courier New';font-weight:600;">¥${Number(d.total_amount).toFixed(2)}</span></div>
            </div>
            <div style="flex-shrink:0;margin-left:16px;">
                ${hasAr
                    ? '<span style="font-size:12px;color:#1f6dad;background:#d1fae5;padding:4px 10px;border-radius:20px;">已生成</span>'
                    : `<button onclick="genArFromDelivery(${d.id},'${d.delivery_no}',this)"
                         style="padding:6px 14px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:6px;font-size:13px;cursor:pointer;">生成应收</button>`
                }
            </div>
        </div>`;
    });
    document.getElementById('genArList').innerHTML = html;
}

async function genArFromDelivery(id, no, btn) {
    if (!confirm(`确定为送货单 "${no}" 生成应收账款吗？`)) return;
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = '⏳';
    try {
        const r = await fetch('delivery_approve.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` });
        const d = await r.json();
        if (d.success) {
            btn.style.background = '#1f6dad'; btn.textContent = '✓ 已生成';
            // 更新本地列表状态
            const item = allDeliveries.find(x => x.id == id);
            if (item) item.has_ar = 1;
            setTimeout(() => { closeGenAr(); location.reload(); }, 800);
        } else {
            sapToast('❌ ' + d.message, 'info');
            btn.disabled = false; btn.textContent = orig;
        }
    } catch(e) {
        sapToast('❌ 网络错误：' + e.message, 'error');
        btn.disabled = false; btn.textContent = orig;
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
