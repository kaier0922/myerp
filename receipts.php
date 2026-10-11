<?php
/**
 * =====================================================
 * 文件名：receipts.php
 * 版本：  1.1
 * 日期：  2026-03-17
 * 功能：  收据管理列表页（查看/新建/删除/导出）
 * =====================================================
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];

$conn = getDBConnection();

$keyword = $_GET['kw'] ?? '';
$kw = '%' . $keyword . '%';

$stats = $conn->query("
    SELECT COUNT(*) as total_count, SUM(total_amount) as total_amount FROM receipts WHERE " . tenantWhere() . "
")->fetch_assoc();

$sql = "SELECT r.*,
               COALESCE(c.short_name, c.company_name, c.contact_name) AS customer_name,
               u.nickname AS creator_name
        FROM receipts r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE " . tenantWhere("r") . " AND (r.receipt_no LIKE ?
           OR IFNULL(COALESCE(c.short_name, c.company_name),'') LIKE ?
           OR IFNULL(c.contact_name,'') LIKE ?
           OR IFNULL(r.walkin_name,'') LIKE ?)
        ORDER BY r.created_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!$stmt) die('❌ SQL 预处理失败：' . $conn->error);
$stmt->bind_param('ssss', $kw, $kw, $kw, $kw);
if (!$stmt->execute()) die('❌ SQL 执行失败：' . $stmt->error);
$result = $stmt->get_result();

$conn->close();
?>
<?php $page_title = '收据管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

.page-subtitle { color: #718096; font-size: 14px; }
.page-actions { display: flex; gap: 12px; }

.stats-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px; margin-bottom: 24px;
}
.stat-box {
    background: white; border-radius: 12px; padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column; gap: 8px;
}
.stat-box-label { font-size: 13px; color: #718096; }
.stat-box-value { font-size: 28px; font-weight: 600; color: #1a202c; font-family: 'Courier New', monospace; }

.alert-close {
    background: none; border: none; font-size: 20px; color: #22543d;
    cursor: pointer; padding: 0; width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
}
.alert-close:hover { opacity: 0.7; }

.filter-bar {
    background: white; padding: 20px; border-radius: 12px;
    margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex; gap: 12px; align-items: center;
}
.filter-item { flex: 1; }
.filter-item input {
    width: 100%; padding: 10px 16px; border: 1px solid #e2e8f0;
    border-radius: 8px; font-size: 14px;
}
.filter-item input:focus {
    outline: none; border-color: #1f6dad;
    box-shadow: 0 0 0 3px rgba(31,109,173,0.1);
}

.btn-group { display: flex; gap: 8px; }
.amount { font-weight: 600; font-family: 'Courier New', monospace; color: #1f6dad; }

.badge-info    { background: #bee3f8; color: #2c5282; }
.badge-success { background: #c6f6d5; color: #22543d; }
</style>
</head>
<body>

<!-- 导航栏（与 index.php 完全一致） -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏（与 index.php 完全一致） -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区（功能逻辑完全不变） -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">📄 收据管理</h1>
            <p class="page-subtitle">管理收款收据，支持新建、查看、打印、导出功能</p>
        </div>
        <div class="page-actions">
            <a href="receipt_create.php" class="btn btn-primary">➕ 新建收据</a>
            <a href="finance.php" class="btn btn-secondary">返回财务</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-box-label">收据总数</div>
            <div class="stat-box-value"><?php echo $stats['total_count'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">收据总金额</div>
            <div class="stat-box-value" style="color:#1f6dad;">¥<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert-success">
        <span>✅ 收据已删除成功！</span>
        <button class="alert-close" onclick="this.parentElement.style.display='none'">×</button>
    </div>
    <?php endif; ?>

    <div class="filter-bar">
        <form method="get" style="display:contents;">
            <div class="filter-item">
                <input type="text" name="kw"
                       placeholder="🔍 搜索收据号 / 客户名称 / 散客名称..."
                       value="<?php echo htmlspecialchars($keyword); ?>">
            </div>
            <button type="submit" class="btn btn-secondary">搜索</button>
            <?php if ($keyword): ?>
                <a href="receipts.php" class="btn btn-secondary">清除</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="content-card">
        <table>
            <thead>
                <tr>
                    <th>收据号</th>
                    <th>客户/散客</th>
                    <th>金额</th>
                    <th>支付方式</th>
                    <th>来源</th>
                    <th>创建人</th>
                    <th>创建时间</th>
                    <th style="width:280px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['receipt_no']); ?></strong></td>
                        <td><?php echo $row['customer_name'] ?: htmlspecialchars($row['walkin_name'] ?: '散客'); ?></td>
                        <td class="amount">¥<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['payment_method'] ?: '-'); ?></td>
                        <td>
                            <span class="badge <?php echo ($row['related_type']==='receivable') ? 'badge-success' : 'badge-info'; ?>">
                                <?php echo ($row['related_type']==='receivable') ? '应收生成' : '独立创建'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['creator_name'] ?: '-'); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="receipt_view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-primary btn-sm">👁️ 查看</a>
                                <a href="receipt_edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-secondary btn-sm">📝 编辑</a>
                                <a href="receipt_delete.php?id=<?php echo (int)$row['id']; ?>"
                                   onclick="return confirm('确定要删除这张收据吗？此操作不可恢复！');"
                                   class="btn btn-secondary btn-sm" style="color:#dc2626;">🗑️ 删除</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div style="font-size:48px; margin-bottom:16px;">📄</div>
                            <h3 style="margin-bottom:8px;">暂无收据记录</h3>
                            <p>点击右上角"新建收据"开始创建第一张收据</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
