<?php
// 引入配置文件
require_once 'config.php';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = trim($input['phone'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => '请输入手机号']);
            exit;
        }
        
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => '请输入密码']);
            exit;
        }
        
        $conn = getDBConnection();
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => '数据库连接失败']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id, nickname, role, password, status FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if ($user['status'] != 1) {
                echo json_encode(['success' => false, 'message' => '账户已被禁用']);
                exit;
            }
            
            if (password_verify($password, $user['password'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nickname'] = $user['nickname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['phone'] = $phone;
                
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'nickname' => $user['nickname'],
                        'role' => $user['role']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '密码错误']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '用户不存在']);
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '系统错误，请稍后重试']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 企业管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 48px 40px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin-bottom: 16px;
        }

        .logo h1 {
            font-size: 24px;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .logo p {
            color: #718096;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
        }

        input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
        }

        input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input::placeholder {
            color: #cbd5e0;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .error-message {
            background: #fff5f5;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            border-left: 4px solid #fc8181;
            display: none;
        }

        .footer-text {
            text-align: center;
            color: #a0aec0;
            font-size: 13px;
            margin-top: 24px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">📊</div>
            <h1>企业管理系统</h1>
            <p>欢迎回来，请登录您的账户</p>
        </div>

        <div class="error-message" id="errorMessage"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="phone">手机号</label>
                <div class="input-wrapper">
                    <span class="input-icon">📱</span>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        placeholder="请输入手机号" 
                        maxlength="11"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">密码</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="请输入密码"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn-login">登录</button>
        </form>

        <div class="footer-text">
            <p>© 2025 企业管理系统. 保留所有权利.</p>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        const btnLogin = document.querySelector('.btn-login');

        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const phone = phoneInput.value.trim();
            const password = passwordInput.value;

            if (phone.length !== 11) {
                showError('请输入正确的11位手机号');
                return;
            }

            if (!password) {
                showError('请输入密码');
                return;
            }

            hideError();
            btnLogin.textContent = '登录中...';
            btnLogin.disabled = true;

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        phone: phone,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('登录成功！正在跳转...');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 500);
                } else {
                    showError(data.message || '登录失败');
                    btnLogin.textContent = '登录';
                    btnLogin.disabled = false;
                }
            } catch (error) {
                showError('网络错误，请稍后重试');
                console.error('登录错误:', error);
                btnLogin.textContent = '登录';
                btnLogin.disabled = false;
            }
        });

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            errorMessage.style.background = '#fff5f5';
            errorMessage.style.color = '#c53030';
            errorMessage.style.borderLeftColor = '#fc8181';
        }

        function showSuccess(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            errorMessage.style.background = '#f0fff4';
            errorMessage.style.color = '#22543d';
            errorMessage.style.borderLeftColor = '#48bb78';
        }

        function hideError() {
            errorMessage.style.display = 'none';
        }
    </script>
</body>
</html>