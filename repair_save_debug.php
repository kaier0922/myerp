<?php
/**
 * repair_save_debug.php - 调试版本
 * 临时使用，找到问题后删除
 */

// 开启错误显示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== 开始调试 ===\n\n";

// 检查登录
if (!isset($_SESSION['user_id'])) {
    die("❌ 未登录\n");
}
echo "✅ 已登录，用户ID: " . $_SESSION['user_id'] . "\n\n";

// 检查参数
$action = $_POST['action'] ?? '';
$repair_id = intval($_POST['id'] ?? 0);

echo "动作: {$action}\n";
echo "维修单ID: {$repair_id}\n\n";

if ($action !== 'update') {
    die("❌ 只测试更新操作\n");
}

if ($repair_id <= 0) {
    die("❌ 无效的维修单ID\n");
}

echo "✅ 参数验证通过\n\n";

// 获取所有POST数据
echo "=== 接收到的数据 ===\n";
$repair_type = trim($_POST['repair_type'] ?? 'inshop');
$customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
$customer_name = trim($_POST['customer_name'] ?? '');
$contact_phone = trim($_POST['contact_phone'] ?? '');
$contact_address = trim($_POST['contact_address'] ?? '');
$device_type = trim($_POST['device_type'] ?? '');
$device_brand = trim($_POST['device_brand'] ?? '');
$device_model = trim($_POST['device_model'] ?? '');
$device_sn = trim($_POST['device_sn'] ?? '');
$appearance_check = trim($_POST['appearance_check'] ?? '');
$accessories = trim($_POST['accessories'] ?? '');
$fault_description = trim($_POST['fault_description'] ?? '');
$technician = trim($_POST['technician'] ?? '');
$expected_finish_date = !empty($_POST['expected_finish_date']) ? trim($_POST['expected_finish_date']) : null;
$service_fee = floatval($_POST['service_fee'] ?? 0);
$parts_fee = floatval($_POST['parts_fee'] ?? 0);
$other_fee = floatval($_POST['other_fee'] ?? 0);
$total_fee = $service_fee + $parts_fee + $other_fee;
$notes = trim($_POST['notes'] ?? '');

echo "维修类型: {$repair_type}\n";
echo "客户ID: " . var_export($customer_id, true) . "\n";
echo "客户姓名: {$customer_name}\n";
echo "联系电话: {$contact_phone}\n";
echo "预计完成: " . var_export($expected_finish_date, true) . "\n";
echo "服务费: {$service_fee}\n";
echo "配件费: {$parts_fee}\n";
echo "其他费: {$other_fee}\n";
echo "总费用: {$total_fee}\n\n";

// 连接数据库
echo "=== 连接数据库 ===\n";
try {
    $conn = getDBConnection();
    echo "✅ 数据库连接成功\n\n";
} catch (Exception $e) {
    die("❌ 数据库连接失败: " . $e->getMessage() . "\n");
}

// 验证维修单存在
echo "=== 验证维修单 ===\n";
$sql = "SELECT order_no FROM repair_orders WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("❌ 准备语句失败: " . $conn->error . "\n");
}

$stmt->bind_param('i', $repair_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ 维修单不存在\n");
}

$order_no = $result->fetch_assoc()['order_no'];
$stmt->close();
echo "✅ 找到维修单: {$order_no}\n\n";

// 准备更新语句
echo "=== 准备UPDATE语句 ===\n";
$sql = "UPDATE repair_orders SET
    repair_type=?, customer_id=?, customer_name=?, contact_phone=?,
    contact_address=?, device_type=?, device_brand=?, device_model=?,
    device_sn=?, fault_description=?, appearance_check=?, accessories=?,
    expected_finish_date=?, technician=?, service_fee=?, parts_fee=?,
    other_fee=?, total_fee=?, notes=?
WHERE id=?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("❌ 准备UPDATE语句失败: " . $conn->error . "\n");
}
echo "✅ UPDATE语句准备成功\n\n";

// 参数类型验证
echo "=== 参数绑定 ===\n";
$type_string = 'sisssssssssssddddsi';
echo "类型字符串: {$type_string}\n";
echo "类型字符串长度: " . strlen($type_string) . "\n";

$params = [
    $repair_type, $customer_id, $customer_name, $contact_phone,
    $contact_address, $device_type, $device_brand, $device_model,
    $device_sn, $fault_description, $appearance_check, $accessories,
    $expected_finish_date, $technician, $service_fee, $parts_fee,
    $other_fee, $total_fee, $notes, $repair_id
];
echo "参数数量: " . count($params) . "\n";

if (strlen($type_string) !== count($params)) {
    die("❌ 参数数量不匹配！\n");
}
echo "✅ 参数数量匹配\n\n";

// 显示每个参数的值和类型
echo "=== 参数详情 ===\n";
$type_chars = str_split($type_string);
for ($i = 0; $i < count($params); $i++) {
    $value = $params[$i];
    $type = $type_chars[$i];
    echo sprintf("参数%d: 类型=%s, 值=%s\n", $i+1, $type, var_export($value, true));
}
echo "\n";

// 执行绑定
echo "=== 执行绑定 ===\n";
$bind_result = $stmt->bind_param(
    'sisssssssssssddddsi',
    $repair_type, $customer_id, $customer_name, $contact_phone,
    $contact_address, $device_type, $device_brand, $device_model,
    $device_sn, $fault_description, $appearance_check, $accessories,
    $expected_finish_date, $technician, $service_fee, $parts_fee,
    $other_fee, $total_fee, $notes, $repair_id
);

if (!$bind_result) {
    die("❌ 绑定参数失败\n");
}
echo "✅ 参数绑定成功\n\n";

// 执行更新
echo "=== 执行UPDATE ===\n";
if (!$stmt->execute()) {
    die("❌ 执行失败: " . $stmt->error . "\n");
}

echo "✅ 更新成功！\n";
echo "受影响行数: " . $stmt->affected_rows . "\n\n";

$stmt->close();
$conn->close();

echo "=== 调试完成 ===\n";
echo "所有步骤都成功！\n";
?>