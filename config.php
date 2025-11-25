<?php
// config.php - 数据库配置文件

// 主业务数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'bjd');
define('DB_PASS', '你自己的密码');
define('DB_NAME', 'bjd');
define('DB_CHARSET', 'utf8mb4');

// 论坛（BBS）数据库配置 - 默认与主库相同，可通过环境变量覆盖
define('BBS_DB_HOST', getenv('BBS_DB_HOST') ?: DB_HOST);
define('BBS_DB_USER', getenv('BBS_DB_USER') ?: DB_USER);
define('BBS_DB_PASS', getenv('BBS_DB_PASS') ?: DB_PASS);
define('BBS_DB_NAME', getenv('BBS_DB_NAME') ?: 'ultrax');
define('BBS_DB_CHARSET', getenv('BBS_DB_CHARSET') ?: DB_CHARSET);
define('BBS_TABLE_PREFIX', getenv('BBS_TABLE_PREFIX') ?: 'pre_');

// 获取数据库连接
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("数据库连接失败: " . $conn->connect_error);
        return null;
    }
    
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

// 获取论坛数据库连接
function getBBSConnection() {
    $conn = new mysqli(BBS_DB_HOST, BBS_DB_USER, BBS_DB_PASS, BBS_DB_NAME);

    if ($conn->connect_error) {
        error_log("论坛数据库连接失败: " . $conn->connect_error);
        return null;
    }

    $conn->set_charset(BBS_DB_CHARSET);
    return $conn;
}

// 错误日志函数
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'logs/app.log');
}
?>