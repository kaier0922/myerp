<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();


$conn = getDBConnection();
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nickname = trim($_POST['nickname']);
    $user_role = $_POST['role'];
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // 验证
    if (empty($username) || empty($password) || empty($nickname) || empty($phone)) {
        $error = '请填写所有必填字段';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少为6位';
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $error = '请输入有效的11位手机号';
    } else {
        // 检查用户名是否已存在
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = '用户名已存在';
        } else {
            // 加密密码
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 插入新用户
            $parent_id = intval($_SESSION['user_id']);
            $stmt = $conn->prepare("INSERT INTO users (username, password, nickname, role, email, phone, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssssi", $username, $hashed_password, $nickname, $user_role, $email, $phone, $parent_id);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                // 自动复制超管的系统设置给新用户
                $super = $conn->query("SELECT id FROM users WHERE phone='13316973303'")->fetch_assoc();
                if ($super) {
                    $rows = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE user_id={$super['id']}")->fetch_all(MYSQLI_ASSOC);
                    $ins = $conn->prepare("INSERT IGNORE INTO system_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
                    foreach ($rows as $row) { $ins->bind_param("iss", $new_id, $row['setting_key'], $row['setting_value']); $ins->execute(); }
                    $ins->close();
                }
                $success = '用户添加成功！';
                writeLog($conn, 'users', 'create', '新建用户');
                header('Location: users.php?success=added');
                exit;
            } else {
                $error = '添加失败：' . $conn->error;
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>
<?php $page_title = '添加用户 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

        .btn-primary-btn { padding:12px 32px; background:linear-gradient(135deg,#1f6dad 0%,#135286 100%); color:white; border:none; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; transition:all .2s; }
        .btn-primary-btn:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(31,109,173,.4); }
        .btn-cancel { padding:12px 32px; background:white; color:#4a5568; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-cancel:hover { background:#f7fafc; }

        /* 表单卡片 */

        .form-label .required { color:#f56565; }
        .form-input, .form-select { width:100%; padding:10px 16px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; transition:all .2s; font-family:inherit; }
        .form-input:focus, .form-select:focus { outline:none; border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
        .form-help { font-size:13px; color:#718096; margin-top:4px; }
        .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #ef4444; }
        .password-strength { margin-top: 8px; font-size: 13px; }
        .strength-bar  { height:4px; background:#e2e8f0; border-radius:2px; margin-bottom:4px; overflow:hidden; }
        .strength-fill { height:100%; transition:all .3s; background:#cbd5e0; }
        .strength-fill.weak   { width:33%; background:#f56565; }
        .strength-fill.medium { width:66%; background:#ed8936; }
        .strength-fill.strong { width:100%; background:#1f6dad; }
        @media (max-width:768px) { }
        .form-card { max-width: 860px; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">➕ 添加新用户</h1>
        <a href="users.php" class="btn btn-secondary">← 返回列表</a>
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

                <form method="POST" action="" id="userForm">
                    <div class="form-group">
                        <label class="form-label">
                            用户名 <span class="required">*</span>
                        </label>
                        <input type="text" name="username" class="form-input" required 
                               pattern="[a-zA-Z0-9_]{3,20}" 
                               title="用户名只能包含字母、数字和下划线，长度3-20位"
                               placeholder="请输入用户名（3-20位字母、数字或下划线）">
                        <div class="form-help">用户名用于登录，只能包含字母、数字和下划线</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            昵称 <span class="required">*</span>
                        </label>
                        <input type="text" name="nickname" class="form-input" required 
                               placeholder="请输入昵称">
                        <div class="form-help">昵称将显示在系统中</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            密码 <span class="required">*</span>
                        </label>
                        <input type="password" name="password" class="form-input" required 
                               minlength="6" id="password"
                               placeholder="请输入密码（至少6位）">
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthBar"></div>
                            </div>
                            <span id="strengthText">密码强度：未输入</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            确认密码 <span class="required">*</span>
                        </label>
                        <input type="password" name="confirm_password" class="form-input" required 
                               minlength="6"
                               placeholder="请再次输入密码">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            角色 <span class="required">*</span>
                        </label>
                        <select name="role" class="form-select" required>
                            <option value="user">普通用户</option>
                            <option value="admin">管理员</option>
                        </select>
                        <div class="form-help">管理员拥有所有权限，普通用户权限受限</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">邮箱</label>
                        <input type="email" name="email" class="form-input" 
                               placeholder="请输入邮箱地址（可选）">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            手机号 <span class="required">*</span>
                        </label>
                        <input type="tel" name="phone" class="form-input" required
                               pattern="1[3-9][0-9]{9}"
                               title="请输入有效的11位手机号"
                               placeholder="请输入11位手机号">
                        <div class="form-help">手机号用于登录，必须是有效的11位号码</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary-btn">✅ 保存用户</button>
                        <a href="users.php" class="btn-cancel">取消</a>
                    </div>
                </form>
    </div>
</main>

    <script>
        // 密码强度检测
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'strength-fill';
            if (strength <= 2) {
                strengthBar.classList.add('weak');
                strengthText.textContent = '密码强度：弱';
            } else if (strength <= 3) {
                strengthBar.classList.add('medium');
                strengthText.textContent = '密码强度：中';
            } else {
                strengthBar.classList.add('strong');
                strengthText.textContent = '密码强度：强';
            }
            
            if (password.length === 0) {
                strengthBar.className = 'strength-fill';
                strengthText.textContent = '密码强度：未输入';
            }
        });

        // 表单验证
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const password = document.querySelector('[name="password"]').value;
            const confirmPassword = document.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('两次输入的密码不一致，请重新输入！');
                return false;
            }
        });
    </script>
</body>
</html>