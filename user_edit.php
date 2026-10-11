<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();
if (!$_SESSION['user_id'] ?? false) { header('Location: login.php'); exit; }


$conn = getDBConnection();
$error = '';
$success = '';

// 获取用户ID
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// admin 只能编辑自己或自己下属的用户
$role_val = $_SESSION['role'] ?? '';
if ($role_val === 'admin' && $user_id !== intval($_SESSION['user_id'])) {
    $conn_chk = getDBConnection();
    $chk = $conn_chk->prepare("SELECT id FROM users WHERE id=? AND parent_id=?");
    $chk->bind_param("ii", $user_id, $_SESSION['user_id']);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        writeLog($conn, 'users', 'update', '修改用户');
        header('Location: users.php?error=no_permission');
        exit;
    }
}

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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $nickname = trim($_POST['nickname']);
    $user_role = $_POST['role'];
    // 角色由表单选择决定，不与手机号绑定
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 验证
    if (empty($username) || empty($nickname) || empty($phone)) {
        $error = '请填写所有必填字段';
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $error = '请输入有效的11位手机号';
    } else {
        // 检查用户名是否已被其他用户使用
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = '用户名已被其他用户使用';
        } else {
            // 更新用户信息
            if (!empty($password)) {
                // 如果填写了新密码，则更新密码
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, nickname = ?, role = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssssssi", $username, $hashed_password, $nickname, $user_role, $email, $phone, $user_id);
            } else {
                // 不更新密码
                $stmt = $conn->prepare("UPDATE users SET username = ?, nickname = ?, role = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("sssssi", $username, $nickname, $user_role, $email, $phone, $user_id);
            }
            
            if ($stmt->execute()) {
                $success = '用户信息更新成功！';
                // 重新获取用户信息
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error = '更新失败：' . $conn->error;
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>
<?php $page_title = '编辑用户 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
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

        .form-container {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }

        /* 页面头部 */

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

        /* 表单卡片 */

        .form-label .required {
            color: #f56565;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
        }

        .form-help {
            font-size: 13px;
            color: #718096;
            margin-top: 4px;
        }

        /* 表单按钮 */

        /* 消息提示 */

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .info-box {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #2c5282;
        }

        @media (max-width: 768px) {
            

            .btn-primary,
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <div class="form-container">
            <div class="page-header">
                <h1 class="page-title">✏️ 编辑用户</h1>
                <div class="breadcrumb">
                    <a href="index.php">首页</a>
                    <span>/</span>
                    <a href="users.php">用户管理</a>
                    <span>/</span>
                    <span>编辑用户</span>
                </div>
            </div>

            <div class="form-card">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span>❌</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <span>✅</span>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    💡 提示：如果不需要修改密码，请将密码字段留空
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">
                            用户名 <span class="required">*</span>
                        </label>
                        <input type="text" name="username" class="form-input" required 
                               pattern="[a-zA-Z0-9_]{3,20}" 
                               title="用户名只能包含字母、数字和下划线，长度3-20位"
                               value="<?php echo htmlspecialchars($user['username'] ?? $user['nickname'] ?? ''); ?>"
                               placeholder="请输入用户名（3-20位字母、数字或下划线）">
                        <div class="form-help">用户名用于登录，只能包含字母、数字和下划线</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            昵称 <span class="required">*</span>
                        </label>
                        <input type="text" name="nickname" class="form-input" required 
                               value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>"
                               placeholder="请输入昵称">
                        <div class="form-help">昵称将显示在系统中</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="changePasswordToggle" onchange="togglePassword(this)">
                            &nbsp;修改密码
                        </label>
                        <div id="passwordField" style="display:none;margin-top:8px">
                            <input type="password" name="password" id="passwordInput" class="form-input"
                                   minlength="6" autocomplete="new-password"
                                   placeholder="请输入新密码（至少6位）">
                            <div class="form-help">勾选"修改密码"后才能输入新密码</div>
                        </div>
                    </div>
                    <script>
                    function togglePassword(cb){
                        var f=document.getElementById('passwordField');
                        var inp=document.getElementById('passwordInput');
                        f.style.display=cb.checked?'block':'none';
                        if(!cb.checked){ inp.value=''; inp.removeAttribute('required'); }
                    }
                    // 提交前如果未勾选则清空密码字段
                    document.querySelector('form').addEventListener('submit',function(){
                        if(!document.getElementById('changePasswordToggle').checked){
                            document.getElementById('passwordInput').value='';
                        }
                    });
                    </script>

                    <div class="form-group">
                        <label class="form-label">
                            角色 <span class="required">*</span>
                        </label>
                        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                                        <input type="hidden" name="role" value="super_admin">
                                        <input type="text" class="form-input" value="超级管理员" disabled>
                                        <?php else: ?>
                                        <select name="role" class="form-select" required>
                            <option value="super_admin" <?php echo ($user['role'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>超级管理员</option>
                            <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>管理员</option>
                            <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>普通用户</option>
                        </select>
                        <?php endif; ?>
                                        <div class="form-help">管理员拥有所有权限，普通用户权限受限</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">邮箱</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               placeholder="请输入邮箱地址（可选）">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            手机号 <span class="required">*</span>
                        </label>
                        <input type="tel" name="phone" class="form-input" required
                               pattern="1[3-9][0-9]{9}"
                               title="请输入有效的11位手机号"
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                               placeholder="请输入11位手机号">
                        <div class="form-help">手机号用于登录，必须是有效的11位号码</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">💾 保存修改</button>
                        <a href="users.php" class="btn-secondary">取消</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>