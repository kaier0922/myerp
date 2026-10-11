<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_super_admin();

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$conn = getDBConnection();

// 获取用户ID
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    header('Location: users.php');
    exit;
}

// 获取用户信息
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die('用户不存在');
}

$conn->close();
?>
<?php $page_title = '查看用户 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

        /* 顶部导航栏 */

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

        .detail-container {
            width: 100%;
            max-width: 800px;
        }

        /* 页面头部 */

        .page-title-section {
            flex: 1;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            font-size: 14px;
            color: #718096;
        }

        .breadcrumb a {
            color: #1f6dad;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        /* 用户卡片 */
        .user-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .user-header {
            background: linear-gradient(135deg, #1f6dad 0%, #135286 100%);
            padding: 32px;
            text-align: center;
            color: white;
        }

        .user-username {
            font-size: 16px;
            opacity: 0.9;
        }

        .detail-section {
            padding: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }

        .detail-value {
            font-size: 15px;
            color: #2d3748;
            font-weight: 500;
        }

        .detail-value.empty {
            color: #a0aec0;
            font-style: italic;
        }

        /* 按钮 */

        .btn-danger {
            padding: 10px 24px;
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #feb2b2;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger:hover {
            background: #fed7d7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 48, 48, 0.3);
        }

        /* 徽章 */

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

        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }

            .action-buttons .btn-primary,
            .action-buttons .btn-secondary,
            .action-buttons .btn-danger {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <div class="detail-container">
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">👁️ 用户详情</h1>
                    <div class="breadcrumb">
                        <a href="index.php">首页</a>
                        <span>/</span>
                        <a href="users.php">用户管理</a>
                        <span>/</span>
                        <span>用户详情</span>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn-primary">
                        ✏️ 编辑
                    </a>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nickname'] ?? $user['username'] ?? 'user', ENT_QUOTES); ?>')" class="btn-danger">
                            🗑️ 删除
                        </button>
                    <?php endif; ?>
                    <a href="users.php" class="btn-secondary">返回列表</a>
                </div>
            </div>

            <!-- 用户信息卡片 -->
            <div class="user-card">
                <div class="user-header">
                    <div class="user-avatar">
                        <?php echo mb_substr($user['nickname'] ?? '用', 0, 1); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user['nickname'] ?? '未设置'); ?></div>
                    <div class="user-username">@<?php echo htmlspecialchars($user['username'] ?? $user['nickname'] ?? 'user'); ?></div>
                </div>

                <div class="detail-section">
                    <h2 class="section-title">基本信息</h2>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">用户ID</div>
                            <div class="detail-value">#<?php echo $user['id']; ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">用户名</div>
                            <div class="detail-value"><?php echo htmlspecialchars($user['username'] ?? $user['nickname'] ?? '未设置'); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">昵称</div>
                            <div class="detail-value"><?php echo htmlspecialchars($user['nickname'] ?? '未设置'); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">角色</div>
                            <div class="detail-value">
                                <span class="badge <?php echo $user['role'] ?? 'user'; ?>">
                                    <?php echo ($user['role'] ?? 'user') === 'admin' ? '管理员' : '普通用户'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">邮箱地址</div>
                            <div class="detail-value <?php echo empty($user['email']) ? 'empty' : ''; ?>">
                                <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : '未设置'; ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">手机号码</div>
                            <div class="detail-value <?php echo empty($user['phone']) ? 'empty' : ''; ?>">
                                <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '未设置'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h2 class="section-title">系统信息</h2>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">创建时间</div>
                            <div class="detail-value">
                                <?php echo isset($user['created_at']) ? date('Y年m月d日 H:i:s', strtotime($user['created_at'])) : '未知'; ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">更新时间</div>
                            <div class="detail-value">
                                <?php 
                                echo isset($user['updated_at']) && $user['updated_at'] 
                                    ? date('Y年m月d日 H:i:s', strtotime($user['updated_at'])) 
                                    : '从未更新'; 
                                ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">最后登录</div>
                            <div class="detail-value <?php echo empty($user['last_login']) ? 'empty' : ''; ?>">
                                <?php 
                                echo isset($user['last_login']) && $user['last_login'] 
                                    ? date('Y年m月d日 H:i:s', strtotime($user['last_login'])) 
                                    : '从未登录'; 
                                ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">登录次数</div>
                            <div class="detail-value">
                                <?php echo isset($user['login_count']) ? number_format($user['login_count']) : '0'; ?> 次
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function deleteUser(userId, userName) {
            if (confirm('确定要删除用户 "' + userName + '" 吗？\n\n此操作不可恢复！')) {
                window.location.href = 'user_delete.php?id=' + userId;
            }
        }

        function logout() {
            if (confirm('确定要退出登录吗？')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>