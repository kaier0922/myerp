<?php
require_once 'config.php';
$conn = getDBConnection();
$result = $conn->query("SELECT DATABASE() AS db_name;");
$row = $result->fetch_assoc();
echo "<h2>当前连接的数据库：" . $row['db_name'] . "</h2>";

$result = $conn->query("SHOW TABLES;");
echo "<h3>当前库的表：</h3><ul>";
while ($t = $result->fetch_row()) {
    echo "<li>{$t[0]}</li>";
}
echo "</ul>";
$conn->close();
?>
