<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';

// 筛选参数
$type    = $_GET['type']   ?? '';
$status  = $_GET['status'] ?? '';
$kw      = trim($_GET['kw'] ?? '');

$where  = "WHERE " . tenantWhere();
$params = [];
$types  = "";

if ($type)   { $where .= " AND type=?";            $params[]=$type;   $types.="s"; }
if ($status) { $where .= " AND status=?";           $params[]=$status; $types.="s"; }
if ($kw)     { $where .= " AND (contract_no LIKE ? OR customer_name LIKE ? OR contact_name LIKE ?)";
               $kw2="%$kw%"; $params=array_merge($params,[$kw2,$kw2,$kw2]); $types.="sss"; }

// 列表
$stmt = $conn->prepare("SELECT * FROM rental_contracts $where ORDER BY
    CASE WHEN status='active' THEN 0 ELSE 1 END,
    end_date ASC, created_at DESC");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 统计
$stats = $conn->query("SELECT
    COUNT(*) as total,
    SUM(type='rental' AND status='active') as renting,
    SUM(type='loan'   AND status='active') as loaning,
    SUM(status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY)) as expiring,
    SUM(status='active' AND end_date < CURDATE()) as overdue
FROM rental_contracts WHERE " . tenantWhere())->fetch_assoc();

// 每个合同的设备数
$item_counts = [];
$ic = $conn->query("SELECT contract_id, COUNT(*) as cnt FROM rental_items GROUP BY contract_id");
if ($ic) { while ($r=$ic->fetch_assoc()) $item_counts[$r['contract_id']] = $r['cnt']; }

function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
function daysDiff($date) {
    $diff = (strtotime($date) - time()) / 86400;
    return intval($diff);
}

// 删除合同（AJAX）
if (isset($_POST['action']) && $_POST['action'] === 'delete_contract') {
    header('Content-Type: application/json');
    $del_id = intval($_POST['id'] ?? 0);
    if ($del_id > 0) {
        // 只允许删除已归还/已取消的合同
        $del_uid = (int)$_SESSION['user_id'];
        $chk = $conn->prepare("SELECT status FROM rental_contracts WHERE id=? AND user_id=?");
        $chk->bind_param("ii", $del_id, $del_uid);
        $chk->execute();
        $chk_r = $chk->get_result()->fetch_assoc();
        if ($chk_r && in_array($chk_r['status'], ['returned', 'cancelled'])) {
            $conn->query("DELETE FROM rental_items WHERE contract_id=$del_id");
            $conn->query("DELETE FROM rental_payments WHERE contract_id=$del_id");
            $conn->query("DELETE FROM rental_returns WHERE contract_id=$del_id");
            $conn->query("DELETE FROM rental_contracts WHERE id=$del_id");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'msg' => '只能删除已归还或已取消的合同']);
        }
    } else {
        echo json_encode(['success' => false, 'msg' => '参数错误']);
    }
    $conn->close(); exit;
}

