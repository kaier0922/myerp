<?php
// 显示所有错误（仅用于调试）
error_reporting(0);
ini_set('display_errors', 0);

// 引入配置文件
require_once 'config.php';

// 检查 UserAgentParser.php 是否存在
if (!file_exists('UserAgentParser.php')) {
    die('❌ 错误：找不到 UserAgentParser.php 文件。请确保已上传此文件到与 login.php 相同的目录。');
}

require_once 'UserAgentParser.php';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = trim($input['phone'] ?? '');
        $password = $input['password'] ?? '';
        
        // 获取客户端信息
        $clientInfo = $input['clientInfo'] ?? [];
        
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => '请输入账号或用户名']);
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
        
        // 查询用户
        $stmt = $conn->prepare("SELECT id, nickname, role, password, status, phone FROM users WHERE phone = ? OR username = ?");
        $stmt->bind_param("ss", $phone, $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // 获取登录IP
        $login_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $login_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $login_ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        // ── 登录失败锁定检查 ──
        $lock_window = date('Y-m-d H:i:s', time() - 1800); // 30分钟
        $fail_by_phone = $conn->prepare("SELECT COUNT(*) c FROM login_logs WHERE phone=? AND status='failed' AND created_at>?");
        $fail_by_phone->bind_param('ss', $phone, $lock_window);
        $fail_by_phone->execute();
        $phone_fails = intval($fail_by_phone->get_result()->fetch_assoc()['c']);

        $fail_by_ip = $conn->prepare("SELECT COUNT(*) c FROM login_logs WHERE ip_address=? AND status='failed' AND created_at>?");
        $fail_by_ip->bind_param('ss', $login_ip, $lock_window);
        $fail_by_ip->execute();
        $ip_fails = intval($fail_by_ip->get_result()->fetch_assoc()['c']);

        if ($phone_fails >= 5) {
            echo json_encode(['success'=>false,'message'=>'登录失败次数过多，账号已锁定30分钟，请稍后再试']);
            $conn->close(); exit;
        }
        if ($ip_fails >= 10) {
            echo json_encode(['success'=>false,'message'=>'该IP登录失败次数过多，已被限制30分钟']);
            $conn->close(); exit;
        }

        // 解析User-Agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $parser = new UserAgentParser($user_agent);
        $parsedInfo = $parser->parse();
        
        // 准备登录日志数据
        $logData = [
            'phone' => $phone,
            'ip_address' => $login_ip,
            'user_agent' => $user_agent,
            'browser_name' => $parsedInfo['browser']['name'],
            'browser_version' => $parsedInfo['browser']['version'],
            'engine_name' => $parsedInfo['engine'],
            'os_name' => $parsedInfo['os']['name'],
            'os_version' => $parsedInfo['os']['version'],
            'device_type' => $parsedInfo['device']['type'],
            'device_vendor' => $parsedInfo['device']['vendor'],
            'device_model' => $parsedInfo['device']['model'],
            'screen_resolution' => $clientInfo['screenResolution'] ?? null,
            'timezone' => $clientInfo['timezone'] ?? null,
            'language' => $clientInfo['language'] ?? null,
            'platform' => $clientInfo['platform'] ?? null,
            'device_fingerprint' => $clientInfo['deviceFingerprint'] ?? null,
            'canvas_fingerprint' => $clientInfo['canvasFingerprint'] ?? null,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'session_id' => session_id(),
            'fail_reason' => null,
            'status'     => null,
        ];
        
        if ($user = $result->fetch_assoc()) {
            // 用户存在，验证密码
            if ($user['status'] != 1) {
                // 记录失败日志
                $logData['status'] = 'failed';
                $logData['fail_reason'] = '账户已被禁用';
                $logData['user_id'] = $user['id'];
                logLogin($conn, $user['id'], $logData);
                
                echo json_encode(['success' => false, 'message' => '账户已被禁用']);
                $stmt->close();
                $conn->close();
                exit;
            }
            
            if (password_verify($password, $user['password'])) {
                // 登录成功
                session_start();
                session_regenerate_id(true); // 防止Session固定攻击
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nickname'] = $user['nickname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['phone'] = $phone;
                // 只有普通用户首次登录才复制（排除超管和模板账号）
                if (!in_array($user['role'] ?? '', ['super_admin', 'template'])) {
                $prod_check = $conn->query("SELECT COUNT(*) as n FROM customers WHERE user_id=" . intval($user['id']));
                if ($prod_check->fetch_assoc()['n'] == 0) {


                    // 初始化模板数据
                    $new_uid = intval($user['id']);
                    require_once __DIR__ . '/template_data.php';
                    initTemplateData($conn, $new_uid);
                    // 复制印章模板

                }
                
                // 生成浏览器简要信息
                } // end !in_array
                $browser_info = $parsedInfo['browser']['name'] . ' (' . $parsedInfo['os']['name'] . ')';
                
                // 更新用户表的最后登录信息
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_login_ip = ?, last_login_browser = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $login_ip, $browser_info, $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // 记录成功日志
                $logData['user_id'] = $user['id'];
                $logData['status'] = 'success';
                logLogin($conn, $user['id'], $logData);
                
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'nickname' => $user['nickname'],
                        'role' => $user['role']
                    ]
                ]);
            } else {
                // 密码错误
                $logData['user_id'] = $user['id'];
                $logData['status'] = 'failed';
                $logData['fail_reason'] = '密码错误';
                logLogin($conn, $user['id'], $logData);
                
                echo json_encode(['success' => false, 'message' => '密码错误']);
            }
        } else {
            // 用户不存在
            $logData['status'] = 'failed';
            $logData['fail_reason'] = '用户不存在';
            $logData['user_id'] = null;
            logLogin($conn, null, $logData);
            
            echo json_encode(['success' => false, 'message' => '用户不存在']);
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (\Throwable $e) {
        // \Throwable 同时捕获 Exception 和 Error（含 PHP 7 fatal error）
        echo json_encode([
            'success' => false,
            'message' => '系统错误：' . $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine()
        ]);
    }
    exit;
}

