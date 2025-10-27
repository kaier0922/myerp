<?php
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

// 获取报价单统计
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = '草稿' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = '已发送' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = '已成交' THEN 1 ELSE 0 END) as closed,
        SUM(final_amount) as total_amount
    FROM quotes
")->fetch_assoc();

// 获取报价单列表
$quotes = $conn->query("
    SELECT q.*, c.company_name, c.contact_name, u.nickname as creator_name
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.user_id = u.id
    ORDER BY q.created_at DESC
    LIMIT 50
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报价管理 - 企业管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            text-decoration: none;
        }

        .navbar-brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .btn-back {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
        }

        .page-header {
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
        }

        .stat-value.money {
            color: #10b981;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-item {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #4a5568;
            transition: all 0.2s;
        }

        .filter-item:hover, .filter-item.active {
            background: #eef2ff;
            border-color: #667eea;
            color: #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.draft { background: #e2e8f0; color: #4a5568; }
        .badge.sent { background: #dbeafe; color: #1e3a8a; }
        .badge.closed { background: #d1fae5; color: #065f46; }
        .badge.expired { background: #fee2e2; color: #991b1b; }

        .template-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            background: #f3f4f6;
            color: #6b7280;
            margin-right: 8px;
        }

        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #4a5568;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-sm:hover {
            background: #edf2f7;
        }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #1a202c;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .template-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .template-card:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .template-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .template-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .template-desc {
            font-size: 13px;
            color: #718096;
            line-height: 1.5;
        }

        .btn-close {
            margin-top: 24px;
            width: 100%;
            padding: 12px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .template-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <div class="navbar-brand-icon">📊</div>
            <span>企业管理系统</span>
        </a>
        <a href="index.php" class="btn-back">← 返回主页</a>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">💰 报价管理</h1>
            <button class="btn-primary" onclick="showTemplateModal()">+ 新建报价单</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">报价单总数</div>
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">草稿</div>
                <div class="stat-value"><?php echo $stats['draft'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">已发送</div>
                <div class="stat-value"><?php echo $stats['sent'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">已成交</div>
                <div class="stat-value"><?php echo $stats['closed'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">成交总额</div>
                <div class="stat-value money">¥<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">报价单列表</h2>
            </div>

            <div class="filter-bar">
                <div class="filter-item active" onclick="filterByStatus('all')">全部</div>
                <div class="filter-item" onclick="filterByStatus('草稿')">草稿</div>
                <div class="filter-item" onclick="filterByStatus('已发送')">已发送</div>
                <div class="filter-item" onclick="filterByStatus('已成交')">已成交</div>
                <div class="filter-item" onclick="filterByStatus('已过期')">已过期</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>报价单号</th>
                        <th>模板类型</th>
                        <th>客户名称</th>
                        <th>项目名称</th>
                        <th>金额</th>
                        <th>状态</th>
                        <th>日期</th>
                        <th>创建人</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quotes->num_rows > 0): ?>
                        <?php while ($row = $quotes->fetch_assoc()): ?>
                            <?php
                            $template_names = [
                                'assembled_pc' => '组装电脑',
                                'brand_pc' => '品牌整机',
                                'weak_current' => '弱电工程',
                                'strong_current' => '强电工程'
                            ];
                            $status_class = [
                                '草稿' => 'draft',
                                '已发送' => 'sent',
                                '已成交' => 'closed',
                                '已过期' => 'expired'
                            ];
                            ?>
                            <tr data-status="<?php echo $row['status']; ?>">
                                <td><strong><?php echo htmlspecialchars($row['quote_no']); ?></strong></td>
                                <td>
                                    <span class="template-badge">
                                        <?php echo $template_names[$row['template_type']] ?? $row['template_type']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['company_name'] ?? '未指定客户'); ?></td>
                                <td><?php echo htmlspecialchars($row['project_name'] ?? '-'); ?></td>
                                <td><strong>¥<?php echo number_format($row['final_amount'], 2); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $status_class[$row['status']] ?? 'draft'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($row['quote_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['creator_name'] ?? ''); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="quote_edit.php?id=<?php echo $row['id']; ?>" class="btn-sm">编辑</a>
                                        <a href="quote_view.php?id=<?php echo $row['id']; ?>" class="btn-sm" target="_blank">预览</a>
                                        <button class="btn-sm" onclick="deleteQuote(<?php echo $row['id']; ?>)">删除</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #a0aec0; padding: 40px;">
                                暂无报价单数据，点击右上角"新建报价单"开始创建
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- 选择模板模态框 -->
    <div id="templateModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header">选择报价模板</h2>
            
            <div class="template-grid">
                <div class="template-card" onclick="createQuote('assembled_pc')">
                    <div class="template-icon">🖥️</div>
                    <div class="template-title">组装电脑</div>
                    <div class="template-desc">适用于DIY组装台式机、游戏主机等，包含详细配件清单</div>
                </div>

                <div class="template-card" onclick="createQuote('brand_pc')">
                    <div class="template-icon">💻</div>
                    <div class="template-title">品牌整机</div>
                    <div class="template-desc">适用于品牌电脑、笔记本、服务器、网络设备等原装产品</div>
                </div>

                <div class="template-card" onclick="createQuote('weak_current')">
                    <div class="template-icon">📡</div>
                    <div class="template-title">弱电工程</div>
                    <div class="template-desc">适用于网络布线、监控系统、门禁系统等弱电施工项目</div>
                </div>

                <div class="template-card" onclick="createQuote('strong_current')">
                    <div class="template-icon">⚡</div>
                    <div class="template-title">强电工程</div>
                    <div class="template-desc">适用于电力安装、配电系统、照明工程等强电施工项目</div>
                </div>
            </div>

            <button class="btn-close" onclick="hideTemplateModal()">取消</button>
        </div>
    </div>

    <script>
        function showTemplateModal() {
            document.getElementById('templateModal').classList.add('active');
        }

        function hideTemplateModal() {
            document.getElementById('templateModal').classList.remove('active');
        }

        function createQuote(templateType) {
            window.location.href = 'quote_create.php?template=' + templateType;
        }

        function filterByStatus(status) {
            // 更新按钮状态
            document.querySelectorAll('.filter-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.classList.add('active');

            // 筛选表格行
            const rows = document.querySelectorAll('tbody tr[data-status]');
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function deleteQuote(id) {
            if (confirm('确定要删除这个报价单吗？')) {
                // TODO: 实现删除功能
                alert('删除功能开发中... ID: ' + id);
            }
        }

        // 点击模态框外部关闭
        document.getElementById('templateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideTemplateModal();
            }
        });
    </script>
</body>
</html>