<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();
require_once 'config.php';

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];
$conn = getDBConnection();

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $conn->query("UPDATE rma_orders SET status='closed' WHERE id=$del_id AND user_id=" . intval($_SESSION['user_id']));
    header('Location: rma.php'); exit;
}

$status_filter = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';
$where = "1=1";
if ($status_filter) $where .= " AND r.status='" . $conn->real_escape_string($status_filter) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (r.rma_no LIKE '%$s%' OR r.serial_number LIKE '%$s%' OR s.company_name LIKE '%$s%' OR r.fault_desc LIKE '%$s%')";
}

$stats = $conn->query("SELECT
    COUNT(*) as total,
    SUM(status='pending') as pending,
    SUM(status='sent') as sent,
    SUM(status='processing') as processing,
    SUM(status IN ('returned','replaced','refunded')) as done
    FROM rma_orders")->fetch_assoc();

$sql = "SELECT r.*, s.company_name as supplier_name, p.name as product_name
        FROM rma_orders r
        LEFT JOIN suppliers s ON r.supplier_id=s.id
        LEFT JOIN products p ON r.product_id=p.id
        WHERE $where ORDER BY r.id DESC";
$res = $conn->query($sql);
if (!$res) die("SQL错误: " . $conn->error);
$rows = $res->fetch_all(MYSQLI_ASSOC);

$conn->close();

$status_map = [
    'pending'    => ['待发出', 'badge-gray'],
    'sent'       => ['已发出', 'badge-blue'],
    'processing' => ['处理中', 'badge-yellow'],
    'returned'   => ['已返回', 'badge-green'],
    'replaced'   => ['已换货', 'badge-green'],
    'refunded'   => ['已退款', 'badge-green'],
    'closed'     => ['已关闭', 'badge-gray'],
];
$result_map = [
    'repaired'     => '修好返回',
    'replaced'     => '换全机',
    'refunded'     => '退款',
    'unrepairable' => '无法维修',
];
function e($v){return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
?>
<?php $page_title = '返修管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.btn-danger { background: #fee2e2; color: #991b1b; border: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
.action-buttons { display:flex; gap:8px; align-items:center; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card  { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.stat-num   { font-size: 28px; font-weight: 700; color: #1f6dad; }
.stat-label { font-size: 13px; color: #718096; margin-top: 4px; }
.toolbar { background: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.search-input  { padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; width: 220px; }
.filter-select { padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
.search-input:focus, .filter-select:focus { outline: none; border-color: #1f6dad; box-shadow: 0 0 0 3px rgba(31,109,173,0.1); }
.table-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
tr:hover
.badge-gray   { background: #f1f5f9; color: #64748b; }
.badge-blue   { background: #dbeafe; color: #1d4ed8; }
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-green  { background: #dcfce7; color: #166534; }
.empty { text-align: center; padding: 60px; color: #a0aec0; }
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">↩️ 返修管理</h1>
    </div>

    <div class="stats">
        <div class="stat-card"><div class="stat-num"><?=$stats['total']?></div><div class="stat-label">全部</div></div>
        <div class="stat-card"><div class="stat-num"><?=$stats['pending']?></div><div class="stat-label">待发出</div></div>
        <div class="stat-card"><div class="stat-num"><?=$stats['sent']?></div><div class="stat-label">已发出</div></div>
        <div class="stat-card"><div class="stat-num"><?=$stats['processing']?></div><div class="stat-label">处理中</div></div>
        <div class="stat-card"><div class="stat-num"><?=$stats['done']?></div><div class="stat-label">已完成</div></div>
    </div>

    <div class="toolbar">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" class="search-input" placeholder="搜索单号/序列号/供应商..." value="<?=e($search)?>">
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">全部状态</option>
                <?php foreach($status_map as $k=>$v): ?>
                <option value="<?=$k?>" <?=$status_filter===$k?'selected':''?>><?=$v[0]?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">🔍 搜索</button>
            <?php if($search||$status_filter): ?><a href="rma.php" class="btn btn-secondary">清除</a><?php endif; ?>
            <a href="products.php" class="btn btn-secondary">← 返回产品管理</a>
            <a href="rma_add.php" class="btn btn-primary">＋ 新建返修单</a>
        </form>
    </div>

    <div class="table-card">
        <?php if(empty($rows)): ?>
        <div class="empty">暂无返修记录</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>返修单号</th>
                    <th>供应商</th>
                    <th>产品 / 序列号</th>
                    <th>故障描述</th>
                    <th>发出日期</th>
                    <th>状态</th>
                    <th>处理结果</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): ?>
            <tr>
                <td><strong><?=e($r['rma_no'])?></strong></td>
                <td><?=e($r['supplier_name']??'-')?></td>
                <td>
                    <?=e($r['product_name']??'')?>
                    <?php if($r['serial_number']): ?>
                    <br><small style="color:#718096;"><?=e($r['serial_number'])?></small>
                    <?php endif; ?>
                </td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?=e($r['fault_desc'])?>">
                    <?=e($r['fault_desc']??'-')?>
                </td>
                <td><?=e($r['send_date']??'-')?></td>
                <td><span class="badge <?=$status_map[$r['status']][1]??'badge-gray'?>"><?=$status_map[$r['status']][0]??$r['status']?></span></td>
                <td><?=e($result_map[$r['result']]??'-')?></td>
                <td style="white-space:nowrap;">
                    <div class="action-buttons">
                        <a href="rma_view.php?id=<?=$r['id']?>" class="btn btn-secondary" style="font-size:13px;padding:6px 12px;">👁️ 查看</a>
                        <a href="rma_add.php?id=<?=$r['id']?>" class="btn btn-secondary" style="font-size:13px;padding:6px 12px;">✏️ 编辑</a>
                        <button onclick="deleteRma(<?=$r['id']?>, '<?=e($r['rma_no'])?>')" class="btn btn-secondary" style="font-size:13px;padding:6px 12px;color:#ef4444;border-color:#fecaca;">🗑️ 删除</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>


<script>
function deleteRma(id, rmaNo) {
    sapConfirm(
        `确定要删除返修单 "<strong>${rmaNo}</strong>" 吗？<br><br>此操作不可恢复。`,
        function() { window.location.href = 'rma.php?action=delete&id=' + id; },
        { danger: true, title: '删除返修单', okText: '确认删除', icon: '🗑️' }
    );
}
</script>
</body>
</html>
