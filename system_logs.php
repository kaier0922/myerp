<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$conn = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '';
$role     = $_SESSION['role'] ?? '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// 筛选参数
$module     = trim($_GET['module'] ?? '');
$action     = trim($_GET['action'] ?? '');
$user_filter= trim($_GET['user_id'] ?? '');
$date_from  = trim($_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')));
$date_to    = trim($_GET['date_to']   ?? date('Y-m-d'));
$page       = max(1, intval($_GET['page'] ?? 1));
$per_page   = 50;

// 构建WHERE
$where = ['1=1'];
$params = []; $types = '';
if ($module)      { $where[] = "l.module=?";           $params[] = $module;      $types .= 's'; }
if ($action)      { $where[] = "l.action=?";           $params[] = $action;      $types .= 's'; }
if ($user_filter) { $where[] = "l.user_id=?";          $params[] = $user_filter; $types .= 'i'; }
if ($date_from)   { $where[] = "DATE(l.created_at)>=?"; $params[] = $date_from;  $types .= 's'; }
if ($date_to)     { $where[] = "DATE(l.created_at)<=?"; $params[] = $date_to;    $types .= 's'; }
$where_sql = implode(' AND ', $where);

// 总数
$count_sql = "SELECT COUNT(*) as cnt FROM system_logs l WHERE $where_sql";
if ($params) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['cnt'];
} else {
    $total = $conn->query($count_sql)->fetch_assoc()['cnt'];
}
$total_pages = ceil($total / $per_page);
$offset = ($page-1) * $per_page;

