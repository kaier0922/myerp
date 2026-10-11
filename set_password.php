<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_login();

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? '';
$conn = getDBConnection();

// 检查是否已有密码
$stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 处理提交
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd1 = $_POST['password'] ?? '';
    $pwd2 = $_POST['password2'] ?? '';
    if (strlen($pwd1) < 6) {
        $error = '密码至少6位';
    } elseif ($pwd1 !== $pwd2) {
        $error = '两次输入的密码不一致';
    } else {
        $hash = password_hash($pwd1, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        writeLog($conn, 'users', 'update', '修改密码');
        $success = true;
        // 清除强制设置密码标记
        unset($_SESSION['login_type']);
    }
}

$conn->close();
$already_set = !empty($user['password']);
?>
<?php $page_title = '设置密码 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.card{background:white;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);padding:40px;width:100%;max-width:420px;}
.logo{text-align:center;margin-bottom:32px;}
.logo-icon{width:64px;height:64px;background:linear-gradient(135deg,#1f6dad,#135286);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 12px;}
.logo h1{font-size:20px;font-weight:700;color:#1a202c;}
.logo p{font-size:13px;color:#718096;margin-top:6px;}
.alert-error{background:#fff5f5;color:#c53030;border-left:4px solid #fc8181;}
.form-group label{display:block;font-size:13px;font-weight:600;color:#4a5568;margin-bottom:8px;}
.form-group input{width:100%;padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;transition:border-color .2s;}
.form-group input:focus{border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.hint{font-size:12px;color:#a0aec0;margin-top:4px;}
.btn-skip{display:block;text-align:center;margin-top:14px;font-size:13px;color:#a0aec0;text-decoration:none;}
.btn-skip:hover{color:#718096;}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🔐</div>
        <h1>设置登录密码</h1>
        <p>你好 <?=htmlspecialchars($nickname)?>，<?=$already_set?'修改你的登录密码':'为账号设置密码，下次可直接密码登录'?></p>
    </div>

    <?php if(!$already_set && !$success): ?>
    <div class="alert alert-warning">
        💡 你目前通过验证码登录，建议设置密码方便下次快速登录
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="alert alert-error">❌ <?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <?php if($success): ?>
    <div class="alert alert-success">✅ 密码设置成功！</div>
    <script>setTimeout(()=>location.href='index.php', 1500);</script>
    <?php else: ?>
    <form method="POST">
        <div class="form-group">
            <label>新密码</label>
            <input type="password" name="password" placeholder="请输入新密码" minlength="6" required>
            <div class="hint">至少6位，建议包含字母和数字</div>
        </div>
        <div class="form-group">
            <label>确认密码</label>
            <input type="password" name="password2" placeholder="再次输入密码" required>
        </div>
        <button type="submit" class="btn-primary">💾 保存密码</button>
    </form>
    <?php if(!$already_set): ?>
    <a href="index.php" class="btn-skip">下次再设置（每次登录都会提醒）</a>
    <?php else: ?>
    <a href="index.php" class="btn-skip">取消，返回系统</a>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
