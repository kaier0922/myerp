<?php
/**
 * =====================================================
 * 文件名：seals.php
 * 版本：  1.1
 * 日期：  2026-03-17
 * 功能：  公章管理页面（添加/编辑/删除/设为默认）
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

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $seal_id = intval($_GET['id']);
    $del_uid=(int)$_SESSION['user_id'];
    $conn->query("DELETE FROM seals WHERE id=$seal_id AND user_id=$del_uid");
    header('Location: seals.php');
    exit;
}

// 处理设为默认操作
if (isset($_GET['action']) && $_GET['action'] === 'set_default' && isset($_GET['id'])) {
    $seal_id = intval($_GET['id']);
    $conn->query("UPDATE seals SET is_default = 0");
    $conn->query("UPDATE seals SET is_default = 1 WHERE id = $seal_id");
    header('Location: seals.php');
    exit;
}

$seals = $conn->query("SELECT * FROM seals WHERE " . tenantWhere() . " ORDER BY is_default DESC, created_at DESC");

$stats = $conn->query("
    SELECT COUNT(*) as total_count,
           SUM(CASE WHEN is_default=1 THEN 1 ELSE 0 END) as default_count
    FROM seals WHERE " . tenantWhere() . "
")->fetch_assoc();

$conn->close();
?>
<?php $page_title = '公章管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

.page-subtitle { color: #718096; font-size: 14px; }

.stats-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px; margin-bottom: 30px;
}
.stat-box {
    background: white; border-radius: 12px; padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;
}
.stat-box-label { font-size: 14px; color: #718096; margin-bottom: 8px; }
.stat-box-value { font-size: 28px; font-weight: 700; color: #1a202c; }

.toolbar { display: flex; justify-content: flex-end; margin-bottom: 24px; }

.seals-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;
}
.seal-card {
    background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;
    transition: all 0.3s; position: relative;
}
.seal-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: #1f6dad; }

.seal-preview {
    width: 100%; height: 200px; background: #f8fafc; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px; border: 1px dashed #e2e8f0; overflow: hidden;
}
.seal-preview img { max-width: 85%; max-height: 85%; object-fit: contain; }

.seal-name {
    font-size: 17px; font-weight: 600; color: #1a202c;
    margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
}
.seal-meta { margin-bottom: 20px; font-size: 14px; color: #718096; line-height: 1.6; }

.badge.default { background: #d1fae5; color: #065f46; }

.seal-actions { display: flex; gap: 8px; flex-wrap: wrap; }

@media (max-width: 1024px) { }
</style>
</head>
<body>

<!-- 导航栏（与 index.php 完全一致） -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏（标准菜单顺序，标准图标） -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区（功能逻辑完全不变） -->
<main class="main-content">
<div style="max-width:900px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">🔏 公章管理</h1>
        <p class="page-subtitle">管理企业印章，用于报价单、方案及合同自动盖章</p>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-box-label">公章总数</div>
            <div class="stat-box-value"><?php echo $stats['total_count']; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">默认公章</div>
            <div class="stat-box-value"><?php echo $stats['default_count']; ?></div>
        </div>
    </div>

    <div class="toolbar">
        <a href="seal_add.php" class="btn btn-primary">＋ 添加新公章</a>
    </div>

    <?php if ($seals->num_rows > 0): ?>
    <div class="seals-grid">
        <?php while($seal = $seals->fetch_assoc()): ?>
        <div class="seal-card">
            <div class="seal-preview">
                <img src="<?php echo htmlspecialchars($seal['file_path']); ?>" alt="公章预览">
            </div>
            <div class="seal-name">
                <?php echo htmlspecialchars($seal['seal_name']); ?>
                <?php if ($seal['is_default']): ?>
                    <span class="badge default">默认公章</span>
                <?php endif; ?>
            </div>
            <div class="seal-meta">
                类型：<?php echo htmlspecialchars($seal['seal_type']); ?><br>
                创建于：<?php echo date('Y-m-d', strtotime($seal['created_at'])); ?>
            </div>
            <div class="seal-actions">
                <?php if (!$seal['is_default']): ?>
                <a href="seals.php?action=set_default&id=<?php echo $seal['id']; ?>"
                   class="btn btn-secondary btn-sm"
                   onclick="return confirm('确定设为默认公章吗？')">设为默认</a>
                <?php endif; ?>
                <a href="seal_edit.php?id=<?php echo $seal['id']; ?>" class="btn btn-secondary btn-sm">编辑</a>
                <a href="seals.php?action=delete&id=<?php echo $seal['id']; ?>"
                   class="btn btn-secondary btn-sm" style="color:#ef4444;"
                   onclick="return confirm('警告：确定要删除此公章吗？')">删除</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div style="font-size:48px; margin-bottom:20px;">🔏</div>
        <h3 style="font-size:20px; color:#1a202c; margin-bottom:10px;">暂无公章记录</h3>
        <p style="color:#718096; margin-bottom:30px;">您还没有添加任何公章，请点击右上角按钮添加。</p>
        <a href="seal_add.php" class="btn btn-primary">添加第一个公章</a>
    </div>
    <?php endif; ?>
</div><!-- end max-width -->
</main>

</body>
</html>
