<?php
/**
 * profit_report.php — 利润报表（基于审核送货单）
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid      = (int)$_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$conn     = getDBConnection();

function e($v)     { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function money($v) { return '¥' . number_format((float)$v, 2); }

// ── 筛选参数 ──────────────────────────────────────────────
$view  = in_array($_GET['view'] ?? '', ['order','day','week','month','year'])
         ? $_GET['view'] : 'month';
$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? 0); // 0 = 全年

// 可用年份列表
$yr_res = $conn->query("SELECT DISTINCT YEAR(delivery_date) y FROM deliveries
                        WHERE user_id=$uid AND approved=1 ORDER BY y DESC");
$years  = array_column($yr_res->fetch_all(MYSQLI_ASSOC), 'y');
if (!in_array($year, $years)) array_unshift($years, $year);
rsort($years);

// ── 构建查询条件 ──────────────────────────────────────────
$conds  = ["d.approved=1", "d.user_id=$uid"];
$params = []; $types = '';
if ($year)    { $conds[] = "YEAR(d.delivery_date)=?";  $params[] = $year;  $types .= 'i'; }
if ($month>0) { $conds[] = "MONTH(d.delivery_date)=?"; $params[] = $month; $types .= 'i'; }
$where = implode(' AND ', $conds);

// ── 主查询：每张送货单营业额 + 成本 ──────────────────────
$sql = "
    SELECT d.id, d.delivery_no, d.delivery_date, d.customer_name, d.quote_id,
           d.goods_amount AS revenue,
           ROUND(COALESCE(SUM(qi.cost_subtotal), 0) * COALESCE(q.unit_qty, 1), 2) AS cost
    FROM deliveries d
    LEFT JOIN quotes      q  ON q.id       = d.quote_id
    LEFT JOIN quote_items qi ON qi.quote_id = d.quote_id
    WHERE $where
    GROUP BY d.id
    ORDER BY d.delivery_date DESC, d.id DESC
";
if ($types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
$conn->close();

// ── 逐行计算利润 ──────────────────────────────────────────
foreach ($rows as &$r) {
    $r['has_cost'] = $r['quote_id'] && $r['cost'] > 0;
    $r['profit']   = $r['has_cost'] ? round($r['revenue'] - $r['cost'], 2) : null;
    $r['margin']   = ($r['has_cost'] && $r['revenue'] > 0)
                     ? round($r['profit'] / $r['revenue'] * 100, 1) : null;
}
unset($r);

// ── 总计 ──────────────────────────────────────────────────
$total_revenue  = array_sum(array_column($rows, 'revenue'));
$cost_rows      = array_filter($rows, fn($r) => $r['has_cost']);
$total_cost     = array_sum(array_column($cost_rows, 'cost'));
$total_profit   = $total_revenue - $total_cost;
$total_margin   = $total_revenue > 0 ? round($total_profit / $total_revenue * 100, 1) : 0;
$no_cost_count  = count(array_filter($rows, fn($r) => !$r['has_cost']));
$total_orders   = count($rows);

// ── 周期聚合（按日/周/月/年视图）─────────────────────────
$periods = [];
foreach ($rows as $r) {
    $ts  = strtotime($r['delivery_date']);
    $dow = (int)date('N', $ts); // 1=周一 7=周日
    switch ($view) {
        case 'day':
            $key   = $r['delivery_date'];
            $label = date('Y-m-d', $ts) . '（' . ['','一','二','三','四','五','六','日'][$dow] . '）';
            break;
        case 'week':
            $mon_ts = $ts - ($dow - 1) * 86400;
            $sun_ts = $mon_ts + 6 * 86400;
            $key    = date('oW', $ts);
            $label  = date('Y', $ts) . '年第' . date('W', $ts) . '周'
                    . '（' . date('m/d', $mon_ts) . '~' . date('m/d', $sun_ts) . '）';
            break;
        case 'month':
            $key   = date('Ym', $ts);
            $label = date('Y年n月', $ts);
            break;
        case 'year':
            $key   = date('Y', $ts);
            $label = date('Y年', $ts);
            break;
        default:
            continue 2;
    }
    if (!isset($periods[$key])) {
        $periods[$key] = ['label'=>$label,'count'=>0,'revenue'=>0,'cost'=>0,'cost_count'=>0];
    }
    $periods[$key]['count']++;
    $periods[$key]['revenue'] = round($periods[$key]['revenue'] + $r['revenue'], 2);
    if ($r['has_cost']) {
        $periods[$key]['cost']       = round($periods[$key]['cost'] + $r['cost'], 2);
        $periods[$key]['cost_count']++;
    }
}
foreach ($periods as &$p) {
    $p['profit'] = round($p['revenue'] - $p['cost'], 2);
    $p['margin'] = $p['revenue'] > 0 ? round($p['profit'] / $p['revenue'] * 100, 1) : 0;
}
unset($p);
krsort($periods); // 最新在前

$month_names = [1=>'一月',2=>'二月',3=>'三月',4=>'四月',5=>'五月',6=>'六月',
                7=>'七月',8=>'八月',9=>'九月',10=>'十月',11=>'十一月',12=>'十二月'];
?>
<?php $page_title = '利润报表'; include __DIR__ . '/head.php'; ?>
<style>
.navbar-user a{color:#1f6dad;text-decoration:none;}
.
.btn-nav{padding:8px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;text-decoration:none;font-size:13px;transition:all .2s;}
.btn-nav:hover{border-color:#1f6dad;color:#1f6dad;}
.content{padding:20px 32px 40px;}

/* 视图切换 */
.view-tabs{display:flex;gap:4px;background:#e2e8f0;padding:4px;border-radius:10px;width:fit-content;}
.view-tab{padding:6px 18px;border-radius:7px;font-size:13px;font-weight:500;color:#718096;text-decoration:none;transition:all .2s;}
.view-tab.active{background:#fff;color:#1f6dad;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.view-tab:hover:not(.active){color:#1f6dad;}

/* 筛选栏 */
.filter-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0;}
.filter-bar select,.filter-bar a{padding:7px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#4a5568;background:#fff;text-decoration:none;cursor:pointer;}
.filter-bar select:focus{outline:none;border-color:#1f6dad;}
.filter-bar .btn-reset{color:#e53e3e;border-color:#fed7d7;}
.filter-bar .btn-reset:hover{background:#fff5f5;}

/* 汇总卡片 */
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.card{background:#fff;border-radius:12px;padding:20px 24px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.card-label{font-size:12px;font-weight:600;color:#a0aec0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;}
.card-value{font-size:26px;font-weight:700;color:#1a202c;}
.card-value.green{color:#16a34a;}
.card-value.red{color:#dc2626;}
.card-sub{font-size:12px;color:#a0aec0;margin-top:6px;}
.card.warn{border-color:#fbd38d;background:#fffbeb;}

/* 表格 */
.table-wrap{background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.table-wrap
.table-wrap thead{background:linear-gradient(135deg,#1f6dad,#135286);color:#fff;}
.table-wrap
.table-wrap
.table-wrap tbody tr:last-child
.table-wrap tbody
.badge-margin{display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:600;}
.badge-high{background:#d1fae5;color:#065f46;}
.badge-mid{background:#fef3c7;color:#92400e;}
.badge-low{background:#fee2e2;color:#991b1b;}
.badge-na{background:#f1f5f9;color:#94a3b8;}
.text-right{text-align:right;}
.text-center{text-align:center;}
.no-cost-warn{font-size:11px;color:#d97706;margin-left:4px;}
.empty-state div{font-size:40px;margin-bottom:12px;}
.notice{background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;font-size:13px;color:#92400e;margin-bottom:16px;}
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="page-header">
        <div class="page-title">📈 利润报表</div>
        <div class="view-tabs">
            <?php foreach (['order'=>'按单','day'=>'按日','week'=>'按周','month'=>'按月','year'=>'按年'] as $v=>$vl): ?>
            <a href="?view=<?=$v?>&year=<?=$year?>&month=<?=$month?>"
               class="view-tab <?=$view===$v?'active':''?>"><?=$vl?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content">

        <!-- 筛选栏 -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="<?=e($view)?>">
            <select name="year" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?>年</option>
                <?php endforeach; ?>
            </select>
            <?php if (in_array($view, ['order','day','week'])): ?>
            <select name="month" onchange="this.form.submit()">
                <option value="0" <?=$month==0?'selected':''?>>全年</option>
                <?php foreach ($month_names as $mn=>$ml): ?>
                <option value="<?=$mn?>" <?=$mn==$month?'selected':''?>><?=$ml?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="month" value="0">
            <?php endif; ?>
            <?php if ($month > 0): ?>
            <a href="?view=<?=e($view)?>&year=<?=$year?>" class="btn-reset">✕ 清除月份</a>
            <?php endif; ?>
        </form>

        <!-- 无成本数据提示 -->
        <?php if ($no_cost_count > 0): ?>
        <div class="notice">
            ⚠️ 本期 <strong><?=$no_cost_count?></strong> 张送货单无关联报价单或成本为零，利润数据不含这些单据，营业额仍完整统计。
        </div>
        <?php endif; ?>

        <!-- 汇总卡片 -->
        <div class="cards">
            <div class="card">
                <div class="card-label">总营业额</div>
                <div class="card-value"><?=money($total_revenue)?></div>
                <div class="card-sub"><?=$total_orders?> 张送货单</div>
            </div>
            <div class="card">
                <div class="card-label">总成本</div>
                <div class="card-value"><?=money($total_cost)?></div>
                <div class="card-sub"><?=count($cost_rows)?> 张有成本数据</div>
            </div>
            <div class="card">
                <div class="card-label">毛利润</div>
                <div class="card-value <?=$total_profit>=0?'green':'red'?>"><?=money($total_profit)?></div>
                <div class="card-sub">基于有成本数据的单据</div>
            </div>
            <div class="card <?=$no_cost_count>0?'warn':''?>">
                <div class="card-label">毛利率</div>
                <div class="card-value <?=$total_margin>=20?'green':($total_margin>=10?'':'red')?>"><?=$total_margin?>%</div>
                <div class="card-sub">毛利润 ÷ 总营业额</div>
            </div>
        </div>

        <!-- 数据表格 -->
        <?php if ($view === 'order'): ?>
        <!-- 按单视图 -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>送货单号</th>
                        <th>日期</th>
                        <th>客户</th>
                        <th class="text-right">营业额</th>
                        <th class="text-right">成本</th>
                        <th class="text-right">毛利润</th>
                        <th class="text-center">毛利率</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div>📭</div>暂无数据</div></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?=e($r['delivery_no'])?></strong></td>
                        <td><?=e($r['delivery_date'])?></td>
                        <td><?=e($r['customer_name'])?></td>
                        <td class="text-right"><?=money($r['revenue'])?></td>
                        <td class="text-right">
                            <?php if ($r['has_cost']): ?>
                                <?=money($r['cost'])?>
                            <?php else: ?>
                                <span class="badge-na" style="font-size:12px;padding:2px 8px;border-radius:4px;">—</span>
                                <?php if (!$r['quote_id']): ?>
                                <span class="no-cost-warn">⚠️无报价单</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($r['profit'] !== null): ?>
                                <strong style="color:<?=$r['profit']>=0?'#16a34a':'#dc2626'?>"><?=money($r['profit'])?></strong>
                            <?php else: ?>
                                <span style="color:#cbd5e0">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['margin'] !== null): ?>
                                <?php $mc = $r['margin']>=20?'badge-high':($r['margin']>=10?'badge-mid':'badge-low'); ?>
                                <span class="badge-margin <?=$mc?>"><?=$r['margin']?>%</span>
                            <?php else: ?>
                                <span class="badge-margin badge-na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="delivery_view.php?id=<?=$r['id']?>" style="color:#1f6dad;font-size:13px;text-decoration:none;">查看</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- 聚合视图（日/周/月/年）-->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?=['day'=>'日期','week'=>'周次','month'=>'月份','year'=>'年份'][$view]?></th>
                        <th class="text-center">送货单数</th>
                        <th class="text-right">营业额</th>
                        <th class="text-right">成本</th>
                        <th class="text-right">毛利润</th>
                        <th class="text-center">毛利率</th>
                        <th class="text-center">成本覆盖</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($periods)): ?>
                    <tr><td colspan="7"><div class="empty-state"><div>📭</div>暂无数据</div></td></tr>
                <?php else: foreach ($periods as $p): ?>
                    <tr>
                        <td><strong><?=e($p['label'])?></strong></td>
                        <td class="text-center"><?=$p['count']?></td>
                        <td class="text-right"><?=money($p['revenue'])?></td>
                        <td class="text-right"><?=$p['cost']>0?money($p['cost']):'<span style="color:#cbd5e0">—</span>'?></td>
                        <td class="text-right">
                            <?php if ($p['cost'] > 0): ?>
                                <strong style="color:<?=$p['profit']>=0?'#16a34a':'#dc2626'?>"><?=money($p['profit'])?></strong>
                            <?php else: ?>
                                <span style="color:#cbd5e0">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($p['cost'] > 0): ?>
                                <?php $mc = $p['margin']>=20?'badge-high':($p['margin']>=10?'badge-mid':'badge-low'); ?>
                                <span class="badge-margin <?=$mc?>"><?=$p['margin']?>%</span>
                            <?php else: ?>
                                <span class="badge-margin badge-na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center" style="font-size:12px;color:#718096;">
                            <?=$p['cost_count']?>/<?=$p['count']?> 张
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- .content -->
</div><!-- .main -->
</body>
</html>