// 复制合同（AJAX）
if (isset($_POST['action']) && $_POST['action'] === 'copy_contract') {
    header('Content-Type: application/json');
    $copy_id = intval($_POST['id'] ?? 0);
    if ($copy_id > 0) {
        $copy_uid2 = (int)$_SESSION['user_id'];
        $orig = $conn->query("SELECT * FROM rental_contracts WHERE id=$copy_id AND user_id=$copy_uid2")->fetch_assoc();
        if ($orig) {
            // 生成新合同编号
            $prefix     = $orig['type'] === 'rental' ? 'RT' : 'LN';
            $new_no     = $prefix . date('YmdHis');
            // 日期：从今天起，保留原合同租期长度
            $orig_days  = max(1, round((strtotime($orig['end_date']) - strtotime($orig['start_date'])) / 86400));
            $new_start  = date('Y-m-d');
            $new_end    = date('Y-m-d', strtotime("+{$orig_days} days"));

            $conn->begin_transaction();
            try {
                $ins = $conn->prepare("INSERT INTO rental_contracts
                    (contract_no, type, customer_id, customer_name, contact_name, contact_phone,
                     start_date, end_date, rent_cycle, rent_price, deposit, deposit_status,
                     deposit_paid_amount, total_rent, paid_rent, status, notes, created_by, user_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,'active',?,?,?)");
                // 重新计算总租金
                $new_total = 0;
                if ($orig['type'] === 'rental' && $orig['rent_price'] > 0) {
                    if ($orig['rent_cycle'] === 'month') {
                        $months = max(1, round($orig_days / 30));
                        $new_total = $orig['rent_price'] * $months;
                    } else {
                        $new_total = $orig['rent_price'] * $orig_days;
                    }
                }
                $dep_status = $orig['deposit'] > 0 ? 'unpaid' : 'unpaid';
                $dep_paid   = 0.0;
                $copy_uid3 = (int)$_SESSION['user_id'];
                $ins->bind_param("ssissssssddsddsii",
                    $new_no, $orig['type'], $orig['customer_id'], $orig['customer_name'],
                    $orig['contact_name'], $orig['contact_phone'],
                    $new_start, $new_end, $orig['rent_cycle'], $orig['rent_price'],
                    $orig['deposit'], $dep_status, $dep_paid, $new_total,
                    $orig['notes'], $copy_uid3, $copy_uid3);
                $ins->execute();
                $new_id = $conn->insert_id;

                // 复制设备明细
                $items = $conn->query("SELECT * FROM rental_items WHERE contract_id=$copy_id");
                $iins  = $conn->prepare("INSERT INTO rental_items
                    (contract_id, product_name, serial_no, label_no, spec,
                     cost_price, market_price, quantity, rent_price, notes)
                    VALUES (?,?,?,?,?,?,?,?,?,?)");
                while ($it = $items->fetch_assoc()) {
                    // 复制时清空序列号和易碎标签（新设备）
                    $empty = '';
                    $iins->bind_param("issssdddds",
                        $new_id, $it['product_name'], $empty, $empty, $it['spec'],
                        $it['cost_price'], $it['market_price'], $it['quantity'],
                        $it['rent_price'], $it['notes']);
                    $iins->execute();
                }

                $conn->commit();
                echo json_encode(['success' => true, 'new_id' => $new_id, 'contract_no' => $new_no]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => '原合同不存在']);
        }
    } else {
        echo json_encode(['success' => false, 'msg' => '参数错误']);
    }
    $conn->close(); exit;
}


$conn->close();
?>
<?php $page_title = '租借管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,

/* 统计卡片 */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.stat-label{font-size:13px;color:#718096;margin-bottom:8px;}
.stat-value{font-size:28px;font-weight:700;color:#1a202c;}
.stat-value.blue{color:#3b82f6;}
.stat-value.green{color:#135286;}
.stat-value.orange{color:#d97706;}
.stat-value.red{color:#dc2626;}

/* 快捷筛选 */
.filter-tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;}
.filter-tab{padding:7px 16px;border:1px solid #e2e8f0;border-radius:20px;font-size:13px;color:#4a5568;text-decoration:none;background:white;transition:all .2s;cursor:pointer;}
.filter-tab:hover{border-color:#1f6dad;color:#1f6dad;}
.filter-tab.active{background:linear-gradient(135deg,#1f6dad,#135286);color:white;border-color:transparent;}

/* 搜索栏 */
.filter-row{display:flex;align-items:center;gap:10px;background:white;border-radius:12px;padding:14px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:20px;flex-wrap:wrap;}
.filter-row input{flex:1;min-width:200px;padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;}
.filter-row input:focus{outline:none;border-color:#1f6dad;}
.btn-search{padding:8px 18px;background:#1f6dad;color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;}
.btn-clear{padding:8px 14px;color:#4a5568;text-decoration:none;font-size:14px;}

/* 列表 */
thead{background:#f7fafc;}
tbody
tbody tr:last-child

/* 徽章 */
.badge-rental{background:#dbeafe;color:#135286;}
.badge-loan{background:#d1fae5;color:#065f46;}
.badge-active{background:#d1fae5;color:#065f46;}
.badge-expired{background:#fee2e2;color:#991b1b;}
.badge-returned{background:#f3f4f6;color:#374151;}
.badge-cancelled{background:#f3f4f6;color:#9ca3af;}

/* 到期预警 */
.expiring-row{background:#fffbeb !important;}
.overdue-row{background:#fff5f5 !important;}
.days-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;}
.days-ok{color:#135286;}
.days-warn{background:#fef3c7;color:#d97706;}
.days-danger{background:#fee2e2;color:#dc2626;}
.days-overdue{background:#fecaca;color:#991b1b;}

.contract-no{font-weight:600;color:#1a202c;font-family:'Courier New',monospace;font-size:13px;}
.customer-name{font-weight:600;color:#1a202c;}
.customer-sub{font-size:12px;color:#718096;margin-top:2px;}
.amount{font-family:'Courier New',monospace;font-weight:600;}
.btn-sm-red{padding:5px 12px;background:#fff5f5;color:#c53030;border:1px solid #feb2b2;border-radius:6px;font-size:13px;cursor:pointer;white-space:nowrap;}
.btn-group{display:flex;gap:6px;}
.empty-icon{font-size:48px;margin-bottom:12px;}

/* 到期预警横幅 */
.alert-banner{background:linear-gradient(135deg,#fef3c7,#fde68a);border:1px solid #f59e0b;border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:14px;color:#92400e;}
.alert-banner.danger{background:linear-gradient(135deg,#fee2e2,#fecaca);border-color:#ef4444;color:#991b1b;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">📋 租借管理</h1>
        <a href="products.php" style="padding:8px 16px;background:white;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;">← 返回产品管理</a>
    </div>

    <?php if ($stats['overdue'] > 0): ?>
    <div class="alert-banner danger">
        ⚠️ 有 <strong><?=$stats['overdue']?></strong> 份合同已逾期未归还，请及时跟进！
        <a href="?status=active&overdue=1" style="color:inherit;margin-left:8px;text-decoration:underline;">查看详情</a>
    </div>
    <?php endif; ?>

    <?php if ($stats['expiring'] > 0): ?>
    <div class="alert-banner">
        🔔 有 <strong><?=$stats['expiring']?></strong> 份合同将在 3 天内到期，请提前联系客户！
    </div>
    <?php endif; ?>

    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">合同总数</div><div class="stat-value"><?=$stats['total']?></div></div>
        <div class="stat-card"><div class="stat-label">租赁中</div><div class="stat-value blue"><?=$stats['renting']?></div></div>
        <div class="stat-card"><div class="stat-label">借用中</div><div class="stat-value green"><?=$stats['loaning']?></div></div>
        <div class="stat-card"><div class="stat-label">即将到期</div><div class="stat-value orange"><?=$stats['expiring']?></div></div>
        <div class="stat-card"><div class="stat-label">已逾期</div><div class="stat-value red"><?=$stats['overdue']?></div></div>
    </div>

    <!-- 快捷筛选 -->
    <div class="filter-tabs">
        <a href="rentals.php" class="filter-tab <?=(!$type&&!$status)?'active':''?>">全部</a>
        <a href="rentals.php?status=active" class="filter-tab <?=($status==='active'&&!$type)?'active':''?>">进行中</a>
        <a href="rentals.php?type=rental&status=active" class="filter-tab <?=($type==='rental'&&$status==='active')?'active':''?>">🔵 租赁中</a>
        <a href="rentals.php?type=loan&status=active" class="filter-tab <?=($type==='loan'&&$status==='active')?'active':''?>">🟢 借用中</a>
        <a href="rentals.php?status=returned" class="filter-tab <?=($status==='returned')?'active':''?>">已归还</a>
        <a href="rentals.php?status=expired" class="filter-tab <?=($status==='expired')?'active':''?>">已逾期</a>
    </div>

    <!-- 搜索 -->
    <form class="filter-row" method="GET">
        <?php if ($type): ?><input type="hidden" name="type" value="<?=e($type)?>"> <?php endif; ?>
        <?php if ($status): ?><input type="hidden" name="status" value="<?=e($status)?>"> <?php endif; ?>
        <input type="text" name="kw" placeholder="🔍 合同编号、客户名称、联系人..." value="<?=e($kw)?>">
        <button type="submit" class="btn-search">搜索</button>
        <?php if ($kw): ?><a href="rentals.php" class="btn-clear">✕ 清除</a><?php endif; ?>
        <a href="rental_add.php?type=loan"   class="btn-primary" style="background:linear-gradient(135deg,#135286,#047857);">🤝 新建借用</a>
        <a href="rental_add.php?type=rental" class="btn-primary">📋 新建租赁</a>
    </form>

    <!-- 列表 -->
    <div class="content-card">
        <?php if (!empty($list)): ?>
        <table>
            <thead>
                <tr>
                    <th>合同编号</th>
                    <th>类型</th>
                    <th>客户</th>
                    <th>设备</th>
                    <th>租期</th>
                    <th>到期/剩余</th>
                    <th>租金</th>
                    <th>押金</th>
                    <th>状态</th>
                    <th style="text-align:center;white-space:nowrap;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $r):
                $days = daysDiff($r['end_date']);
                $is_overdue  = $r['status']==='active' && $days < 0;
                $is_expiring = $r['status']==='active' && $days >= 0 && $days <= 3;
                $row_class   = $is_overdue ? 'overdue-row' : ($is_expiring ? 'expiring-row' : '');
                $item_cnt    = $item_counts[$r['id']] ?? 0;

                $status_badge = [
                    'active'    => '<span class="badge badge-active">● 进行中</span>',
                    'expired'   => '<span class="badge badge-expired">● 已逾期</span>',
                    'returned'  => '<span class="badge badge-returned">✓ 已归还</span>',
                    'cancelled' => '<span class="badge badge-cancelled">✕ 已取消</span>',
                ][$r['status']] ?? '';
            ?>
            <tr class="<?=$row_class?>">
                <td><div class="contract-no"><?=e($r['contract_no'])?></div></td>
                <td>
                    <?php if ($r['type']==='rental'): ?>
                    <span class="badge badge-rental">💰 租赁</span>
                    <?php else: ?>
                    <span class="badge badge-loan">🤝 借用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="customer-name"><?=e($r['customer_name'])?></div>
                    <?php if ($r['contact_name'] || $r['contact_phone']): ?>
                    <div class="customer-sub"><?=e($r['contact_name'])?> <?=e($r['contact_phone'])?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="background:#eef2ff;color:#4338ca;padding:2px 10px;border-radius:10px;font-size:12px;">
                        <?=$item_cnt?> 台设备
                    </span>
                </td>
                <td style="font-size:13px;color:#718096;">
                    <?=e($r['start_date'])?><br>~ <?=e($r['end_date'])?>
                </td>
                <td>
                    <?php if ($r['status']==='active'): ?>
                        <?php if ($is_overdue): ?>
                            <span class="days-badge days-overdue">逾期 <?=abs($days)?> 天</span>
                        <?php elseif ($is_expiring): ?>
                            <span class="days-badge days-warn">还剩 <?=$days?> 天</span>
                        <?php else: ?>
                            <span class="days-badge days-ok">还剩 <?=$days?> 天</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#a0aec0;font-size:13px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['type']==='rental'): ?>
                    <div class="amount" style="color:#3b82f6;">¥<?=number_format($r['rent_price'],2)?>/<?=$r['rent_cycle']==='day'?'天':'月'?></div>
                    <div style="font-size:12px;color:#718096;">已收 ¥<?=number_format($r['paid_rent'],2)?></div>
                    <?php else: ?>
                    <span style="color:#135286;font-size:13px;">免费借用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['deposit'] > 0): ?>
                    <div class="amount">¥<?=number_format($r['deposit'],2)?></div>
                    <div style="font-size:12px;color:#718096;">
                        <?php $ds=['unpaid'=>'未收','paid'=>'已收','refunded'=>'已退','deducted'=>'已扣'];
                        echo $ds[$r['deposit_status']]??''; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:#a0aec0;font-size:13px;">无押金</span>
                    <?php endif; ?>
                </td>
                <td><?=$status_badge?></td>
                <td>
                    <div class="btn-group" style="justify-content:center;">
                        <a href="rental_view.php?id=<?=$r['id']?>" class="btn-sm">📋 详情</a>
                        <?php if ($r['status']==='active'): ?>
                        <a href="rental_edit.php?id=<?=$r['id']?>" class="btn-sm">✏️ 编辑</a>
                        <?php endif; ?>
                        <button class="btn-sm" onclick="copyContract(<?=$r['id']?>, '<?=e($r['contract_no'])?>')" style="color:#135286;">📋 复制</button>
                        <button class="btn-sm-red" onclick="deleteContract(<?=$r['id']?>,'<?=e($r['contract_no'])?>')">🗑️ 删除</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <div>暂无租借记录</div>
            <div style="margin-top:12px;display:flex;gap:10px;justify-content:center;">
                <a href="rental_add.php?type=rental" class="btn-primary" style="font-size:13px;padding:8px 16px;">+ 新建租赁</a>
                <a href="rental_add.php?type=loan"   class="btn-primary" style="font-size:13px;padding:8px 16px;background:linear-gradient(135deg,#135286,#047857);">+ 新建借用</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
async function copyContract(id, contractNo) {
    if (!confirm('复制合同 ' + contractNo + '？\n\n将创建一份新合同：\n· 客户和设备信息相同\n· 日期从今天起、保留原租期长度\n· 序列号/易碎标签清空\n· 押金状态重置为未收\n\n创建后将跳转到新合同页面。')) return;
    try {
        const resp = await fetch(location.pathname, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=copy_contract&id=' + id
        });
        const d = await resp.json();
        if (d.success) {
            window.location.href = 'rental_view.php?id=' + d.new_id + '&created=1';
        } else {
            sapToast('❌ 复制失败：' + (d.msg || '未知错误', 'error'));
        }
    } catch(e) {
        sapToast('❌ 网络错误：' + e.message, 'error');
    }
}

async function deleteContract(id, contractNo) {
    if (!confirm('确定删除合同 ' + contractNo + ' 吗？\n\n此操作不可恢复！')) return;
    try {
        const resp = await fetch(location.pathname, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_contract&id=' + id
        });
        const d = await resp.json();
        if (d.success) {
            const btn = document.querySelector('button[onclick*="deleteContract(' + id + ',"]');
            const row = btn ? btn.closest('tr') : null;
            if (row) {
                row.style.transition = 'opacity .4s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 400);
            } else {
                location.reload();
            }
        } else {
            sapToast('❌ ' + (d.msg || '删除失败', 'error'));
        }
    } catch(e) {
        sapToast('❌ 网络错误：' + e.message, 'error');
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
