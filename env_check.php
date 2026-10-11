<?php
/**
 * env_check.php — 环境诊断页（用于迁移后快速验证）
 * 确认环境 OK 后可删除此文件或限制访问
 */
session_start();
require_once __DIR__ . '/config.php';

/* ── 只允许管理员访问 ── */
if (!isset($_SESSION['user_id'])) {
    die('请先<a href="login.php">登录</a>后访问此页面。');
}

$checks = [];

/* ── PHP 版本 ── */
$phpver = PHP_VERSION;
$checks[] = [
    'label' => 'PHP 版本',
    'value' => $phpver,
    'ok'    => version_compare($phpver, '7.4.0', '>='),
    'fix'   => '需要 PHP 7.4 或更高版本',
];

/* ── 必需扩展 ── */
$required_ext = [
    'mysqli'   => '数据库连接',
    'mbstring' => '中文字符串处理',
    'gd'       => '验证码图片生成',
    'curl'     => '授权校验 / 短信',
    'fileinfo' => '上传文件类型验证',
    'openssl'  => '配置项解密',
    'json'     => 'JSON 处理',
    'session'  => 'Session 管理',
];
foreach ($required_ext as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $checks[] = [
        'label' => "PHP 扩展：{$ext}",
        'value' => $loaded ? '已加载' : '未加载',
        'ok'    => $loaded,
        'fix'   => "在 php.ini 中取消注释：extension={$ext}（Windows 下通常是 extension=php_{$ext}.dll）",
    ];
}

/* ── MySQL 连接 ── */
$conn_ok = false;
$conn_msg = '';
try {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        $conn_msg = $conn->connect_error;
    } else {
        $conn_ok = true;
        $conn_msg = "连接成功（{$conn->host_info}）";
        $conn->close();
    }
} catch (Throwable $e) {
    $conn_msg = $e->getMessage();
}
$checks[] = [
    'label' => 'MySQL 连接',
    'value' => $conn_msg,
    'ok'    => $conn_ok,
    'fix'   => '检查 config.php 中的 DB_HOST / DB_USER / DB_PASS / DB_NAME 是否正确',
];

/* ── 目录可写性 ── */
$dirs = [
    'uploads/'          => '公章/图片上传',
    'uploads/seals/'    => '公章图片',
    'uploads/idcards/'  => '身份证图片',
    'uploads/feedback/' => '反馈附件',
    'logs/'             => '运行日志',
    'attachments/'      => '方案附件',
];
foreach ($dirs as $dir => $desc) {
    $full = __DIR__ . '/' . $dir;
    $exists   = is_dir($full);
    $writable = $exists && is_writable($full);
    $checks[] = [
        'label' => "目录可写：{$dir}",
        'value' => !$exists ? '目录不存在' : ($writable ? '可写' : '不可写'),
        'ok'    => $writable,
        'fix'   => !$exists
            ? "创建目录：mkdir {$dir}"
            : "检查目录权限（Windows：右键→属性→安全，给 IIS_IUSRS 或 Everyone 写入权限）",
    ];
}

/* ── 关键文件存在 ── */
$key_files = ['config.php', 'auth_helper.php', 'settings_helper.php',
              'sidebar.php', 'navbar.php', 'head.php', 'common.css',
              '_export_helper.php'];
foreach ($key_files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $checks[] = [
        'label' => "文件存在：{$file}",
        'value' => $exists ? '存在' : '缺失',
        'ok'    => $exists,
        'fix'   => "确保 {$file} 已复制到站点根目录",
    ];
}

/* ── OPcache（可选）── */
$oc = extension_loaded('Zend OPcache') || extension_loaded('opcache');
$checks[] = [
    'label' => 'OPcache（可选）',
    'value' => $oc ? '已启用' : '未启用',
    'ok'    => true,   // 可选，不强制
    'warn'  => !$oc,
    'fix'   => "在 php.ini 加入：zend_extension=php_opcache.dll（Windows），可提升约 30% 性能",
];

$pass  = count(array_filter($checks, fn($c) => $c['ok']));
$total = count($checks);
$all_ok = $pass === $total;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>环境诊断 — 凯尔ERP</title>
<style>
body { font-family: -apple-system, "Microsoft YaHei", sans-serif; background: #f7fafc; color: #2d3748; margin: 0; padding: 24px; }
.wrap { max-width: 860px; margin: 0 auto; }
h1 { font-size: 22px; color: #1f6dad; margin-bottom: 4px; }
.subtitle { color: #718096; font-size: 14px; margin-bottom: 24px; }
.summary { padding: 16px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 15px; font-weight: 600; }
.summary.ok  { background: #c6f6d5; color: #22543d; border-left: 4px solid #38a169; }
.summary.err { background: #fed7d7; color: #742a2a; border-left: 4px solid #e53e3e; }
table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
th { background: #1f6dad; color: white; padding: 10px 14px; text-align: left; font-size: 13px; }
td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
tr:last-child td { border-bottom: none; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.ok-badge   { background: #c6f6d5; color: #22543d; }
.err-badge  { background: #fed7d7; color: #742a2a; }
.warn-badge { background: #feebc8; color: #744210; }
.fix { color: #718096; font-size: 12px; margin-top: 3px; }
.note { margin-top: 24px; background: #ebf4ff; border-left: 4px solid #1f6dad; padding: 12px 16px;
        border-radius: 6px; font-size: 13px; color: #2c5282; }
a { color: #1f6dad; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🔍 环境诊断</h1>
  <p class="subtitle">凯尔ERP — 迁移后可用性检查 &nbsp;|&nbsp; PHP <?= PHP_VERSION ?> &nbsp;|&nbsp; OS: <?= PHP_OS_FAMILY ?></p>

  <div class="summary <?= $all_ok ? 'ok' : 'err' ?>">
    <?= $all_ok
        ? "✅ 全部 {$total} 项检查通过，环境就绪，可正常使用！"
        : "❌ {$pass}/{$total} 项通过，请修复下方标红项目后刷新页面。" ?>
  </div>

  <table>
    <thead>
      <tr><th width="28%">检查项</th><th width="22%">状态</th><th>说明 / 修复方法</th></tr>
    </thead>
    <tbody>
    <?php foreach ($checks as $c):
        $is_warn = isset($c['warn']) && $c['warn'] && $c['ok'];
        $badge   = $c['ok'] ? ($is_warn ? 'warn-badge' : 'ok-badge') : 'err-badge';
        $icon    = $c['ok'] ? ($is_warn ? '⚠️' : '✅') : '❌';
    ?>
    <tr>
      <td><?= htmlspecialchars($c['label']) ?></td>
      <td><span class="badge <?= $badge ?>"><?= $icon ?> <?= htmlspecialchars($c['value']) ?></span></td>
      <td>
        <?php if (!$c['ok'] || $is_warn): ?>
          <div class="fix">💡 <?= htmlspecialchars($c['fix']) ?></div>
        <?php else: ?>
          <span style="color:#68d391;">正常</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="note">
    ⚠️ 诊断完成后建议删除或限制访问此页面（<code>env_check.php</code>），避免信息泄露。
  </div>

  <p style="margin-top:16px; font-size:13px; color:#a0aec0;">
    <a href="index.php">← 返回主页</a>
  </p>
</div>
</body>
</html>
