<?php
/**
 * 公章自动导入工具
 * 将文件系统中的公章图片导入到数据库
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
require_once 'config.php';

// 检查登录
if (!isset($_SESSION['user_id'])) {
    die('请先登录');
}

$conn = getDBConnection();
if (!$conn) {
    die('数据库连接失败');
}

// 执行导入
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <title>公章导入</title>
<style>
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
            h1 { color: #1f6dad; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
    <div class='container'>
    <h1>🔄 公章导入结果</h1>";
    
    // 搜索公章文件
    $directories = ['uploads/seals/', 'uploads/', './'];
    $seal_files = [];
    
    foreach ($directories as $dir) {
        $full_dir = __DIR__ . '/' . $dir;
        if (is_dir($full_dir)) {
            $files = glob($full_dir . 'seal_*.png');
            foreach ($files as $file) {
                $seal_files[] = [
                    'file' => basename($file),
                    'path' => $dir . basename($file)
                ];
            }
        }
    }
    
    if (count($seal_files) == 0) {
        echo "<div class='error'>❌ 未找到任何公章文件（seal_*.png）</div>";
        echo "<p>请先将公章图片上传到 uploads/seals/ 目录</p>";
    } else {
        echo "<p>找到 " . count($seal_files) . " 个公章文件，开始导入...</p>";
        
        $imported = 0;
        $errors = 0;
        
        foreach ($seal_files as $index => $seal) {
            $seal_name = '企业公章 ' . ($index + 1);
            $seal_type = '企业公章';
            $file_path = $seal['path'];
            $is_default = ($index === 0) ? 1 : 0; // 第一个设为默认
            
            // 检查是否已存在
            $check_sql = "SELECT id FROM seals WHERE file_path = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("s", $file_path);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            
            if ($exists) {
                echo "<div class='success'>⊙ {$seal['file']} - 已存在，跳过</div>";
                continue;
            }
            
            // 插入数据（添加 uploaded_by 字段）
            $uploaded_by = $_SESSION['user_id']; // 当前登录用户
            $sql = "INSERT INTO seals (seal_name, seal_type, file_path, is_default, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $seal_name, $seal_type, $file_path, $is_default, $uploaded_by);
            
            if ($stmt->execute()) {
                echo "<div class='success'>✓ {$seal['file']} - 导入成功（ID: {$conn->insert_id}）</div>";
                $imported++;
            } else {
                echo "<div class='error'>✗ {$seal['file']} - 导入失败：{$conn->error}</div>";
                $errors++;
            }
        }
        
        echo "<hr>";
        echo "<h2>导入完成</h2>";
        echo "<p>成功导入：<strong>{$imported}</strong> 个</p>";
        if ($errors > 0) {
            echo "<p>失败：<strong>{$errors}</strong> 个</p>";
        }
        
        echo "<a href='seals.php' class='btn'>返回公章管理</a>";
    }
    
    echo "</div></body></html>";
    $conn->close();
    exit;
}

// 显示确认页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>公章导入确认</title>
    <style>
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #1f6dad; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .btn:hover { background: #2f8a5a; }
        .btn-cancel { background: #718096; margin-left: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 公章导入工具</h1>
    
    <div class="info">
        <h3>⚠️ 注意事项</h3>
        <ul>
            <li>此工具会扫描 <code>uploads/seals/</code> 目录中的 <code>seal_*.png</code> 文件</li>
            <li>自动导入到 <code>seals</code> 数据库表</li>
            <li>第一个公章会被设为默认公章</li>
            <li>已存在的公章会被跳过（不会重复导入）</li>
        </ul>
    </div>
    
    <form method="POST">
        <button type="submit" class="btn">开始导入</button>
        <a href="seals.php" class="btn btn-cancel">取消</a>
    </form>
</div>
</body>
</html>
<?php
$conn->close();
?>
