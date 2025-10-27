<?php
session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

// 只有管理员可以访问用户管理
if ($role !== 'admin') {
    die('您没有权限访问此页面');
}

$conn = getDBConnection();

// 处理搜索
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
if ($search) {
    $search_term = $conn->real_escape_string($search);
    // 兼容username和nickname字段
    $where = " WHERE (username LIKE '%$search_term%' OR nickname LIKE '%$search_term%' OR role LIKE '%$search_term%')";
}

// 处理消息提示
$message = '';
$message_type = '';

if (isset($_GET['success'])) {
    $message_type = 'success';
    switch ($_GET['success']) {
        case 'added':
            $message = '用户添加成功！';
            break;
        case 'deleted':
            $username = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '用户';
            $message = "用户 「{$username}」 已被删除！";
            break;
        case 'updated':
            $message = '用户信息更新成功！';
            break;
    }
}

if (isset($_GET['error'])) {
    $message_type = 'error';
    switch ($_GET['error']) {
        case 'invalid_id':
            $message = '无效的用户ID';
            break;
        case 'cannot_delete_self':
            $message = '您不能删除自己的账号';
            break;
        case 'user_not_found':
            $message = '用户不存在';
            break;
        case 'delete_failed':
            $message = '删除失败，请稍后重试';
            break;
    }
}

// 获取用户列表
$sql = "SELECT * FROM users $where ORDER BY created_at DESC";
$result = $conn->query($sql);

// 获取用户统计
$stats_sql = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count
FROM users";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 企业管理系统</title>
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

        /* 顶部导航栏 */
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

        /* 主内容区 */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* 页面头部 */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
        }

        /* 搜索和操作栏 */
        .action-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        /* 按钮 */
        .btn-primary {
            padding: 10px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            padding: 6px 12px;
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-danger {
            padding: 6px 12px;
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #feb2b2;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background: #fed7d7;
        }

        /* 表格容器 */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f7fafc;
        }

        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #2d3748;
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* 徽章 */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.admin {
            background: #fef5e7;
            color: #d68910;
        }

        .badge.user {
            background: #e8f4fd;
            color: #2874a6;
        }

        .badge.active {
            background: #d5f4e6;
            color: #0e6f4b;
        }

        .badge.inactive {
            background: #fadbd8;
            color: #922b21;
        }

        /* 操作按钮 */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state-text {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .empty-state-subtext {
            font-size: 14px;
            color: #cbd5e0;
        }

        /* 消息提示 */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            color: inherit;
            opacity: 0.6;
            background: none;
            border: none;
            padding: 0;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <div class="navbar-brand-icon">📊</div>
            <span>企业管理系统</span>
        </a>
        <a href="index.php" class="btn-back">← 返回主页</a>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" id="alert">
                <span><?php echo $message_type === 'success' ? '✅' : '❌'; ?></span>
                <span><?php echo $message; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1 class="page-title">👤 用户管理</h1>
            <a href="user_add.php" class="btn-primary">
                <span>➕</span>
                <span>添加用户</span>
            </a>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">全部用户</div>
                <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">管理员</div>
                <div class="stat-value"><?php echo $stats['admin_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">普通用户</div>
                <div class="stat-value"><?php echo $stats['user_count'] ?? 0; ?></div>
            </div>
        </div>

        <!-- 搜索和操作栏 -->
        <div class="action-bar">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="搜索用户名、昵称或角色..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <span class="search-icon">🔍</span>
            </form>
            <?php if ($search): ?>
                <a href="users.php" class="btn-secondary">清除搜索</a>
            <?php endif; ?>
        </div>

        <!-- 用户列表 -->
        <div class="content-card">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th>用户名</th>
                            <th>昵称</th>
                            <th width="120">角色</th>
                            <th width="160">创建时间</th>
                            <th width="160">最后登录</th>
                            <th width="220">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $user['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($user['username'] ?? $user['nickname'] ?? '未设置'); ?></td>
                                <td><?php echo htmlspecialchars($user['nickname'] ?? '未设置'); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] ?? 'user'; ?>">
                                        <?php echo ($user['role'] ?? 'user') === 'admin' ? '管理员' : '普通用户'; ?>
                                    </span>
                                </td>
                                <td><?php echo isset($user['created_at']) ? date('Y-m-d H:i', strtotime($user['created_at'])) : '-'; ?></td>
                                <td>
                                    <?php 
                                    echo isset($user['last_login']) && $user['last_login'] 
                                        ? date('Y-m-d H:i', strtotime($user['last_login'])) 
                                        : '从未登录'; 
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="user_view.php?id=<?php echo $user['id']; ?>" class="btn-secondary">查看</a>
                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn-secondary">编辑</a>
                                        <?php if ($user['id'] != $user_id): ?>
                                            <button class="btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nickname'] ?? $user['username'] ?? 'user', ENT_QUOTES); ?>')">删除</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <div class="empty-state-text">暂无用户数据</div>
                    <div class="empty-state-subtext">
                        <?php if ($search): ?>
                            没有找到符合条件的用户
                        <?php else: ?>
                            点击"添加用户"按钮创建第一个用户
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // 自动隐藏消息提示
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 3000);
            }
        });

        function deleteUser(userId, userName) {
            if (confirm('确定要删除用户 "' + userName + '" 吗？\n\n此操作不可恢复！')) {
                window.location.href = 'user_delete.php?id=' + userId;
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>