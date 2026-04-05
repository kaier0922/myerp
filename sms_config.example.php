<?php
/**
 * 腾讯云短信配置
 * SecretId 和 SecretKey 请填入实际值
 */
define('TENCENT_SECRET_ID',  'YOUR_SECRET_ID');   // 替换为你的 SecretId
define('TENCENT_SECRET_KEY', 'YOUR_SECRET_KEY');  // 替换为你的 SecretKey
define('TENCENT_SMS_APPID',  'YOUR_SMS_APPID');
define('TENCENT_SMS_SIGN',   'YOUR_SMS_SIGN');
define('TENCENT_SMS_TPL_ID', 'YOUR_TPL_ID');

// 验证码配置
define('SMS_CODE_EXPIRE',    300);   // 有效期5分钟（秒）
define('SMS_RESEND_INTERVAL',120);    // 重发间隔60秒
define('SMS_DAILY_LIMIT',    30);    // 每号每天最多10次
?>
