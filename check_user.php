<?php
// check_user.php - 用于检查和修复用户数据
header('Content-Type: text/html; charset=utf-8');

// 数据库配置
$host = 'localhost';
$username = 'bjd';  // 修改为你的数据库用户名
$password = 'Sz28960660';  // 修改为你的数据库密码
$database = 'bjd';

$conn = new mysqli($host, $username, $password, $database);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

echo "<h2>检查用户数据</h2>";

// 1. 检查用户是否存在
$phone = '13316973303';
$sql = "SELECT * FROM users WHERE phone = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>1. 查询手机号: $phone</h3>";
if ($user = $result->fetch_assoc()) {
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    // 2. 测试密码验证
    $test_password = 'sz28960660';
    echo "<h3>2. 测试密码验证</h3>";
    echo "测试密码: $test_password<br>";
    echo "数据库中的密码哈希: " . $user['password'] . "<br>";
    
    if (password_verify($test_password, $user['password'])) {
        echo "<strong style='color:green;'>✓ 密码验证成功！</strong><br>";
    } else {
        echo "<strong style='color:red;'>✗ 密码验证失败！</strong><br>";
        echo "<br>需要重新生成密码哈希...<br>";
        
        // 生成新的密码哈希
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "新的密码哈希: $new_hash<br>";
        
        // 更新到数据库
        $update_sql = "UPDATE users SET password = ? WHERE phone = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $new_hash, $phone);
        
        if ($update_stmt->execute()) {
            echo "<strong style='color:green;'>✓ 密码已更新！现在可以使用 sz28960660 登录了</strong><br>";
        } else {
            echo "<strong style='color:red;'>✗ 更新失败: " . $conn->error . "</strong><br>";
        }
    }
} else {
    echo "<strong style='color:red;'>✗ 用户不存在！</strong><br>";
    echo "<h3>3. 创建测试用户</h3>";
    
    // 创建用户
    $password_hash = password_hash('sz28960660', PASSWORD_DEFAULT);
    $insert_sql = "INSERT INTO users (phone, nickname, role, status, password) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $nickname = '系统管理员';
    $role = 'admin';
    $status = 1;
    $insert_stmt->bind_param("sssis", $phone, $nickname, $role, $status, $password_hash);
    
    if ($insert_stmt->execute()) {
        echo "<strong style='color:green;'>✓ 用户创建成功！</strong><br>";
        echo "手机号: $phone<br>";
        echo "密码: sz28960660<br>";
        echo "角色: admin<br>";
    } else {
        echo "<strong style='color:red;'>✗ 创建失败: " . $conn->error . "</strong><br>";
    }
}

// 4. 显示所有用户
echo "<h3>4. 当前所有用户</h3>";
$all_users = $conn->query("SELECT id, phone, nickname, role, status, created_at FROM users");
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID</th><th>手机号</th><th>昵称</th><th>角色</th><th>状态</th><th>创建时间</th></tr>";
while ($row = $all_users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['phone'] . "</td>";
    echo "<td>" . $row['nickname'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . ($row['status'] ? '启用' : '禁用') . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();

echo "<br><br><a href='login.html'>返回登录页面</a>";
?>