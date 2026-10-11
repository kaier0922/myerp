<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid      = intval($_SESSION['user_id']);
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';
$conn     = getDBConnection();

$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where  = "sl.user_id=?";
$params = [$uid];
$types  = 'i';
if ($search) {
    $where   .= " AND (sl.product_name LIKE ? OR sl.supplier_name LIKE ? OR sl.notes LIKE ?)";
    $s        = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'sss';
}
$cnt_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM stock_in_log sl WHERE $where");
$cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total       = $cnt_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);
$p2 = $params; $t2 = $types . 'ii';
$p2[] = $limit; $p2[] = $offset;
$stmt = $conn->prepare("SELECT sl.*
    FROM stock_in_log sl
    WHERE $where ORDER BY sl.id DESC LIMIT ? OFFSET ?");
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<?php $page_title = '采购入库记录'; include __DIR__ . '/head.php'; ?>
<style>
.page-subtitle { color: #718096; font-size: 14px; }
.filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 13px; font-weight: 500; color: #4a5568; }
.filter-group input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; width: 240px; }
.filter-group input:focus { outline: none; border-color: #1f6dad; }
.card-title { font-size: 18px; font-weight: 600; color: #1a202c; }
.table-wrapper { overflow-x: auto; }
thead tr { background: #f7fafc; border-bottom: 2px solid #e2e8f0; }
tbody
.badge-paid    { background: #d1fae5; color: #065f46; }
.badge-unpaid  { background: #fee2e2; color: #991b1b; }
.badge-partial { background: #fef3c7; color: #92400e; }
.badge-none    { background: #f3f4f6; color: #6b7280; }
.amt { color: #1f6dad; font-weight: 600; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; padding: 20px; }
.pagination a, .pagination span { padding: 7px 12px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #4a5568; font-size: 14px; }
.pagination a:hover { background: #f7fafc; border-color: #1f6dad; color: #1f6dad; }
.pagination span.cur { background: linear-gradient(135deg,#1f6dad 0%,#135286 100%); color: white; border-color: transparent; }
@media (max-width: 768px) { }
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">📦 采购入库记录</h1>
        <p class="page-subtitle">查看所有采购入库单及对应应付状态</p>
    </div>
    <div class="content-card">
        <div class="card-header" style="justify-content:flex-start;gap:12px;flex-wrap:wrap;">
            <span class="card-title">入库记录</span>
            <span style="font-size:13px;color:#718096">共 <?php echo $total; ?> 条</span>
            <form method="GET" style="display:contents">
                <input type="text" name="search" placeholder="供应商/备注/单号..." value="<?php echo htmlspecialchars($search); ?>" style="padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;width:200px;">
                <button type="submit" class="btn btn-primary">搜索</button>
                <?php if($search): ?><a href="stock_list.php" class="btn btn-secondary">清除</a><?php endif; ?>
                <a href="sn_import.php" class="btn btn-primary">+ 新增入库</a>
                <a href="sn_import.php" class="btn btn-secondary">← 返回采购入库</a>
            </form>
        </div>
        <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>入库时间</th>
                <th>产品名称</th>
                <th>数量</th>
                <th>单价(¥)</th>
                <th>总金额(¥)</th>
                <th>供应商</th>
                <th>序列号</th>
                <th>备注</th>
            </tr></thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">📦</div><p>暂无入库记录</p></div></td></tr>
            <?php else: foreach($rows as $r):
            ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($r['created_at'])); ?></td>
                    <td style="font-weight:500;"><?php echo htmlspecialchars($r['product_name'] ?? '-'); ?></td>
                    <td style="text-align:center;"><?php echo intval($r['quantity']); ?></td>
                    <td><?php echo $r['unit_price']>0 ? '¥'.number_format($r['unit_price'],2) : '<span style="color:#a0aec0">-</span>'; ?></td>
                    <td><?php echo $r['total_amount']>0 ? '¥'.number_format($r['total_amount'],2) : '<span style="color:#a0aec0">-</span>'; ?></td>
                    <td><?php echo $r['supplier_name'] ? htmlspecialchars($r['supplier_name']) : '<span style="color:#a0aec0">-</span>'; ?></td>
                    <td style="font-size:12px;">
                    <?php
                    $sn_list_arr = $r['sn_codes'] ? array_filter(array_map('trim', explode("\n", trim($r['sn_codes'])))) : [];
                    $sn_cnt = count($sn_list_arr);
                    if ($sn_cnt === 0):
                    ?><span style="color:#a0aec0;">无</span><?php
                    elseif ($sn_cnt <= 3):
                        foreach($sn_list_arr as $sn) echo '<div style="font-family:monospace;">'.htmlspecialchars($sn).'</div>';
                    else:
                        $preview = array_slice($sn_list_arr, 0, 2);
                        foreach($preview as $sn) echo '<div style="font-family:monospace;">'.htmlspecialchars($sn).'</div>';
                        $uid_tag = 'sn_'.$r['id'];
                    ?>
                        <div id="<?=$uid_tag?>" style="display:none;">
                        <?php foreach(array_slice($sn_list_arr,2) as $sn) echo '<div style="font-family:monospace;">'.htmlspecialchars($sn).'</div>'; ?>
                        </div>
                        <a href="javascript:void(0)" onclick="toggleSN('<?=$uid_tag?>',this)"
                           style="color:#1f6dad;font-size:11px;display:inline-block;margin-top:3px;">
                           ▼ 共<?=$sn_cnt?>个，点击展开
                        </a>
                    <?php endif; ?>
                    </td>
                    <td style="color:#718096;font-size:12px;"><?php echo htmlspecialchars($r['notes'] ?? ''); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php for($i=1;$i<=$total_pages;$i++): ?>
                <?php if($i==$page): ?>
                    <span class="cur"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
function toggleSN(id, btn) {
    var el = document.getElementById(id);
    if (el.style.display === 'none') {
        el.style.display = 'block';
        btn.textContent = '▲ 收起';
    } else {
        el.style.display = 'none';
        btn.textContent = btn.textContent.replace('▲ 收起', '▼ ' + btn.textContent.match(/共\d+个/)[0] + '，点击展开');
    }
}
</script>
</body>
</html>
