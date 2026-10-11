<?php
define('APP_VERSION', 'v1.5.0-local');
define('DEPLOY_SOURCE', 'win_15');
// ============================================================
// config.php — 数据库配置 + 全局安全加固
// ============================================================

// ── 本地运行覆盖（Windows 集成包由启动器自动生成，不提交 git）────
if (file_exists(__DIR__ . '/config_local.php')) {
    require_once __DIR__ . '/config_local.php';
}

// ── 数据库配置（若 config_local.php 已定义则跳过）──────────────
defined('DB_HOST')    || define('DB_HOST',    'localhost');
defined('DB_PORT')    || define('DB_PORT',    3306);
defined('DB_USER')    || define('DB_USER',    '你自己的数据库账号');
defined('DB_PASS')    || define('DB_PASS',    '你自己的数据库密码');
defined('DB_NAME')    || define('DB_NAME',    '你自己的数据库名');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// ── 全局安全响应头 ──────────────────────────────────────────
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');                    // 防点击劫持
    header('X-Content-Type-Options: nosniff');                // 防 MIME 嗅探
    header('X-XSS-Protection: 1; mode=block');               // 旧浏览器 XSS 过滤
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:;");
}

// ── Session 安全配置 ────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);   // JS 无法读取 Cookie
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    // HTTPS 环境下开启（宝塔已配置 SSL 则取消注释）
    // ini_set('session.cookie_secure', 1);
}

// ── 数据库连接 ──────────────────────────────────────────────
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        error_log("数据库连接失败: " . $conn->connect_error);
        // 不把错误信息暴露给前端
        die(json_encode(['success'=>false,'message'=>'系统繁忙，请稍后重试'], JSON_UNESCAPED_UNICODE));
    }
    $conn->set_charset(DB_CHARSET);
    if (!defined('_SVC_CHECKED')) {
        define('_SVC_CHECKED', true);
        $__r        = $conn->query("SELECT setting_value FROM system_settings WHERE user_id=0 AND setting_key='static_asset_rev' LIMIT 1");
        $__row      = $__r ? $__r->fetch_assoc() : null;
        $__expected = $__row['setting_value'] ?? '';
        $__raw      = hash_file('sha256', __DIR__ . '/License.php');
        $__content  = file_get_contents(__DIR__ . '/License.php');
        $__norm     = hash('sha256', str_replace("\r\n", "\n", $__content));
        define('_SVC_OK', $__expected !== '' &&
            (hash_equals($__expected, $__raw) || hash_equals($__expected, $__norm)));
        unset($__r, $__row, $__expected, $__raw, $__content, $__norm);
    }
    return $conn;
}

// ── CSRF Token 工具函数 ─────────────────────────────────────
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['success'=>false,'message'=>'请求验证失败，请刷新页面重试'], JSON_UNESCAPED_UNICODE));
    }
}

// ── 登录失败限速（基于 Session + 文件锁）──────────────────
function login_check_limit($username) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key      = 'login_fail_' . md5($username . $_SERVER['REMOTE_ADDR']);
    $fails    = $_SESSION[$key]['count'] ?? 0;
    $last_at  = $_SESSION[$key]['last']  ?? 0;

    // 超过5次失败，锁定10分钟
    if ($fails >= 5 && (time() - $last_at) < 600) {
        $wait = 600 - (time() - $last_at);
        return ['locked' => true, 'wait' => $wait];
    }
    // 10分钟后自动重置
    if ((time() - $last_at) >= 600) {
        $_SESSION[$key] = ['count' => 0, 'last' => 0];
    }
    return ['locked' => false, 'fails' => $fails];
}

function login_record_fail($username) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = 'login_fail_' . md5($username . $_SERVER['REMOTE_ADDR']);
    $_SESSION[$key]['count'] = ($_SESSION[$key]['count'] ?? 0) + 1;
    $_SESSION[$key]['last']  = time();
}

function login_clear_fail($username) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = 'login_fail_' . md5($username . $_SERVER['REMOTE_ADDR']);
    unset($_SESSION[$key]);
}

// ── 上传文件安全校验 ────────────────────────────────────────
function secure_upload_check($file, $allowed_exts = ['jpg','jpeg','png','gif','webp','pdf'], $max_mb = 10) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => '文件上传出错（错误码：' . $file['error'] . '）'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return ['ok' => false, 'msg' => '不支持的文件格式'];
    }
    if ($file['size'] > $max_mb * 1024 * 1024) {
        return ['ok' => false, 'msg' => "文件过大，最大允许 {$max_mb}MB"];
    }
    // 检查真实 MIME（防止伪装扩展名）
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $safe_mime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    if (!in_array($mime, $safe_mime)) {
        return ['ok' => false, 'msg' => '文件内容与格式不符，上传被拒绝'];
    }
    // 图片二次检验：用 getimagesize 确认是真实图片
    if (strpos($mime, 'image/') === 0 && !getimagesize($file['tmp_name'])) {
        return ['ok' => false, 'msg' => '无效的图片文件'];
    }
    return ['ok' => true, 'ext' => $ext, 'mime' => $mime];
}

// ── 安全输出（防 XSS）──────────────────────────────────────
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// ── 错误日志 ────────────────────────────────────────────────
function logError($message) {
    static $log_dir;
    if (!$log_dir) {
        $log_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $log_dir . DIRECTORY_SEPARATOR . 'app.log');
}

// ── 多租户开关 ────────────────────────────────────────────────
define('MULTI_TENANT', false);
define('ADMIN_USER_ID', 1);

// ── 邮件 SMTP 配置 ──
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', '你自己的邮箱账号');
define('SMTP_PASS', '你自己的邮箱密码或授权码');
define('MAIL_TO',   '接收通知的邮箱地址');
define('LIC_SECRET', '请改成你自己的随机密钥（仅用于签名校验，不是加密密钥）');
define('AD_WATERMARK', base64_decode('5Yev5bCU55S16ISR'));

// 已登录用户访问时上报一次(节流10分钟)，用于统计真实使用时段和活跃度
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/License.php';
        License::reportVisit();
    } catch (Throwable $e) {
        // 静默忽略，不影响正常使用
    }
}

// 装机数统计：不管登没登录、有没有授权SN都报（节流6小时），用于统计总使用量
try {
    require_once __DIR__ . '/License.php';
    License::reportInstall();
} catch (Throwable $e) {
    // 静默忽略，不影响正常使用
}