// 列表
$sql = "SELECT l.*, u.nickname FROM system_logs l
        LEFT JOIN users u ON l.user_id=u.id
        WHERE $where_sql ORDER BY l.created_at DESC LIMIT $per_page OFFSET $offset";
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $logs = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// 用户列表（筛选用）
$users = $conn->query("SELECT id, nickname FROM users WHERE role != 'super_admin' ORDER BY nickname")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$module_map = [
    'quotes'=>'报价管理','contracts'=>'合同管理','deliveries'=>'送货管理',
    'customers'=>'客户管理','suppliers'=>'供应商管理','products'=>'产品管理',
    'receipts'=>'收据管理','repair'=>'维修管理','rentals'=>'租借管理',
    'finance'=>'财务管理','users'=>'用户管理','settings'=>'系统设置',
    'seals'=>'公章管理','solutions'=>'方案管理','auth'=>'登录认证',
];
$action_map = [
    'create'=>'新建','update'=>'修改','delete'=>'删除','approve'=>'审核',
    'unapprove'=>'取消审核','login'=>'登录','logout'=>'退出','export'=>'导出',
    'import'=>'导入','pay'=>'付款','receive'=>'收款','status'=>'更新状态',
];
$action_color = [
    'create'=>'#dbeafe:#1f6dad','update'=>'#fef3c7:#92400e','delete'=>'#fee2e2:#991b1b',
    'approve'=>'#d1fae5:#065f46','unapprove'=>'#f3f4f6:#6b7280','login'=>'#ede9fe:#5b21b6',
    'export'=>'#f0fdf4:#15803d','import'=>'#fff7ed:#c2410c','pay'=>'#ecfdf5:#047857',
    'receive'=>'#eff6ff:#1d4ed8','status'=>'#fdf4ff:#7e22ce','delete'=>'#fee2e2:#991b1b',
];
?>
<?php $page_title = '操作日志 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.inner{max-width:1200px;margin:0 auto;}
.filter-bar{background:white;border-radius:12px;padding:16px 20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:12px;font-weight:500;color:#4a5568;}
.fg select,.fg input{padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;background:white;}
.fg select:focus,.fg input:focus{border-color:#1f6dad;}
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;}
.card-title{font-size:15px;font-weight:600;color:#1a202c;}
tr:last-child td{border-bottom:none;}
tr:hover{background:#f7fafc;}
table th,table td{white-space:nowrap;}
.detail-text{color:#4a5568;max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap;}
.pagination a,.pagination span{padding:6px 12px;border-radius:6px;font-size:13px;text-decoration:none;border:1px solid #e2e8f0;color:#4a5568;background:white;}
.pagination a:hover{background:#edf2f7;}
.pagination .cur{background:#1f6dad;color:white;border-color:#1f6dad;}
.total-info{font-size:13px;color:#718096;margin-bottom:12px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="inner">
    <div class="page-header">
        <div class="page-title">📊 操作日志</div>
    </div>

    <!-- 筛选 -->
    <form method="GET" action="system_logs.php">
    <div class="filter-bar">
        <div class="fg">
            <label>模块</label>
            <select name="module">
                <option value="">全部模块</option>
                <?php foreach($module_map as $k=>$v): ?>
                <option value="<?=$k?>" <?=$module===$k?'selected':'';?>><?=$v?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>操作类型</label>
            <select name="action">
                <option value="">全部操作</option>
                <?php foreach($action_map as $k=>$v): ?>
                <option value="<?=$k?>" <?=$action===$k?'selected':'';?>><?=$v?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>操作人</label>
            <select name="user_id">
                <option value="">全部用户</option>
                <?php foreach($users as $u): ?>
                <option value="<?=$u['id']?>" <?=$user_filter==$u['id']?'selected':'';?>><?=e($u['nickname'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>开始日期</label>
            <input type="date" name="date_from" value="<?=e($date_from)?>">
        </div>
        <div class="fg">
            <label>结束日期</label>
            <input type="date" name="date_to" value="<?=e($date_to)?>">
        </div>
        <button type="submit" class="btn btn-primary">🔍 查询</button>
        <a href="system_logs.php" class="btn btn-secondary">清除</a>
    </div>
    </form>

    <!-- 列表 -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">日志列表</div>
            <span style="font-size:13px;color:#718096;">共 <?=$total?> 条记录</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:160px;">时间</th>
                    <th style="width:80px;">操作人</th>
                    <th style="width:90px;">模块</th>
                    <th style="width:80px;">操作</th>
                    <th>详情</th>
                </tr>
            </thead>
            <tbody>
            <?php if($logs): ?>
                <?php foreach($logs as $log):
                    $mod_name = $module_map[$log['module']] ?? $log['module'];
                    $act_name = $action_map[$log['action']] ?? $log['action'];
                    $colors   = explode(':', $action_color[$log['action']] ?? '#f3f4f6:#6b7280');
                    $bg = $colors[0]; $fg = $colors[1];
                ?>
                <tr>
                    <td style="color:#718096;font-size:12px;"><?=e($log['created_at'])?></td>
                    <td><?=e($log['nickname'] ?? '系统')?></td>
                    <td><span class="badge" style="background:#f0f4ff;color:#1f6dad;"><?=$mod_name?></span></td>
                    <td><span class="badge" style="background:<?=$bg?>;color:<?=$fg?>;"><?=$act_name?></span></td>
                    <td class="detail-text" title="<?=e($log['detail'])?>"><?=e($log['detail'])?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;color:#a0aec0;padding:32px;">暂无日志记录</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <?php if($total_pages > 1):
        $qs = http_build_query(array_filter(['module'=>$module,'action'=>$action,'user_id'=>$user_filter,'date_from'=>$date_from,'date_to'=>$date_to]));
    ?>
    <div class="pagination">
        <?php if($page>1): ?><a href="?<?=$qs?>&page=<?=$page-1?>">← 上一页</a><?php endif; ?>
        <?php for($i=max(1,$page-3);$i<=min($total_pages,$page+3);$i++): ?>
            <?php if($i==$page): ?><span class="cur"><?=$i?></span>
            <?php else: ?><a href="?<?=$qs?>&page=<?=$i?>"><?=$i?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if($page<$total_pages): ?><a href="?<?=$qs?>&page=<?=$page+1?>">下一页 →</a><?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</main>
</body>
</html>
