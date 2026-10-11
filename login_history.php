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

// 获取筛选参数
$user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$ip_filter = isset($_GET['ip']) ? trim($_GET['ip']) : '';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if ($user_id_filter > 0) {
    $where_conditions[] = "l.user_id = ?";
    $params[] = $user_id_filter;
    $types .= 'i';
}

if ($status_filter) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($ip_filter) {
    $where_conditions[] = "l.ip_address LIKE ?";
    $params[] = '%' . $ip_filter . '%';
    $types .= 's';
}

$where_sql = '';
if (count($where_conditions) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// 查询总数
$count_sql = "SELECT COUNT(*) as total FROM login_logs l $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// 查询登录日志
$sql = "SELECT l.*, u.nickname, u.phone as user_phone 
        FROM login_logs l
        LEFT JOIN users u ON l.user_id = u.id
        $where_sql
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// 获取所有用户列表（用于筛选）
$users_sql = "SELECT id, nickname FROM users ORDER BY nickname";
$users_result = $conn->query($users_sql);

// 统计数据
$stats_sql = "SELECT 
    COUNT(*) as total_logs,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT ip_address) as unique_ips
FROM login_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<?php $page_title = '登录历史 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

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

        .page-subtitle {
            font-size: 14px;
            color: #718096;
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

        /* 筛选表单 */
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 13px;
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .filter-input, .filter-select {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #1f6dad;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
        }

        /* 表格 */

        thead {
            background: #f7fafc;
        }

        tbody

        .badge.failed {
            background: #fadbd8;
            color: #922b21;
        }

        .device-info {
            font-size: 13px;
            color: #718096;
            line-height: 1.6;
        }

        .device-detail {
            cursor: pointer;
            color: #1f6dad;
            text-decoration: underline;
        }

        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 20px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f7fafc;
        }

        .page-link.active {
            background: #1f6dad;
            color: white;
            border-color: #1f6dad;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a202c;
        }

        .modal-close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #a0aec0;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 16px;
        }

        .detail-label {
            font-weight: 600;
            color: #4a5568;
        }

        .detail-value {
            color: #2d3748;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <div class="navbar-brand-icon">📊</div>
            <span>电脑装机工程智能报价系统</span>
        </a>
        <a href="users.php" class="btn-back">← 返回用户管理</a>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">🔐 登录历史</h1>
            <p class="page-subtitle">查看所有用户的登录记录和设备信息</p>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">总登录次数</div>
                <div class="stat-value"><?php echo number_format($stats['total_logs'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">成功登录</div>
                <div class="stat-value" style="color: #0e6f4b;"><?php echo number_format($stats['success_count'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">失败尝试</div>
                <div class="stat-value" style="color: #922b21;"><?php echo number_format($stats['failed_count'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">活跃用户</div>
                <div class="stat-value"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">唯一IP数</div>
                <div class="stat-value"><?php echo number_format($stats['unique_ips'] ?? 0); ?></div>
            </div>
        </div>

        <!-- 筛选表单 -->
        <div class="filter-card">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">用户</label>
                        <select name="user_id" class="filter-select">
                            <option value="">全部用户</option>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user_id_filter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nickname']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">状态</label>
                        <select name="status" class="filter-select">
                            <option value="">全部状态</option>
                            <option value="success" <?php echo $status_filter == 'success' ? 'selected' : ''; ?>>成功</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>失败</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">开始日期</label>
                        <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">结束日期</label>
                        <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">IP地址</label>
                        <input type="text" name="ip" class="filter-input" placeholder="搜索IP..." value="<?php echo htmlspecialchars($ip_filter); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-primary">🔍 筛选</button>
                    <a href="login_history.php" class="btn-secondary">清除筛选</a>
                </div>
            </form>
        </div>

        <!-- 登录日志表格 -->
        <div class="content-card">
            <table>
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th>用户</th>
                        <th>状态</th>
                        <th>IP地址</th>
                        <th>设备信息</th>
                        <th>登录时间</th>
                        <th width="80">详情</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $log['id']; ?></td>
                            <td>
                                <?php if ($log['nickname']): ?>
                                    <strong><?php echo htmlspecialchars($log['nickname']); ?></strong><br>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($log['user_phone']); ?></small>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">未知用户</span><br>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($log['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $log['status']; ?>">
                                    <?php echo $log['status'] == 'success' ? '✅ 成功' : '❌ 失败'; ?>
                                </span>
                                <?php if ($log['fail_reason']): ?>
                                    <br><small style="color: #922b21;"><?php echo htmlspecialchars($log['fail_reason']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td class="device-info">
                                <strong><?php echo htmlspecialchars($log['browser_name']); ?></strong> 
                                <?php echo htmlspecialchars($log['browser_version']); ?><br>
                                <?php echo htmlspecialchars($log['os_name']); ?> 
                                <?php echo htmlspecialchars($log['os_version']); ?> 
                                (<?php echo htmlspecialchars($log['device_type']); ?>)
                            </td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <a href="#" class="device-detail" onclick="showDetail(<?php echo $log['id']; ?>); return false;">查看</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?><?php echo $user_id_filter ? '&user_id='.$user_id_filter : ''; ?><?php echo $status_filter ? '&status='.$status_filter : ''; ?>" class="page-link">« 上一页</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $user_id_filter ? '&user_id='.$user_id_filter : ''; ?><?php echo $status_filter ? '&status='.$status_filter : ''; ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo $user_id_filter ? '&user_id='.$user_id_filter : ''; ?><?php echo $status_filter ? '&status='.$status_filter : ''; ?>" class="page-link">下一页 »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- 详情模态框 -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeDetail()">&times;</span>
            <h2 class="modal-header">登录详细信息</h2>
            <div id="detailContent"></div>
        </div>
    </div>

    <script>
        function showDetail(id) {
            // 使用AJAX获取详细信息
            fetch(`get_login_detail.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDetail(data.log);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function displayDetail(log) {
            const content = `
                <div class="detail-grid">
                    <div class="detail-label">用户ID:</div>
                    <div class="detail-value">${log.user_id || '未知'}</div>
                    
                    <div class="detail-label">手机号:</div>
                    <div class="detail-value">${log.phone}</div>
                    
                    <div class="detail-label">状态:</div>
                    <div class="detail-value">${log.status == 'success' ? '✅ 成功' : '❌ 失败'}</div>
                    
                    <div class="detail-label">IP地址:</div>
                    <div class="detail-value">${log.ip_address}</div>
                    
                    <div class="detail-label">浏览器:</div>
                    <div class="detail-value">${log.browser_name} ${log.browser_version}</div>
                    
                    <div class="detail-label">浏览器引擎:</div>
                    <div class="detail-value">${log.engine_name}</div>
                    
                    <div class="detail-label">操作系统:</div>
                    <div class="detail-value">${log.os_name} ${log.os_version}</div>
                    
                    <div class="detail-label">设备类型:</div>
                    <div class="detail-value">${log.device_type}</div>
                    
                    <div class="detail-label">设备厂商:</div>
                    <div class="detail-value">${log.device_vendor || '-'}</div>
                    
                    <div class="detail-label">设备型号:</div>
                    <div class="detail-value">${log.device_model || '-'}</div>
                    
                    <div class="detail-label">屏幕分辨率:</div>
                    <div class="detail-value">${log.screen_resolution || '-'}</div>
                    
                    <div class="detail-label">时区:</div>
                    <div class="detail-value">${log.timezone || '-'}</div>
                    
                    <div class="detail-label">语言:</div>
                    <div class="detail-value">${log.language || '-'}</div>
                    
                    <div class="detail-label">平台:</div>
                    <div class="detail-value">${log.platform || '-'}</div>
                    
                    <div class="detail-label">设备指纹:</div>
                    <div class="detail-value" style="font-size: 11px; word-break: break-all;">${log.device_fingerprint || '-'}</div>
                    
                    <div class="detail-label">登录时间:</div>
                    <div class="detail-value">${log.created_at}</div>
                    
                    <div class="detail-label">User-Agent:</div>
                    <div class="detail-value" style="font-size: 11px; word-break: break-all;">${log.user_agent}</div>
                </div>
            `;
            
            document.getElementById('detailContent').innerHTML = content;
            document.getElementById('detailModal').classList.add('show');
        }

        function closeDetail() {
            document.getElementById('detailModal').classList.remove('show');
        }

        // 点击模态框外部关闭
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetail();
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