/**
 * 记录登录日志
 */
function logLogin($conn, $userId, $data) {
    try {
        $sql = "INSERT INTO login_logs (
            user_id, phone, status, fail_reason,
            ip_address, user_agent,
            browser_name, browser_version, engine_name,
            os_name, os_version,
            device_type, device_vendor, device_model,
            screen_resolution, timezone, language, platform,
            device_fingerprint, canvas_fingerprint,
            referrer, session_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssssssssssssssss",
            $userId,
            $data['phone'],
            $data['status'],
            $data['fail_reason'],
            $data['ip_address'],
            $data['user_agent'],
            $data['browser_name'],
            $data['browser_version'],
            $data['engine_name'],
            $data['os_name'],
            $data['os_version'],
            $data['device_type'],
            $data['device_vendor'],
            $data['device_model'],
            $data['screen_resolution'],
            $data['timezone'],
            $data['language'],
            $data['platform'],
            $data['device_fingerprint'],
            $data['canvas_fingerprint'],
            $data['referrer'],
            $data['session_id']
        );
        
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        error_log("Failed to log login: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>电脑装机工程智能报价系统 - 登录</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #1f6dad 0%, #135286 100%);
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
            background: linear-gradient(135deg, #1f6dad 0%, #135286 100%);
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
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
        }

        input::placeholder {
            color: #cbd5e0;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1f6dad 0%, #135286 100%);
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
            box-shadow: 0 8px 20px rgba(31, 109, 173, 0.4);
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
            max-height: 300px;
            overflow-y: auto;
        }

        .error-detail {
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.8;
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
        /* Tab切换 */
        .login-tabs { display:flex; margin-bottom:24px; border-bottom:2px solid #e2e8f0; }
        .login-tab  { flex:1; padding:10px; text-align:center; cursor:pointer; font-size:14px; font-weight:500; color:#718096; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; }
        .login-tab.active { color:#1f6dad; border-bottom-color:#1f6dad; }
        .tab-panel  { display:none; }
        .tab-panel.active { display:block; }
        /* 验证码按钮 */

        /* 广告横幅 */
        .ad-banner {
            display: block;
            margin-top: 20px;
            padding: 12px 16px;
            background: #f0f7ff;
            border: 1px solid #d6e8ff;
            border-radius: 10px;
            text-align: center;
            font-size: 13px;
            color: #1f6dad;
            text-decoration: none;
            transition: background .2s;
        }

        .ad-banner:hover {
            background: #e2f0ff;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">📊</div>
            <h1>电脑装机工程智能报价系统</h1>
            <p>欢迎回来，请登录您的账户</p>
        </div>

        <div class="error-message" id="errorMessage"></div>

        <!-- Tab切换 -->
        <div class="login-tabs">
        </div>

        <!-- 密码登录 -->
        <div id="tab-password" class="tab-panel active">
        <form id="loginForm">
            <div class="form-group">
                <label for="phone">账号 / 用户名</label>
                <div class="input-wrapper">
                    <span class="input-icon">📱</span>
                    <input type="text" id="phone" name="phone"
                        placeholder="用户名或账号" maxlength="50" required>
                <div id="phoneHint" style="display:none" style="font-size:12px;margin-top:4px;display:none;"></div>
                </div>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password"
                        placeholder="请输入密码" required>
                </div>
            </div>
            <button type="submit" class="btn-login">登录</button>
        </form>
        </div>

        <a class="ad-banner" href="https://www.980g.com" target="_blank" rel="noopener">🚀 装机工程ERP系统 —— 免费下载体验 980g.com</a>

        <div class="footer-text">
            <p>© 2026 装机工程智能报价系统. 保留所有权利. <?php echo defined('APP_VERSION') ? APP_VERSION : ''; ?></p>
        </div>
    </div>

    <!-- 客户端信息收集器 -->
    <script src="client-info-collector.js"></script>
    
    <script>
        const loginForm = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        const btnLogin = document.querySelector('.btn-login');

        // 检查是否加载了 ClientInfoCollector
        if (typeof ClientInfoCollector === 'undefined') {
            showError('错误：client-info-collector.js 未加载。请确保该文件已上传到服务器。');
        }



        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const phone = phoneInput.value.trim();
            const password = passwordInput.value;

            if (phone.length < 1) {
                showError('请输入账号或用户名');
                return;
            }

            if (!password) {
                showError('请输入密码');
                return;
            }

            hideError();
            btnLogin.textContent = '正在收集设备信息...';
            btnLogin.disabled = true;

            let clientInfo = {};
            
            try {
                // 收集客户端信息（如果可用）
                if (typeof ClientInfoCollector !== 'undefined') {
                    clientInfo = await ClientInfoCollector.collect();
                }
                
                btnLogin.textContent = '登录中...';
                
                // 发送登录请求
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        phone: phone,
                        password: password,
                        clientInfo: clientInfo
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('登录成功！正在跳转...');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 500);
                } else {
                    let errorMsg = data.message || '登录失败';
                    if (data.file || data.line) {
                        errorMsg += '<div class="error-detail">错误详情：' + 
                                   (data.file || '') + ' (line ' + (data.line || '') + ')</div>';
                    }
                    showError(errorMsg);
                    btnLogin.textContent = '登录';
                    btnLogin.disabled = false;
                }
            } catch (error) {
                showError('网络错误：' + error.message + 
                         '<div class="error-detail">请检查网络连接或联系管理员</div>');
                console.error('登录错误:', error);
                btnLogin.textContent = '登录';
                btnLogin.disabled = false;
            }
        });

        function showError(message) {
            errorMessage.innerHTML = message;
            errorMessage.style.display = 'block';
            errorMessage.style.background = '#fff5f5';
            errorMessage.style.color = '#c53030';
            errorMessage.style.borderLeftColor = '#fc8181';
        }

        function showSuccess(message) {
            errorMessage.innerHTML = message;
            errorMessage.style.display = 'block';
            errorMessage.style.background = '#f0fff4';
            errorMessage.style.color = '#22543d';
            errorMessage.style.borderLeftColor = '#1f6dad';
        }



        function hideError() {
            errorMessage.style.display = 'none';
        }

        function hideError() {
            errorMessage.style.display = 'none';
        }
    </script>
</body>
</html>
