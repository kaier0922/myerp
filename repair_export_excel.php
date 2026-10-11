<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/_export_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$cfg  = getUserSettings($conn, $_SESSION['user_id']);
$uid  = (int)$_SESSION['user_id'];
$rid  = (int)($_GET['id'] ?? 0);
if (!$rid) die('无效ID');

$stmt = $conn->prepare("
    SELECT ro.*, COALESCE(c.short_name, c.company_name) AS company_name AS cust_company
    FROM repair_orders ro
    LEFT JOIN customers c ON ro.customer_id=c.id
    WHERE ro.id=? AND ro.user_id=?
");
$stmt->bind_param('ii', $rid, $uid); $stmt->execute();
$ro = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$ro) die('维修单不存在或无权访问');

$stmt2 = $conn->prepare("SELECT * FROM repair_parts WHERE repair_id=? ORDER BY created_at");
$stmt2->bind_param('i', $rid); $stmt2->execute();
$parts = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $stmt2->close();
$conn->close();

$status_map = ['pending'=>'待处理','repairing'=>'维修中','testing'=>'测试中',
               'completed'=>'已完成','delivered'=>'已交付','cancelled'=>'已取消'];
$parts_total = array_sum(array_column($parts, 'subtotal'));

export_as_excel('维修单_' . $ro['order_no']);
echo html_open_excel('维修单');

echo '<table><tr>
  <td colspan="5" style="background:#1f6dad;color:#fff;font-size:13pt;font-weight:bold;
      text-align:center;padding:8pt;border:none;">'
  . e($cfg['company_name'] ?? '') . '　维修单</td>
</tr></table>';

/* 基本信息 */
echo '<table>
<tr><td class="lbl" width="15%">工单编号</td><td class="val">' . e($ro['order_no']) . '</td>
    <td class="lbl" width="15%">接收日期</td><td class="val">' . date('Y-m-d', strtotime($ro['receive_date'])) . '</td>
    <td class="lbl" width="15%">工单状态</td><td>' . e($status_map[$ro['status']] ?? '') . '</td></tr>
<tr><td class="lbl">客户姓名</td><td class="val">' . e($ro['customer_name'] ?? '') . '</td>
    <td class="lbl">联系电话</td><td class="val">' . e($ro['contact_phone'] ?? '') . '</td>
    <td class="lbl">所属公司</td><td>' . e($ro['cust_company'] ?? '') . '</td></tr>
<tr><td class="lbl">设备类型</td><td class="val">' . e($ro['device_type'] ?? '') . '</td>
    <td class="lbl">品牌型号</td><td class="val">' . e(trim($ro['device_brand'] . ' ' . $ro['device_model'])) . '</td>
    <td class="lbl">序列号</td><td>' . e($ro['device_sn'] ?? '') . '</td></tr>
<tr><td class="lbl">故障描述</td><td colspan="5">' . e($ro['fault_description'] ?? '') . '</td></tr>
<tr><td class="lbl">维修结果</td><td colspan="5">' . e($ro['repair_result'] ?? '') . '</td></tr>
</table>';

/* 费用 */
echo '<table><tr>
  <th>服务费</th><th>配件费</th><th>其他费用</th><th>应收总计</th><th>已付</th><th>待付</th>
</tr><tr>
  <td class="ta-r">¥' . number_format($ro['service_fee'], 2) . '</td>
  <td class="ta-r">¥' . number_format($ro['parts_fee'], 2) . '</td>
  <td class="ta-r">¥' . number_format($ro['other_fee'], 2) . '</td>
  <td class="ta-r" style="font-weight:bold;">¥' . number_format($ro['total_fee'], 2) . '</td>
  <td class="ta-r" style="color:#1f6dad;">¥' . number_format($ro['paid_amount'], 2) . '</td>
  <td class="ta-r" style="color:#c0392b;">¥' . number_format($ro['total_fee'] - $ro['paid_amount'], 2) . '</td>
</tr></table>';

/* 配件清单 */
if (!empty($parts)) {
    echo '<table><tr><td class="sec" colspan="5">配件清单</td></tr>
    <tr><th width="30%">配件名称</th><th width="30%">型号规格</th>
        <th width="10%">数量</th><th width="15%">单价</th><th width="15%">小计</th></tr>';
    foreach ($parts as $p) {
        echo '<tr><td>' . e($p['part_name']) . '</td><td>' . e($p['part_model'] ?? '') . '</td>
          <td class="ta-c">' . $p['quantity'] . '</td>
          <td class="ta-r">¥' . number_format($p['unit_price'], 2) . '</td>
          <td class="ta-r">¥' . number_format($p['subtotal'], 2) . '</td></tr>';
    }
    echo '<tr class="tot"><td colspan="4" class="ta-r">配件合计</td>
          <th class="ta-r">¥' . number_format($parts_total, 2) . '</th></tr></table>';
}

echo doc_footer($cfg);
echo html_close();
