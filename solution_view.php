<?php
/**
 * 文件名: solution_view.php
 * 优化说明: 
 * 1. 修复侧边栏遮挡内容 (margin-left: 260px)
 * 2. 调大侧边栏菜单字体至 16px，补全所有菜单项
 * 3. 优化详情页视觉层次，增加阴影与间距，提升阅读体验
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

$solution_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 获取方案详情
$solution = $conn->query("
    SELECT s.*, u.nickname as creator_name
    FROM solutions s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.id = $solution_id
")->fetch_assoc();

if (!$solution) {
    header('Location: solutions.php');
    exit;
}

// 获取附件列表
$attachments = $conn->query("SELECT * FROM solution_attachments WHERE solution_id = $solution_id ORDER BY uploaded_at");

$conn->close();
?>
<?php $page_title = htmlspecialchars($solution['solution_name']) . ' - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

        /* --- 顶部导航栏 --- */

        .user-avatar-box { width: 38px; height: 38px; background: linear-gradient(135deg, #1f6dad 0%, #135286 100%); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; }
        .user-details { line-height: 1.3; text-align: right; margin-right: 5px; }
        .u-name { font-size: 14px; font-weight: 600; color: #1a202c; display: block; }
        .u-role { font-size: 12px; color: #94a3b8; display: block; }
        .btn-logout-new { padding: 6px 14px; border: 1px solid #e2e8f0; border-radius: 6px; color: #4b5563; font-size: 13px; text-decoration: none; background: white; transition: 0.2s; }
        .btn-logout-new:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

        /* --- 侧边栏 (字体 16px & 完整菜单) --- */

        /* --- 主内容区修复 --- */
        .content-container { width: 100%; max-width: 1000px; margin: 0 auto; }

        /* 工具栏 */
        .toolbar {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .toolbar-title { font-size: 20px; font-weight: 700; color: #1a202c; }
        .toolbar-actions { display: flex; gap: 10px; }

        /* 按钮 */

        /* 内容卡片 */

        .section-title {
            font-size: 17px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title::before { content: ""; width: 4px; height: 16px; background: #1f6dad; border-radius: 2px; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* 改为三列更紧凑 */
            gap: 24px;
        }
        .info-item { display: flex; flex-direction: column; gap: 4px; }
        .info-label { font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .info-value { font-size: 15px; color: #1e293b; font-weight: 500; }

        .badge.draft { background: #f1f5f9; color: #475569; }
        .badge.reviewing { background: #fefce8; color: #854d0e; }
        .badge.approved { background: #dcfce7; color: #166534; }
        .badge.rejected { background: #fef2f2; color: #991b1b; }

        /* 方案富文本内容美化 */
        .solution-content {
            line-height: 1.8;
            color: #334155;
            font-size: 16px;
        }
        .solution-content h1, .solution-content h2, .solution-content h3 { color: #0f172a; margin-top: 24px; margin-bottom: 12px; }
        .solution-content p { margin-bottom: 16px; }
        .solution-content img { max-width: 100%; border-radius: 8px; margin: 20px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        /* 附件列表 */
        .attachment-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            transition: 0.2s;
        }
        .attachment-item:hover { background: #fff; border-color: #1f6dad; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .attachment-icon { font-size: 24px; }
        .attachment-info { flex: 1; min-width: 0; }
        .attachment-name { font-size: 14px; color: #1e293b; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .attachment-meta { font-size: 11px; color: #94a3b8; }
        .attachment-dl-btn { color: #1f6dad; font-weight: 800; font-size: 18px; }

        @media (max-width: 1024px) {
        }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="content-container">
        <div class="toolbar">
            <div class="toolbar-title">📄 <?php echo htmlspecialchars($solution['solution_name']); ?></div>
            <div class="toolbar-actions">
                <a href="solution_edit.php?id=<?php echo $solution_id; ?>" class="btn btn-primary">编辑方案</a>
                <a href="solution_export_simple.php?id=<?php echo $solution_id; ?>&format=word" class="btn btn-secondary">导出Word</a>
                <a href="solutions.php" class="btn btn-secondary">返回列表</a>
            </div>
        </div>

        <div class="content-card">
            <h2 class="section-title">基本信息</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">方案编号</span>
                    <span class="info-value"><?php echo htmlspecialchars($solution['solution_no']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">当前状态</span>
                    <span class="info-value">
                        <?php
                        $status_class = ['草稿'=>'draft','审核中'=>'reviewing','已通过'=>'approved','已驳回'=>'rejected'];
                        $class = $status_class[$solution['status']] ?? 'draft';
                        ?>
                        <span class="badge <?php echo $class; ?>"><?php echo htmlspecialchars($solution['status']); ?></span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">所属客户</span>
                    <span class="info-value"><?php echo htmlspecialchars($solution['customer_name'] ?? '未指定'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">创建人员</span>
                    <span class="info-value"><?php echo htmlspecialchars($solution['creator_name'] ?? '-'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">创建日期</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($solution['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">最近更新</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($solution['updated_at'])); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($solution['project_background'])): ?>
        <div class="content-card">
            <h2 class="section-title">项目背景</h2>
            <div class="solution-content">
                <?php echo nl2br(htmlspecialchars($solution['project_background'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="content-card">
            <h2 class="section-title">详细方案内容</h2>
            <div class="solution-content">
                <?php echo $solution['solution_content']; ?>
            </div>
        </div>

        <?php if ($attachments->num_rows > 0): ?>
        <div class="content-card">
            <h2 class="section-title">相关附件 (<?php echo $attachments->num_rows; ?>)</h2>
            <div class="attachment-list">
                <?php while($att = $attachments->fetch_assoc()): ?>
                <a href="<?php echo htmlspecialchars($att['file_path']); ?>" class="attachment-item" download>
                    <div class="attachment-icon">📎</div>
                    <div class="attachment-info">
                        <div class="attachment-name" title="<?php echo htmlspecialchars($att['file_name']); ?>">
                            <?php echo htmlspecialchars($att['file_name']); ?>
                        </div>
                        <div class="attachment-meta">
                            大小: <?php echo number_format($att['file_size'] / 1024, 2); ?> KB
                        </div>
                    </div>
                    <span class="attachment-dl-btn">↓</span>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($solution['remarks'])): ?>
        <div class="content-card">
            <h2 class="section-title">备注信息</h2>
            <div class="solution-content">
                <p style="color: #64748b; font-style: italic;">
                    <?php echo nl2br(htmlspecialchars($solution['remarks'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>