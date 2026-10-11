<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);

// 获取合同
$sql = "SELECT * FROM sales_contracts WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) die("合同不存在");

$_nav_prev_id = $conn->query("SELECT MIN(id) m FROM sales_contracts WHERE id > " . intval($id) . " AND user_id=" . intval($_SESSION['user_id']))->fetch_assoc()['m'];
$_nav_next_id = $conn->query("SELECT MAX(id) m FROM sales_contracts WHERE id < " . intval($id) . " AND user_id=" . intval($_SESSION['user_id']))->fetch_assoc()['m'];

// 获取明细
$items_sql = "SELECT * FROM sales_contract_items WHERE contract_id = ? ORDER BY item_no";
$stmt2 = $conn->prepare($items_sql);
$stmt2->bind_param("i", $id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取公章
$seals = [];
$seal_result = $conn->query("SELECT id, seal_name, file_path FROM seals WHERE user_id = " . intval($_SESSION['user_id']) . " LIMIT 5");
if ($seal_result) {
    $seals = $seal_result->fetch_all(MYSQLI_ASSOC);
}

require_once 'settings_helper.php';
$cfg = getUserSettings($conn, $_SESSION['user_id']);

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<?php
$type_label_map = [
    'sales'        => '销售合同',
    'construction' => '施工合同',
    'outsource'    => 'IT外包服务合同',
    'rental'       => '设备租赁合同',
    'software'     => '软件开发合同',
    'loan'         => '设备借用合同',
];
$con_type_label = $type_label_map[$contract['contract_type']??'sales'] ?? '合同';
$page_title = $con_type_label . ' - ' . e($contract['contract_no']);
include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
body {font-family: "SimSun", "Microsoft YaHei", Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0;}
.contract-page-body { margin-left: 260px; padding-top: 74px; }
.toolbar {
    position: fixed; top: 76px; right: 20px; background: white;
    padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0;
    z-index: 1000; width: 200px;
    transform: translateZ(0);
    max-height: calc(100vh - 96px);
    overflow-y: auto;
}
.toolbar h4 {margin: 0 0 8px 0; font-size: 13px; color: #333;}
.toolbar button {
    width: 100%; padding: 8px; margin-bottom: 6px; border: none;
    border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500;
}
.btn-primary  {background: #1f6dad; color: white;}
.btn-secondary{background: #6c757d; color: white;}
.btn-warning  {background: #f59e0b; color: white;}
.btn-danger   {background: #ef4444; color: white;}
.btn-primary:hover  {background: #1a5c94;}
.btn-warning:hover  {background: #d97706;}
.btn-danger:hover   {background: #dc2626;}
.seal-list {margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;}
.seal-item {
    display: flex; align-items: center; gap: 8px; padding: 6px;
    border: 2px solid #e0e0e0; border-radius: 4px; margin-bottom: 5px; cursor: pointer;
}
.seal-item:hover {border-color: #1f6dad; background: #f7fafc;}
.seal-thumb {width: 28px; height: 28px; object-fit: contain;}
.seal-info {flex: 1; font-size: 11px;}

/* ── 合同页面主体 ── */
.page {
    width: 794px; min-height: 1122px; margin: 10px auto 30px;
    background: white; padding: 68px 83px 57px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(15,42,67,.08), 0 12px 32px rgba(15,42,67,.06);
    position: relative;
    overflow: hidden;
    font-size: 10.5pt;
    color: #1a202c;
}
/* 顶部装饰色带 */
.page::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px;
    background: linear-gradient(90deg, #135286 0%, #1f6dad 50%, #3a8fd6 100%);
}

/* 页眉合同编号（打印时可见） */
.page-header-no {
    font-size: 9pt; color: #a0aec0; text-align: right;
    margin-bottom: 20px; letter-spacing: 0.5px;
}
.header {text-align: center; margin-bottom: 26px;}
.header h1 {font-size: 24pt; font-weight: 900; letter-spacing: 10px; margin: 0 0 0 10px; color: #135286;}
.header .divider {
    border: none; height: 3px; margin: 16px auto 10px; width: 120px;
    background: linear-gradient(90deg, #135286, #3a8fd6);
    border-radius: 2px;
}
.header .no {font-size: 10pt; color: #718096; letter-spacing: 1px;}

/* 甲乙方信息框 — table 对齐，同行等高 */
.party-box {
    border: 1px solid #cbd5e0; border-radius: 6px; overflow: hidden;
    margin: 16px 0 18px;
    font-size: 11pt;           /* 默认字号正常大小，超长内容由JS按格自动缩小 */
}
.party-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.party-cell {
    padding: 7px 10px; line-height: 1.9;
    vertical-align: top;
    white-space: nowrap;   /* 父级也必须nowrap，否则浏览器仍可能在标签和值两个span之间断行 */
}
.party-table tr:first-child .party-cell { background: #f0f6fc; }
/* 标签和值同一行，都不允许换行（哪怕内容较长也不强制断行） */
.party-cell .lv-lbl { white-space: nowrap; font-weight: bold; color: #135286; }
.party-cell .lv-val { white-space: nowrap; }
/* 左列右侧画竖线 */
.party-table tr td:first-child { border-right: 1px solid #cbd5e0; }
/* 第 2、3、4 行顶部虚线分隔 */
.party-table tr:not(:first-child) td { border-top: 1px dashed #e2e8f0; }
/* 工期/服务期附加行 */
.party-extra {
    border-top: 1px dashed #cbd5e0; padding: 7px 14px;
    font-size: 9.5pt; line-height: 1.9; background: #f7fafc;
}
.party-extra p { margin: 0; }

/* 条款 */
.section {margin: 16px 0;}
.section-title {
    font-weight: bold; font-size: 11pt; margin-bottom: 6px; color: #135286;
    padding-left: 10px; border-left: 4px solid #1f6dad;
}
.section-content {line-height: 1.8; font-size: 10.5pt; text-indent: 2em; color: #2d3748;}

/* 明细表 */
table.items-tbl {width: 100%; border-collapse: collapse; margin: 11px 0 0; font-size: 10pt;}
table.items-tbl th, table.items-tbl td {border: 1px solid #cbd5e0; padding: 7px 8px;}
table.items-tbl th {background: #135286; color: #fff; font-weight: bold; text-align: center; letter-spacing: .5px;}
table.items-tbl tbody tr:nth-child(even) {background: #f7fafc;}
table.items-tbl .r {text-align: right;}
table.items-tbl .c {text-align: center;}
.amount-total {
    background: #f0f6fc; border: 1px solid #cbd5e0; border-top: 3px solid #1f6dad;
    font-weight: bold; font-size: 12pt; color: #135286;
    margin: 8px 0 0; padding: 8px 14px; text-align: right; border-radius: 0 0 4px 4px;
}
.amount-cn {font-size: 10pt; color: #718096; margin: 6px 0 18px; text-align: right;}

/* 签字区 — table 4行×2列，各行自动等高 */
.signatures-divider { border-top: 1px dashed #cbd5e0; margin-top: 45px; }
.signatures {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.signatures td:first-child { padding-right: 45px; }
.sig-name-cell {
    font-size: 10.5pt; font-weight: bold; line-height: 1.8; color: #135286;
    padding-bottom: 11px;
}
.sig-name-cell .lv-lbl { display: block; }
.sig-name-cell .lv-val { display: block; word-break: break-all; }
.sig-line  { border-bottom: 1px solid #a0aec0; height: 30px; padding-bottom: 8px; }
.sig-auth  { font-size: 9pt; color: #718096; padding-bottom: 23px; }
.sig-date  { font-size: 10.5pt; color: #2d3748; }

.seal {
    position: absolute; cursor: move; z-index: 100; opacity: 0.9;
    user-select: none; width: 120px; height: 120px;
}
.seal img {width: 100%; height: 100%; display: block; pointer-events: none;}

/* 屏幕上：分隔线作为视觉提示，打印时不显示 */
.pg-break { display: none; }

@media print {
    @page { size: A4; margin: 22mm 22mm 22mm 22mm; }
    body { background: none; }
    .navbar, .sidebar, .toolbar { display: none !important; }
    .contract-page-body { margin: 0 !important; padding: 0 !important; }
    .page { box-shadow: none; margin: 0; padding: 0; min-height: auto; width: auto; border-radius: 0; }
    .page::before { display: none; }
    .amount-total { background: none; border: none; border-top: 2px solid #000; color: #000; }
    table.items-tbl th { background: #ececec !important; color: #000 !important; -webkit-print-color-adjust: exact; }
    .party-table tr:first-child .party-cell,
    .party-extra { background: none; }
    .seal { cursor: default !important; }
    .page-header-no { color: #555; }

    /* 全部内容自然流排，不做任何强制换页 */
    /* 只保护这三类区块不在内部被截断 */
    .party-box      { page-break-inside: avoid; }
    table.items-tbl { page-break-inside: avoid; }
    .signatures     { page-break-inside: avoid; }
    /* 防孤行：每段落顶部/底部至少保留 3 行 */
    .section-content { orphans: 3; widows: 3; }
}
</style>
</head>
<body>
<?php
$_navbar_extra = '<div style="display:flex;align-items:center;gap:8px;margin-right:12px;">'
    . '<span style="font-size:13px;color:#94a3b8;font-weight:500;margin-right:4px;">' . e($contract['contract_no']) . '</span>'
    . ($_nav_prev_id ? '<a href="contract_view.php?id=' . $_nav_prev_id . '" class="btn-logout" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">↑ 上一页</a>' : '')
    . ($_nav_next_id ? '<a href="contract_view.php?id=' . $_nav_next_id . '" class="btn-logout" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">↓ 下一页</a>' : '')
    . '</div>';
?>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="contract-page-body">

<div class="toolbar">
    <?php $badge_map=['sales'=>'#1f6dad','construction'=>'#b45309','outsource'=>'#0891b2','rental'=>'#7c3aed','software'=>'#059669','loan'=>'#d97706']; ?>
<div style="margin-bottom:10px;padding:7px 10px;border-radius:6px;font-size:13px;font-weight:700;color:white;background:<?=$badge_map[$ct]??'#1f6dad'?>;text-align:center;letter-spacing:1px;"><?=$con_type_label?></div>
<h4>🖊️ 公章</h4>
    <div class="seal-list" id="sealList">
        <?php if (count($seals) > 0): ?>
            <?php foreach ($seals as $seal): ?>
            <div class="seal-item" onclick="addSeal('<?php echo e($seal['file_path']); ?>')">
                <img src="<?php echo e($seal['file_path']); ?>" class="seal-thumb">
                <div class="seal-info"><?php echo e($seal['seal_name']); ?></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size: 11px; color: #999;">暂无公章</p>
        <?php endif; ?>
    </div>
    <hr style="margin: 12px 0; border: none; border-top: 1px solid #e2e8f0;">
    <h4>📄 文档操作</h4>
    <button class="btn-primary" onclick="window.print()">🖨️ 打印/PDF</button>
    <button class="btn-primary" onclick="exportWord()">📄 导出Word</button>
    <button class="btn-primary" onclick="exportExcel()">📊 导出Excel</button>
    <hr style="margin: 12px 0; border: none; border-top: 1px solid #e2e8f0;">
    <h4>⚙️ 管理</h4>
    <button class="btn-warning" onclick="window.location.href='contract_edit.php?id=<?php echo $id; ?>'">✏️ 编辑</button>
    <button class="btn-danger" onclick="deleteContract()">🗑️ 删除</button>
    <button class="btn-secondary" style="justify-content:center;" onclick="window.location.href='contracts.php'">🔙 返回</button>
</div>

<div class="page" id="page">
<?php
$ct   = $contract['contract_type'] ?? 'sales';
$cn   = e($contract['contract_no']);
$cd   = date('Y年m月d日', strtotime($contract['contract_date']));
$cust = e($contract['customer_name']);
$my   = e($contract['own_name']    ?: $cfg['company_name']);
$addr = e($contract['own_address'] ?: $cfg['company_address']);
$cp   = e($contract['own_contact'] ?: $cfg['contact_person']);
$cph  = e($contract['own_phone']   ?: $cfg['contact_phone']);
$caddr = e($contract['customer_address'] ?? '');
$ccp   = e($contract['customer_contact'] ?? '');
$ccph  = e($contract['customer_phone'] ?? '');
$amt_raw = floatval($contract['total_amount']);

/* 金额大写 */
function money_to_cn($n) {
    if ($n <= 0) return '零元整';
    $n = round($n, 2);
    $digits = ['零','壹','贰','叁','肆','伍','陆','柒','捌','玖'];
    $uints4 = ['','拾','佰','仟'];
    $wans   = ['','万','亿'];
    $int_part = (int)$n;
    $dec = round(($n - $int_part) * 100);
    $jiao = (int)($dec / 10); $fen = $dec % 10;
    $int_str = '';
    if ($int_part == 0) {
        $int_str = '零';
    } else {
        $tmp = $int_part; $segs = []; $wi = 0;
        while ($tmp > 0) { $segs[] = [$tmp % 10000, $wi++]; $tmp = (int)($tmp / 10000); }
        $segs = array_reverse($segs);
        foreach ($segs as [$seg, $wi]) {
            if ($seg == 0) { if ($int_str) $int_str .= '零'; continue; }
            $ss = ''; $prev_zero = false;
            for ($i = 3; $i >= 0; $i--) {
                $d = (int)($seg / pow(10,$i)) % 10;
                if ($d == 0) { $prev_zero = true; continue; }
                if ($prev_zero && $ss) $ss .= '零';
                $ss .= $digits[$d] . $uints4[$i];
                $prev_zero = false;
            }
            if ($int_str && substr($int_str,-1)==='零') {}
            elseif ($int_str && $prev_zero) $int_str .= '零';
            $int_str .= $ss . $wans[$wi];
        }
    }
    $result = ($int_part ? $int_str . '元' : '');
    if ($jiao == 0 && $fen == 0) { $result .= '整'; }
    else { if (!$int_part) $result = ''; if ($jiao) $result .= $digits[$jiao].'角'; elseif ($int_part) $result .= '零'; if ($fen) $result .= $digits[$fen].'分'; }
    return $result;
}

/* 甲乙方信息框（Grid 6格，同行自动等高对齐） */
function party_box($left_label, $left_name, $left_addr, $left_cp, $left_cph,
                   $right_label, $right_name, $right_addr, $right_cp, $right_cph, $extra='') {
    $e = fn($v) => htmlspecialchars($v??'', ENT_QUOTES, 'UTF-8');
    $cell = fn($lbl,$val) =>
        '<td class="party-cell"><span class="lv-lbl">'.$lbl.'：</span><span class="lv-val">'.$val.'</span></td>';
    $row  = fn($lbl,$lv,$rv) => '<tr>'.$cell($lbl,$lv).$cell($lbl,$rv).'</tr>';
    echo '<div class="party-box"><table class="party-table"><tbody>';
    echo '<tr>'.$cell($left_label, $e($left_name)).$cell($right_label, $e($right_name)).'</tr>';
    echo $row('地　址',   $e($left_addr), $e($right_addr));
    echo $row('联系人',   $e($left_cp),   $e($right_cp));
    echo $row('联系电话', $e($left_cph),  $e($right_cph));
    echo '</tbody></table>';
    if ($extra) echo '<div class="party-extra">'.$extra.'</div>';
    echo '</div>';
}

/* 明细表 */
function render_items($items, $col1='产品/服务名称') {
    echo '<table class="items-tbl"><tr>'
        .'<th style="width:30px;" class="c">序号</th><th>'.$col1.'</th><th>规格说明</th>'
        .'<th style="width:44px;" class="c">单位</th><th style="width:50px;" class="c">数量</th>'
        .'<th style="width:88px;" class="r">单价(¥)</th><th style="width:92px;" class="r">金额(¥)</th></tr>';
    $calc = 0;
    foreach ($items as $it) {
        $amt = floatval($it['amount'] ?? (floatval($it['price']??0)*floatval($it['quantity']??1)));
        $calc += $amt;
        echo '<tr>'
            .'<td class="c">'.intval($it['item_no']).'</td>'
            .'<td>'.htmlspecialchars($it['product_name']??'',ENT_QUOTES,'UTF-8').'</td>'
            .'<td>'.htmlspecialchars($it['specification']??'',ENT_QUOTES,'UTF-8').'</td>'
            .'<td class="c">'.htmlspecialchars($it['unit']??'',ENT_QUOTES,'UTF-8').'</td>'
            .'<td class="r">'.intval($it['quantity']).'</td>'
            .'<td class="r">'.number_format($it['price'],2).'</td>'
            .'<td class="r">'.number_format($amt,2).'</td>'
            .'</tr>';
    }
    echo '</table>';
    return $calc;
}

/* 金额合计块（含大写） */
function amount_block($label, $amt) {
    echo '<div class="amount-total">'.$label.'：¥'.number_format($amt,2).'</div>';
    echo '<div class="amount-cn">（大写：'.money_to_cn($amt).'）</div>';
}

/* 条款 */
function sec($title, $content, $default='') {
    $text = trim($content ?? '');
    if (!$text) $text = $default;
    if (!$text) return;
    echo '<div class="section"><div class="section-title">'.$title.'</div>'
        .'<div class="section-content">'.nl2br(htmlspecialchars($text,ENT_QUOTES,'UTF-8')).'</div></div>';
}
function sec_fixed($title, $content) {
    echo '<div class="section"><div class="section-title">'.$title.'</div>'
        .'<div class="section-content">'.$content.'</div></div>';
}

/* 签字区 */
function signatures($left_label, $left_name, $right_label, $right_name) {
    $e = fn($v) => htmlspecialchars($v??'', ENT_QUOTES, 'UTF-8');
    // 标签统一以"盖章"两字结尾，单独包一层span，方便JS精确定位公章位置
    $lbl_html = function($lbl) {
        $head = mb_substr($lbl, 0, -2);
        $tail = mb_substr($lbl, -2);
        return $head.'<span class="seal-word">'.$tail.'</span>';
    };
    $nc = fn($lbl,$nm) =>
        '<td class="sig-name-cell"><span class="lv-lbl">'.$lbl_html($lbl).'：</span><span class="lv-val">'.$e($nm).'</span></td>';
    echo '<div class="signatures-divider"></div>';
    echo '<table class="signatures"><tbody>';
    // 第1行：盖章 + 名称
    echo '<tr>'.$nc($left_label, $left_name).$nc($right_label, $right_name).'</tr>';
    // 第2行：签字横线
    echo '<tr><td class="sig-line"></td><td class="sig-line"></td></tr>';
    // 第3行：授权代表
    echo '<tr><td class="sig-auth">授权代表签字：</td><td class="sig-auth">授权代表签字：</td></tr>';
    // 第4行：日期
    echo '<tr><td class="sig-date">日期：　　　年　　月　　日</td><td class="sig-date">日期：　　　年　　月　　日</td></tr>';
    echo '</tbody></table>';
}

/* 通用页眉 */
function page_no($cn) {
    echo '<div class="page-header-no">合同编号：'.$cn.'</div>';
}

$sm_map = ['onsite'=>'驻场服务','visit'=>'上门服务','remote'=>'远程服务','hybrid'=>'驻场+远程混合'];
?>

<?php /* ═══════════ 一、销售合同 ═══════════ */ if ($ct === 'sales'): ?>
<?php page_no($cn); ?>
<div class="header">
    <h1>销　售　合　同</h1>
    <hr class="divider">
    <div class="no">合同编号：<?=$cn?>&emsp;签订日期：<?=$cd?></div>
</div>
<?php party_box('甲方（卖方）', $my, $addr, $cp, $cph, '乙方（买方）', $cust, $caddr, $ccp, $ccph); ?>
<div class="section"><div class="section-content">根据《中华人民共和国民法典》及相关法律法规，甲乙双方在平等、自愿的基础上，就以下产品及服务的采购事宜达成如下协议：</div></div>
<div class="section"><div class="section-title">第一条&emsp;产品及服务清单</div>
<?php $calc = render_items($items, '产品/服务名称'); ?>
<?php amount_block('合计金额', $calc ?: $amt_raw); ?></div>
<div class="pg-break"></div>
<?php sec('第二条&emsp;付款条款', $contract['payment_terms']); ?>
<?php sec('第三条&emsp;交货条款', $contract['delivery_terms']); ?>
<?php sec('第四条&emsp;质量保证', $contract['warranty_terms']); ?>
<?php sec('第五条&emsp;违约责任', $contract['breach_terms'], '任何一方违反本合同约定，应向守约方承担违约责任，赔偿因此造成的全部损失。'); ?>
<?php sec('第六条&emsp;争议解决', $contract['dispute_terms'], '双方发生争议，应友好协商解决；协商不成的，提交甲方所在地人民法院诉讼解决。本合同一式两份，双方各执一份，具有同等法律效力。'); ?>
<?php sec('附&emsp;&emsp;注', $contract['notes']); ?>
<?php signatures('甲方（卖方）盖章', $my, '乙方（买方）盖章', $cust); ?>

<?php /* ═══════════ 二、施工合同 ═══════════ */ elseif ($ct === 'construction'): ?>
<?php page_no($cn); ?>
<div class="header">
    <h1>施　工　合　同</h1>
    <hr class="divider">
    <div class="no">合同编号：<?=$cn?>&emsp;签订日期：<?=$cd?></div>
</div>
<?php
$_extra = ($contract['work_start']&&$contract['work_end'])
    ? '<p><strong>施工工期：</strong>'.e($contract['work_start']).' 至 '.e($contract['work_end']).'</p>' : '';
party_box('发包方（甲方）', $cust, $caddr, $ccp, $ccph, '承包方（乙方）', $my, $addr, $cp, $cph, $_extra);
?>
<div class="section"><div class="section-content">甲、乙双方根据《中华人民共和国民法典》《中华人民共和国建筑法》及相关法律法规，遵循平等、自愿、公平和诚实信用的原则，就本工程施工事宜协商一致，订立本合同。</div></div>
<?php $n=1; if(trim($contract['service_scope']??'')): sec('第一条&emsp;工程概况及承包范围', $contract['service_scope']); $n=2; endif; ?>
<div class="section"><div class="section-title">第<?=$n?>条&emsp;工程费用清单</div>
<?php $calc = render_items($items, '工程项目名称'); ?>
<?php amount_block('工程总价', $calc ?: $amt_raw); ?></div>
<div class="pg-break"></div>
<?php $n++;sec('第'.$n.'条&emsp;付款方式', $contract['payment_terms'], '合同签订后7日内支付30%预付款；工程完工验收合格后支付60%；竣工结算审定后30日内付清余款10%。'); ?>
<?php $n++;sec('第'.$n.'条&emsp;工程验收', $contract['acceptance_terms'], '工程竣工后乙方提交竣工报告，甲方在5个工作日内组织验收，验收合格后双方签署竣工验收单作为结算依据。'); ?>
<?php $n++;sec('第'.$n.'条&emsp;质量保证', $contract['warranty_terms'], '工程竣工验收合格后质保期2年，质保期内非人为损坏由乙方免费维修。'); ?>
<?php $n++;sec_fixed('第'.$n.'条&emsp;安全文明施工', '乙方在施工期间须严格遵守国家安全生产相关法规，做好施工现场安全防护措施。因乙方原因造成的安全事故，由乙方承担全部责任。'); ?>
<?php $n++;sec('第'.$n.'条&emsp;违约责任', $contract['breach_terms'], '任何一方违反本合同约定，应向守约方承担违约责任，赔偿因此造成的损失。因不可抗力导致合同无法履行的，双方互不承担违约责任。'); ?>
<?php $n++;sec('第'.$n.'条&emsp;争议解决', $contract['dispute_terms'], '双方发生争议，应友好协商解决；协商不成的，提交甲方所在地人民法院诉讼解决。本合同一式两份，双方各执一份，具有同等法律效力。'); ?>
<?php sec('附&emsp;&emsp;注', $contract['notes']); ?>
<?php signatures('乙方（承包方）盖章', $my, '甲方（发包方）盖章', $cust); ?>

<?php /* ═══════════ 三、IT外包合同 ═══════════ */ elseif ($ct === 'outsource'): ?>
<?php page_no($cn); ?>
<div class="header">
    <h1>IT 外 包 服 务 合 同</h1>
    <hr class="divider">
    <div class="no">合同编号：<?=$cn?>&emsp;签订日期：<?=$cd?></div>
</div>
<?php
$_ex2 = '';
if($contract['work_start']&&$contract['work_end'])
    $_ex2 .= '<p><strong>服务期限：</strong>'.e($contract['work_start']).' 至 '.e($contract['work_end']).'</p>';
if($contract['service_mode'])
    $_ex2 .= '<p><strong>服务方式：</strong>'.($sm_map[$contract['service_mode']]??e($contract['service_mode'])).'</p>';
party_box('委托方（甲方）', $cust, $caddr, $ccp, $ccph, '服务方（乙方）', $my, $addr, $cp, $cph, $_ex2);
?>
<div class="section"><div class="section-content">甲、乙双方根据《中华人民共和国民法典》及相关法律法规，本着平等互利、诚实信用的原则，就乙方向甲方提供IT外包服务事宜，协商一致签订本合同。</div></div>
<?php $m=1; if(trim($contract['service_scope']??'')): sec('第一条&emsp;服务范围与内容', $contract['service_scope']); $m=2; endif; ?>
<div class="section"><div class="section-title">第<?=$m?>条&emsp;服务费用清单</div>
<?php $calc = render_items($items, '服务项目名称'); ?>
<?php amount_block('服务费合计', $calc ?: $amt_raw); ?></div>
<div class="pg-break"></div>
<?php $m++;sec('第'.$m.'条&emsp;付款方式', $contract['payment_terms'], '服务费按年收取，合同签订后7日内支付全年服务费。按月收取时，每月1日前支付当月服务费，逾期7日乙方有权暂停服务。'); ?>
<?php $m++;sec('第'.$m.'条&emsp;服务级别协议（SLA）', $contract['sla_terms'], '一般故障：4小时内响应，24小时内解决；重大故障：2小时内响应，8小时内解决；紧急故障：30分钟内响应，4小时内解决。'); ?>
<?php $m++;sec_fixed('第'.$m.'条&emsp;甲方义务', '甲方应为乙方提供必要的工作条件和配合，包括：提供相关系统账号及网络访问权限；驻场服务时提供适宜的工作场所；如实告知IT系统现状及问题；及时对接乙方工作人员处理事宜。'); ?>
<?php $m++;sec('第'.$m.'条&emsp;保密条款', $contract['warranty_terms'], '乙方对服务过程中接触到的甲方业务数据、技术资料及商业信息严格保密，未经甲方书面许可不得向第三方披露。合同终止后保密义务继续有效，期限为3年。'); ?>
<?php $m++;sec_fixed('第'.$m.'条&emsp;知识产权', '乙方为甲方定制开发的软件、脚本及技术文档，知识产权归甲方所有；乙方使用的通用工具及技术方法的知识产权归乙方所有。'); ?>
<?php $m++;sec('第'.$m.'条&emsp;违约责任', $contract['breach_terms'], '任何一方违反本合同，应向守约方赔偿损失。乙方无故中止服务的，应退还剩余服务期对应费用并赔偿损失；甲方无故提前终止合同的，已付费用不予退还。'); ?>
<?php $m++;sec('第'.$m.'条&emsp;争议解决', $contract['dispute_terms'], '双方发生争议，应友好协商解决；协商不成的，提交甲方所在地人民法院诉讼解决。本合同一式两份，双方各执一份，具有同等法律效力。'); ?>
<?php sec('附&emsp;&emsp;注', $contract['notes']); ?>
<?php signatures('乙方（服务方）盖章', $my, '甲方（委托方）盖章', $cust); ?>

<?php /* ═══════════ 四、设备租赁合同 ═══════════ */ elseif ($ct === 'rental'): ?>
<?php
$sla = json_decode($contract['sla_terms'] ?? '{}', true) ?: [];
$r_price  = floatval($sla['rent_price']  ?? 0);
$r_cycle  = $sla['cycle_label'] ?? '天';
$r_qty    = intval($sla['total_qty'] ?? 1);
$r_total  = floatval($sla['total_rent'] ?? $amt_raw);
$r_start  = $sla['start_date'] ?? $contract['work_start'] ?? '';
$r_end    = $sla['end_date']   ?? $contract['work_end']   ?? '';
$r_deposit= floatval($sla['deposit'] ?? 0);
$r_days   = ($r_start && $r_end) ? max(1, round((strtotime($r_end)-strtotime($r_start))/86400)) : 0;
$cycle_map= ['天'=>$r_days,'周'=>max(1,round($r_days/7)),'月'=>max(1,round($r_days/30)),'年'=>max(1,round($r_days/365))];
$r_cycles = $cycle_map[$r_cycle] ?? $r_days;
?>
<?php page_no($cn); ?>
<div class="header">
    <h1>设 备 租 赁 合 同</h1>
    <hr class="divider">
    <div class="no">合同编号：<?=$cn?>&emsp;签订日期：<?=$cd?></div>
</div>
<?php
$_ex3 = ($r_start||$r_end)
    ? '<p><strong>租赁期限：</strong>'.e($r_start).' 至 '.e($r_end).($r_days?' （共 '.$r_days.' 天）':'').'</p>' : '';
party_box('出租方（甲方）', $my, $addr, $cp, $cph, '承租方（乙方）', $cust, $caddr, $ccp, $ccph, $_ex3);
?>
<div class="section"><div class="section-content">甲、乙双方根据《中华人民共和国民法典》及相关法律法规，本着平等自愿、诚实信用的原则，就甲方向乙方出租以下设备事宜，协商一致订立本合同。</div></div>
<div class="section"><div class="section-title">第一条&emsp;租赁设备清单</div>
<table class="items-tbl"><tr>
    <th style="width:30px;" class="c">序号</th><th>设备名称</th><th>规格说明</th>
    <th style="width:44px;" class="c">单位</th><th style="width:50px;" class="c">数量</th>
    <th style="width:90px;" class="r">市场估值(¥)</th><th style="width:90px;" class="r">估值小计(¥)</th>
</tr>
<?php foreach($items as $it): $mv=floatval($it['price']??0); $mq=floatval($it['quantity']??1); ?>
<tr>
    <td class="c"><?=intval($it['item_no'])?></td>
    <td><?=e($it['product_name']??'')?></td>
    <td><?=e($it['specification']??'')?></td>
    <td class="c"><?=e($it['unit']??'台')?></td>
    <td class="r"><?=$mq?></td>
    <td class="r"><?=number_format($mv,2)?></td>
    <td class="r"><?=number_format($mv*$mq,2)?></td>
</tr>
<?php endforeach; ?>
<tr><td colspan="6" style="font-size:9pt;color:#777;text-align:right;">※ 市场估值仅供损坏丢失赔偿参考，不作为租金依据</td><td></td></tr>
</table></div>

<div class="section"><div class="section-title">第二条&emsp;租金明细</div>
<table class="items-tbl"><tr>
    <th>租赁起止日期</th>
    <th style="width:70px;" class="c">计费周期</th>
    <th style="width:60px;" class="c">台数</th>
    <th style="width:110px;" class="r">单价(¥/<?=e($r_cycle)?>)</th>
    <th style="width:60px;" class="c">周期数</th>
    <th style="width:110px;" class="r">租金合计(¥)</th>
</tr>
<tr>
    <td><?=$r_start?' '.e($r_start).' 至 '.e($r_end):'（见合同条款）'?></td>
    <td class="c">按<?=e($r_cycle)?></td>
    <td class="c"><?=$r_qty?></td>
    <td class="r"><?=number_format($r_price,2)?></td>
    <td class="c"><?=$r_cycles?></td>
    <td class="r"><strong>¥<?=number_format($r_total,2)?></strong></td>
</tr>
<?php if($r_deposit>0): ?>
<tr><td colspan="5" class="r">押金：</td><td class="r">¥<?=number_format($r_deposit,2)?></td></tr>
<?php endif; ?>
</table>
<?php amount_block('租金合计', $r_total); ?></div>
<div class="pg-break"></div>
<?php sec('第三条&emsp;付款方式', $contract['payment_terms']); ?>
<?php sec('第四条&emsp;设备交付与归还', $contract['delivery_terms'], '设备由甲方送货上门，验收签字后生效；租期届满乙方须将设备完好归还甲方，归还前须清洁设备并确认功能正常。'); ?>
<?php sec_fixed('第五条&emsp;设备使用与维护', '乙方应按照设备正常操作规程合理使用设备，不得擅自拆卸、改装、转借或转租。租赁期间设备的日常保养由乙方负责，因乙方使用不当造成的损坏由乙方承担全部维修费用。'); ?>
<?php sec('第六条&emsp;损坏赔偿条款', $contract['warranty_terms'], '租借期间设备出现非人为故障，由甲方负责维修。人为损坏或丢失按设备市场估值赔偿。'); ?>
<?php sec('第七条&emsp;违约责任', $contract['breach_terms'], '乙方逾期归还设备的，每逾期一日按日租金的双倍支付违约金；乙方逾期支付租金的，每逾期一日加收应付租金1‰的滞纳金；逾期超过30日的，甲方有权终止合同并收回设备。'); ?>
<?php sec('第八条&emsp;争议解决', $contract['dispute_terms'], '双方发生争议，应友好协商解决；协商不成的，提交甲方所在地人民法院诉讼解决。本合同一式两份，双方各执一份，具有同等法律效力。'); ?>
<?php sec('附&emsp;&emsp;注', $contract['notes']); ?>
<?php signatures('甲方（出租方）盖章', $my, '乙方（承租方）盖章', $cust); ?>

<?php /* ═══════════ 五、软件开发合同 ═══════════ */ elseif ($ct === 'software'): ?>
<?php page_no($cn); ?>
<div class="header">
    <h1>软 件 开 发 合 同</h1>
    <hr class="divider">
    <div class="no">合同编号：<?=$cn?>&emsp;签订日期：<?=$cd?></div>
</div>
<?php
$_ex4 = ($contract['work_start']&&$contract['work_end'])
    ? '<p><strong>开发周期：</strong>'.e($contract['work_start']).' 至 '.e($contract['work_end']).'</p>' : '';
party_box('委托方（甲方）', $cust, $caddr, $ccp, $ccph, '开发方（乙方）', $my, $addr, $cp, $cph, $_ex4);
?>
<div class="section"><div class="section-content">甲、乙双方根据《中华人民共和国民法典》及相关法律法规，本着平等自愿、诚实信用的原则，就乙方为甲方开发软件系统事宜，经充分协商，签订本合同。</div></div>
<?php $p=1; if(trim($contract['service_scope']??'')): sec('第一条&emsp;开发内容与功能范围', $contract['service_scope']); $p=2; endif; ?>
<div class="section"><div class="section-title">第<?=$p?>条&emsp;开发费用清单</div>
<?php $calc = render_items($items, '开发模块/功能名称'); ?>
<?php amount_block('开发费用合计', $calc ?: $amt_raw); ?></div>
<div class="pg-break"></div>
<?php $p++;sec('第'.$p.'条&emsp;付款方式', $contract['payment_terms'], '合同签订后支付30%需求定金；完成开发测试提交甲方验收后支付60%；验收通过正式上线后支付余款10%。'); ?>
<?php $p++;sec('第'.$p.'条&emsp;验收标准', $contract['acceptance_terms'], '乙方完成开发并提交测试版本，甲方在10个工作日内组织验收。以双方确认的需求文档为验收依据，验收合格后双方签署验收确认书。'); ?>
<?php $p++;sec('第'.$p.'条&emsp;维护与培训', $contract['warranty_terms'], '软件验收上线后提供6个月免费技术支持，包括功能性bug修复及基本操作培训。超出范围的二次开发及定制功能需另行签订补充协议。'); ?>
<?php $p++;sec_fixed('第'.$p.'条&emsp;知识产权', '乙方为甲方定制开发的软件系统（含源代码、文档、界面设计），知识产权归甲方所有，甲方付清全部费用后乙方交付源代码。乙方使用的通用框架、第三方组件的知识产权归其原始权利人所有。'); ?>
<?php $p++;sec_fixed('第'.$p.'条&emsp;保密条款', '乙方对开发过程中接触到的甲方业务数据、技术资料及商业信息严格保密，未经甲方书面许可不得向第三方披露或用于其他项目。合同终止后保密义务继续有效，期限为5年。'); ?>
<?php $p++;sec('第'.$p.'条&emsp;违约责任', $contract['breach_terms'], '乙方无故逾期交付的，每逾期一日按合同总额0.3‰支付违约金，逾期超30日甲方有权解除合同并追偿损失；甲方无故中止项目的，已支付款项不予退还，乙方不承担未完成部分的开发义务。'); ?>
<?php $p++;sec('第'.$p.'条&emsp;争议解决', $contract['dispute_terms'], '双方发生争议，应友好协商解决；协商不成的，提交甲方所在地人民法院诉讼解决。本合同一式两份，双方各执一份，具有同等法律效力。'); ?>
<?php sec('附&emsp;&emsp;注', $contract['notes']); ?>
<?php signatures('乙方（开发方）盖章', $my, '甲方（委托方）盖章', $cust); ?>

<?php /* ═══════════ 六、设备借用合同 ═══════════ */ elseif ($ct === 'loan'): ?>
<?php page_no($cn); ?>
<div class="header">
    <h1>设 备 借 用 合 同</h1>
    <hr class="divider">
    <div class="no">合同编号：<?=$cn?>&emsp;签订日期：<?=$cd?></div>
</div>
<?php
$_ex5 = ($contract['work_start']&&$contract['work_end'])
    ? '<p><strong>借用期限：</strong>'.e($contract['work_start']).' 至 '.e($contract['work_end']).'</p>' : '';
party_box('出借方（甲方）', $my, $addr, $cp, $cph, '借用方（乙方）', $cust, $caddr, $ccp, $ccph, $_ex5);
?>
<div class="section"><div class="section-content">甲、乙双方本着友好合作、诚实信用的原则，就甲方向乙方出借以下设备事宜，协商一致订立本合同。</div></div>
<div class="section"><div class="section-title">第一条&emsp;借用设备清单</div>
<?php render_items($items, '设备名称'); ?>
</div>
<div class="pg-break"></div>
<?php sec('第二条&emsp;借用期限及归还', $contract['delivery_terms'], '乙方借用期满后须按时将设备完好归还甲方，归还前须清洁设备并确认功能正常。如需续借，须提前7日告知甲方并重新签订协议。'); ?>
<?php sec_fixed('第三条&emsp;设备使用与保管', '乙方应妥善保管设备，按正常操作规程合理使用，不得擅自拆卸、改装、转借或转让。借用期间如发生设备损坏、丢失，乙方须按原值赔偿。'); ?>
<?php sec('第四条&emsp;损坏赔偿条款', $contract['warranty_terms'], '借用期间设备因乙方使用不当造成损坏或丢失，乙方须按设备市场价值全额赔偿。正常使用磨损不在赔偿范围内。'); ?>
<?php sec('第五条&emsp;违约责任', $contract['breach_terms'], '乙方逾期未归还设备的，每逾期一日按设备市值0.1‰支付违约金；造成设备损毁、灭失的，按市场估价全额赔偿，甲方有权追究法律责任。'); ?>
<?php sec('第六条&emsp;争议解决', $contract['dispute_terms'], '双方发生争议，应友好协商解决；协商不成的，提交甲方所在地人民法院诉讼解决。本合同一式两份，双方各执一份，具有同等法律效力。'); ?>
<?php sec('附&emsp;&emsp;注', $contract['notes']); ?>
<?php signatures('甲方（出借方）盖章', $my, '乙方（借用方）盖章', $cust); ?>

<?php endif; ?>
</div>

<script>
// 甲乙方信息框：每格文字都不许换行，内容太长就自动缩小字号直到装得下（最小到6pt为止），
// 覆盖公司全称/地址/联系人/联系电话所有行
function fitPartyCells() {
    document.querySelectorAll('.party-cell').forEach(function(cell) {
        let fs = 11;
        cell.style.fontSize = fs + 'pt';
        let guard = 0;
        while (cell.scrollWidth > cell.clientWidth && fs > 7 && guard < 20) {
            fs -= 0.3;
            cell.style.fontSize = fs + 'pt';
            guard++;
        }
    });
}
window.addEventListener('load', fitPartyCells);

// 添加公章
function addSeal(path) {
    const old = document.querySelector('.seal');
    if (old) old.remove();

    const seal = document.createElement('div');
    seal.className = 'seal';
    seal.innerHTML = `<img src="${path}">`;

    const page = document.getElementById('page');
    page.appendChild(seal);

    const sigFirstCell = document.querySelector('.signatures td.sig-name-cell:first-child');
    const sealWord = sigFirstCell.querySelector('.seal-word'); // "盖章"二字
    const valSpan  = sigFirstCell.querySelector('.lv-val');    // 公司名称
    const pageRect = page.getBoundingClientRect();
    const wordRect = sealWord.getBoundingClientRect();
    const valRect  = valSpan.getBoundingClientRect();

    const sealSize = 120;
    seal.style.position = 'absolute';
    // 横向以公司名称左边为基准，往左再让一点，避免导出PDF时公章偏右
    seal.style.left = (valRect.left - pageRect.left - 30) + 'px';
    // 纵向以"盖章"字样与公司名称的交界处为中心，让公章上半部压住"盖章"二字，下半部盖住公司名称
    seal.style.top  = ((wordRect.bottom + valRect.top) / 2 - pageRect.top - sealSize / 2) + 'px';
    seal.style.right  = 'auto';
    seal.style.bottom = 'auto';

    seal.onmousedown = function(e) {
        const rect   = seal.getBoundingClientRect();
        const parent = seal.parentElement.getBoundingClientRect();
        const shiftX = e.clientX - rect.left;
        const shiftY = e.clientY - rect.top;

        function move(e) {
            seal.style.left = (e.clientX - parent.left - shiftX) + 'px';
            seal.style.top  = (e.clientY - parent.top  - shiftY) + 'px';
        }

        document.addEventListener('mousemove', move);
        document.onmouseup = function() {
            document.removeEventListener('mousemove', move);
            document.onmouseup = null;
        };
    };
    seal.ondragstart = () => false;
}

// 删除合同
function deleteContract() {
    sapConfirm('确定要删除这份合同吗？<br><br>此操作不可恢复！',
    function() {
        fetch('contract_delete_new.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=<?php echo $id; ?>'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                sapToast('删除成功！', 'success');
                setTimeout(() => window.location.href = 'contracts.php', 1200);
            } else {
                sapToast('删除失败：' + (data.error || '未知错误'), 'error');
            }
        })
        .catch(err => {
            sapToast('删除失败，请检查网络连接', 'error');
        });
    }, {danger:true, title:'删除合同', okText:'确认删除'});
}

// 导出Word
function exportWord() {
    const contractId = <?php echo $id; ?>;
    window.location.href = 'contract_export_word.php?id=' + contractId;
}

// 导出Excel
function exportExcel() {
    const contractId = <?php echo $id; ?>;
    window.location.href = 'contract_export_excel.php?id=' + contractId;
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

</div><!-- end contract-page-body -->
</body>
</html>
<?php $conn->close(); ?>
