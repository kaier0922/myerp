<?php
/**
 * 文件名: sales_report.php
 * 版本: 1.0
 * 功能: 销售统计分析（基于送货单）
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── 筛选参数 ──
$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? 0);
$cid   = intval($_GET['cid']   ?? 0);

// 可选年份
$years = $conn->query("SELECT DISTINCT YEAR(delivery_date) as y FROM deliveries WHERE " . tenantWhere() . " ORDER BY y DESC")->fetch_all(MYSQLI_ASSOC);
$years = array_column($years, 'y');
if (!in_array($year, $years)) $years[] = $year;

// WHERE 条件
$where = ["d.status NOT IN ('cancelled','failed')", tenantWhere("d")];
$params = []; $types = '';
$where[] = "YEAR(d.delivery_date)=?"; $params[]=$year; $types.='i';
if ($month) { $where[] = "MONTH(d.delivery_date)=?"; $params[]=$month; $types.='i'; }
if ($cid)   { $where[] = "d.customer_id=?"; $params[]=$cid; $types.='i'; }
$ws = implode(' AND ', $where);

function query($conn, $sql, $types='', $params=[]) {
    if (!$params) return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ── 总览 ──
$overview = query($conn,
    "SELECT COUNT(*) as order_cnt,
            SUM(d.total_amount) as total_revenue,
            SUM(d.paid_amount)  as total_paid,
            SUM(d.total_amount-d.paid_amount) as total_unpaid,
            COUNT(DISTINCT d.customer_id) as customer_cnt
     FROM deliveries d WHERE $ws", $types, $params)[0];

// ── 月度趋势（仅在未选月份时显示） ──
$monthly = [];
if (!$month) {
    $monthly = query($conn,
        "SELECT MONTH(d.delivery_date) as m,
                COUNT(*) as order_cnt,
                SUM(d.total_amount) as revenue
         FROM deliveries d WHERE $ws
         GROUP BY m ORDER BY m", $types, $params);
}

// ── 客户排行 TOP10 ──
$cus_rank = query($conn,
    "SELECT d.customer_name,
            COUNT(*) as order_cnt,
            SUM(d.total_amount) as revenue,
            SUM(d.paid_amount) as paid
     FROM deliveries d WHERE $ws
     GROUP BY d.customer_id, d.customer_name
     ORDER BY revenue DESC LIMIT 10", $types, $params);

// ── 产品销售排行 TOP15（基于 delivery_items） ──
$prod_rank = query($conn,
    "SELECT di.product_name,
            SUM(di.quantity) as total_qty,
            SUM(di.subtotal) as total_amount
     FROM delivery_items di
     JOIN deliveries d ON di.delivery_id=d.id
     WHERE $ws
     GROUP BY di.product_name
     ORDER BY total_amount DESC LIMIT 15", $types, $params);

// ── 送货人业绩 ──
$person_rank = query($conn,
    "SELECT IFNULL(d.delivery_person,'未指定') as person,
            COUNT(*) as order_cnt,
            SUM(d.total_amount) as revenue
     FROM deliveries d WHERE $ws
     GROUP BY d.delivery_person
     ORDER BY revenue DESC", $types, $params);

// ── 客户列表（用于筛选） ──
$customers = query($conn,
    "SELECT DISTINCT d.customer_id as id, d.customer_name as name
     FROM deliveries d WHERE d.customer_id IS NOT NULL AND " . tenantWhere("d") . " AND 1" . "
     ORDER BY d.customer_name");

$conn->close();

// 月份标签
$month_labels = ['','1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
$monthly_map = []; foreach ($monthly as $r) $monthly_map[$r['m']] = $r;
$max_rev = max(array_column($monthly, 'revenue') ?: [1]);
$max_cus = max(array_column($cus_rank, 'revenue') ?: [1]);
$max_prod= max(array_column($prod_rank, 'total_amount') ?: [1]);
?>
<?php $page_title = '销售统计 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.
.btn-nav{padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:13px;text-decoration:none;}
.btn-nav:hover{background:#edf2f7;}

/* 筛选栏 */
.filter-bar{background:white;border-radius:12px;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:24px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
.fg label{display:block;font-size:12px;font-weight:700;color:#4a5568;margin-bottom:6px;}
.fg select,.fg input{padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;}
.fg select:focus{border-color:#1f6dad;}
.btn-filter{padding:9px 20px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;}
.btn-clear{padding:9px 14px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;}

/* 总览卡片 */
.overview-row{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.ov-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid var(--c,#1f6dad);}
.ov-card .v{font-size:22px;font-weight:700;color:var(--c,#1a202c);margin-bottom:4px;}
.ov-card .l{font-size:12px;color:#718096;}
.c1{--c:#1f6dad;}.c2{--c:#1f6dad;}.c3{--c:#3b82f6;}.c4{--c:#ef4444;}.c5{--c:#f59e0b;}

/* 两列布局 */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;}
.three-col{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;}

/* 卡片 */
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;}
.card-title{font-size:15px;font-weight:700;color:#1a202c;}
.card-

/* 月度柱状图 */
.bar-chart{display:flex;align-items:flex-end;gap:8px;height:160px;padding-bottom:24px;border-bottom:2px solid #f0f0f0;position:relative;}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;}
.bar-wrap{width:100%;display:flex;align-items:flex-end;justify-content:center;height:130px;}
.bar{width:70%;min-height:2px;background:linear-gradient(180deg,#1f6dad,#135286);border-radius:4px 4px 0 0;transition:all .3s;cursor:pointer;position:relative;}
.bar:hover{opacity:.85;}
.bar-tip{position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);background:#1a202c;color:white;font-size:10px;padding:3px 6px;border-radius:4px;white-space:nowrap;display:none;}
.bar:hover .bar-tip{display:block;}
.bar-label{font-size:11px;color:#718096;margin-top:2px;}
.bar-val{font-size:10px;color:#1f6dad;font-weight:600;}

/* 排行表 */
.rank-
.rank-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0;}
.rank-row:last-child{border-bottom:none;}
.rank-no{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.rn1{background:#f59e0b;color:white;}
.rn2{background:#94a3b8;color:white;}
.rn3{background:#cd7f32;color:white;}
.rn-{background:#f1f5f9;color:#718096;}
.rank-name{flex:1;font-size:13px;font-weight:500;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.rank-bar-wrap{width:80px;background:#f1f5f9;border-radius:4px;height:6px;flex-shrink:0;}
.rank-bar{height:100%;border-radius:4px;background:linear-gradient(90deg,#1f6dad,#135286);}
.rank-val{font-size:12px;font-weight:600;color:#1f6dad;min-width:70px;text-align:right;flex-shrink:0;}
.rank-sub{font-size:11px;color:#a0aec0;}

/* 空状态 */
.empty{text-align:center;padding:32px;color:#a0aec0;font-size:13px;}

/* 标签 */
.period-label{font-size:13px;color:#1f6dad;font-weight:600;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="page-header">
        <div class="page-title">📊 销售统计</div>
        <a href="deliveries.php" class="btn-nav">← 返回送货管理</a>
        <span class="period-label">
            <?=$year?>年<?=$month?$month_labels[$month]:'全年'?>
            <?=$cid?'· '.e(array_column($customers,'name','id')[$cid]??''):''; ?>
        </span>
    </div>

    <!-- 筛选栏 -->
    <form method="GET" class="filter-bar">
        <div class="fg">
            <label>年份</label>
            <select name="year">
                <?php foreach ($years as $y): ?>
                <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?>年</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>月份</label>
            <select name="month">
                <option value="0">全年</option>
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=$month_labels[$m]?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="fg">
            <label>客户</label>
            <select name="cid">
                <option value="0">全部客户</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?=$c['id']?>" <?=$c['id']==$cid?'selected':''?>><?=e($c['name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">🔍 查询</button>
        <a href="sales_report.php?year=<?=$year?>" class="btn-clear">重置</a>
    </form>

    <!-- 总览 -->
    <div class="overview-row">
        <div class="ov-card c1">
            <div class="v"><?=number_format($overview['order_cnt']??0)?></div>
            <div class="l">📦 送货单数</div>
        </div>
        <div class="ov-card c2">
            <div class="v">¥<?=number_format($overview['total_revenue']??0,2)?></div>
            <div class="l">💰 总销售额</div>
        </div>
        <div class="ov-card c3">
            <div class="v">¥<?=number_format($overview['total_paid']??0,2)?></div>
            <div class="l">✅ 已收款</div>
        </div>
        <div class="ov-card c4">
            <div class="v">¥<?=number_format($overview['total_unpaid']??0,2)?></div>
            <div class="l">⏳ 未收款</div>
        </div>
        <div class="ov-card c5">
            <div class="v"><?=number_format($overview['customer_cnt']??0)?></div>
            <div class="l">🏢 服务客户数</div>
        </div>
    </div>

    <!-- 月度趋势（全年时显示） -->
    <?php if (!$month && !empty($monthly)): ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div class="card-title">📈 月度销售趋势 - <?=$year?>年</div>
        </div>
        <div class="card-body">
            <div class="bar-chart">
                <?php for ($m=1;$m<=12;$m++):
                    $rev = $monthly_map[$m]['revenue'] ?? 0;
                    $cnt = $monthly_map[$m]['order_cnt'] ?? 0;
                    $h   = $max_rev > 0 ? round($rev/$max_rev*120) : 0;
                ?>
                <div class="bar-col">
                    <div class="bar-wrap">
                        <div class="bar" style="height:<?=$h?>px;">
                            <div class="bar-tip"><?=$month_labels[$m]?>: ¥<?=number_format($rev,0)?> (<?=$cnt?>单)</div>
                        </div>
                    </div>
                    <div class="bar-label"><?=$m?>月</div>
                    <?php if ($rev>0): ?><div class="bar-val">¥<?=number_format($rev/10000,1)?>W</div><?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 客户排行 + 送货人业绩 -->
    <div class="two-col">
        <!-- 客户销售排行 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏆 客户销售排行 TOP10</div>
            </div>
            <div class="card-body" style="padding:12px 20px;">
                <?php if (empty($cus_rank)): ?>
                <div class="empty">暂无数据</div>
                <?php else: ?>
                <div class="rank-table">
                <?php foreach ($cus_rank as $i => $row):
                    $pct = $max_cus > 0 ? round($row['revenue']/$max_cus*80) : 0;
                    $no_cls = $i==0?'rn1':($i==1?'rn2':($i==2?'rn3':'rn-'));
                ?>
                <div class="rank-row">
                    <div class="rank-no <?=$no_cls?>"><?=$i+1?></div>
                    <div>
                        <div class="rank-name"><?=e($row['customer_name'])?></div>
                        <div class="rank-sub"><?=$row['order_cnt']?> 单</div>
                    </div>
                    <div class="rank-bar-wrap"><div class="rank-bar" style="width:<?=$pct?>%"></div></div>
                    <div class="rank-val">¥<?=number_format($row['revenue'],0)?></div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 送货人业绩 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 送货人业绩</div>
            </div>
            <div class="card-body" style="padding:12px 20px;">
                <?php if (empty($person_rank)): ?>
                <div class="empty">暂无数据</div>
                <?php else:
                $max_p = max(array_column($person_rank,'revenue')?:[1]);
                foreach ($person_rank as $i => $row):
                    $pct = $max_p > 0 ? round($row['revenue']/$max_p*80) : 0;
                    $no_cls = $i==0?'rn1':($i==1?'rn2':($i==2?'rn3':'rn-'));
                ?>
                <div class="rank-row">
                    <div class="rank-no <?=$no_cls?>"><?=$i+1?></div>
                    <div>
                        <div class="rank-name"><?=e($row['person'])?></div>
                        <div class="rank-sub"><?=$row['order_cnt']?> 单</div>
                    </div>
                    <div class="rank-bar-wrap"><div class="rank-bar" style="width:<?=$pct?>%"></div></div>
                    <div class="rank-val">¥<?=number_format($row['revenue'],0)?></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- 产品销售排行 -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📦 产品销售排行 TOP15</div>
        </div>
        <div class="card-body" style="padding:12px 20px;">
            <?php if (empty($prod_rank)): ?>
            <div class="empty">暂无明细数据</div>
            <?php else: ?>
            <div class="rank-table">
            <?php foreach ($prod_rank as $i => $row):
                $pct = $max_prod > 0 ? round($row['total_amount']/$max_prod*120) : 0;
                $no_cls = $i==0?'rn1':($i==1?'rn2':($i==2?'rn3':'rn-'));
            ?>
            <div class="rank-row">
                <div class="rank-no <?=$no_cls?>"><?=$i+1?></div>
                <div style="flex:1;min-width:0;">
                    <div class="rank-name"><?=e($row['product_name'])?></div>
                    <div class="rank-sub">销量 <?=number_format($row['total_qty'])?> 件</div>
                </div>
                <div class="rank-bar-wrap" style="width:120px;"><div class="rank-bar" style="width:<?=$pct?>%"></div></div>
                <div class="rank-val">¥<?=number_format($row['total_amount'],0)?></div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>
