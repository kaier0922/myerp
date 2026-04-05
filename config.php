<?php
// config.php - 数据库配置文件

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', '***');
define('DB_PASS', '你自己的密码');
define('DB_NAME', '***');
define('DB_CHARSET', 'utf8mb4');

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

// 错误日志函数
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'logs/app.log');
}
?>
