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
    SELECT ro.*, COALESCE(c.short_name, c.company_name) AS company_name AS cust_company, c.contact_name AS cust_contact
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
$pay_map    = ['paid'=>'✓ 已结清','partial'=>'部分付款','unpaid'=>'未付款'];
$parts_total = array_sum(array_column($parts, 'subtotal'));
$receive_date = date('Y-m-d', strtotime($ro['receive_date']));

export_as_word('维修单_' . $ro['order_no']);
echo html_open_word();
echo doc_header($cfg['company_name'] ?? '', '维　修　单',
                '工单号：' . $ro['order_no'],
                '接收：' . $receive_date,
                $ro['actual_finish_date'] ? '完成：' . date('Y-m-d', strtotime($ro['actual_finish_date'])) : '');

/* 状态/类型 */
$s_label = $status_map[$ro['status']] ?? $ro['status'];
$type_lbl = $ro['repair_type'] === 'onsite' ? '上门服务' : '带回维修';
$pay_lbl  = $pay_map[$ro['payment_status']] ?? $ro['payment_status'];
echo '<table><tr>
  <td class="sec" colspan="4">工单状态：' . e($s_label) . '　　' . e($type_lbl)
    . '　　工程师：' . e($ro['technician'] ?? '未指定')
    . '　　' . e($pay_lbl) . '</td>
</tr></table>';

/* 客户 + 设备 */
echo '<table><tr>
  <td class="sec" colspan="2" width="50%">客户信息</td>
  <td class="sec" colspan="2" width="50%">设备信息</td>
</tr>
<tr>
  <td class="lbl" width="15%">客户姓名</td>
  <td class="val" width="35%">' . e($ro['customer_name'] ?? '') . '</td>
  <td class="lbl" width="15%">设备类型</td>
  <td class="val" width="35%">' . e($ro['device_type'] ?? '') . '</td>
</tr>
<tr>
  <td class="lbl">联系电话</td>
  <td class="val">' . e($ro['contact_phone'] ?? '') . '</td>
  <td class="lbl">品牌型号</td>
  <td class="val">' . e(trim($ro['device_brand'] . ' ' . $ro['device_model'])) . '</td>
</tr>
<tr>
  <td class="lbl">所属公司</td>
  <td class="val">' . e($ro['cust_company'] ?? '') . '</td>
  <td class="lbl">序列号</td>
  <td class="val" style="font-family:monospace;">' . e($ro['device_sn'] ?? '') . '</td>
</tr>
<tr>
  <td class="lbl">联系地址</td>
  <td class="val">' . e($ro['contact_address'] ?? '') . '</td>
  <td class="lbl">附带配件</td>
  <td class="val">' . e($ro['accessories'] ?? '') . '</td>
</tr></table>';

/* 故障描述 + 维修结果 */
echo '<table><tr>
  <td class="sec" width="50%">故障描述</td>
  <td class="sec" width="50%">维修结果</td>
</tr>
<tr>
  <td style="vertical-align:top;padding:8pt;min-height:40pt;">'
    . nl2br(e($ro['fault_description'] ?? '')) . '</td>
  <td style="vertical-align:top;padding:8pt;min-height:40pt;">'
    . nl2br(e($ro['repair_result'] ?? '—')) . '</td>
</tr></table>';

/* 配件清单 */
if (!empty($parts)) {
    echo '<table><tr><td class="sec" colspan="5">维修配件清单</td></tr>
    <tr>
      <th width="30%">配件名称</th>
      <th width="30%">型号规格</th>
      <th width="10%">数量</th>
      <th width="15%">单价(¥)</th>
      <th width="15%">小计(¥)</th>
    </tr>';
    foreach ($parts as $p) {
        echo '<tr>
          <td>' . e($p['part_name']) . '</td>
          <td>' . e($p['part_model'] ?? '') . '</td>
          <td class="ta-c">' . $p['quantity'] . '</td>
          <td class="ta-r">¥' . number_format($p['unit_price'], 2) . '</td>
          <td class="ta-r">¥' . number_format($p['subtotal'], 2) . '</td>
        </tr>';
    }
    echo '<tr class="tot"><td colspan="4" class="ta-r">配件合计</td>
        <th class="ta-r">¥' . number_format($parts_total, 2) . '</th></tr></table>';
}

/* 费用汇总 */
$owe = floatval($ro['total_fee']) - floatval($ro['paid_amount']);
echo '<table><tr><td class="sec" colspan="4">费用汇总</td></tr>
<tr>
  <td class="ta-c" style="background:#e8f4fd;font-weight:bold;color:#1f6dad;" width="25%">服务费</td>
  <td class="ta-c" style="background:#e8f4fd;font-weight:bold;color:#1f6dad;" width="25%">配件费</td>
  <td class="ta-c" style="background:#e8f4fd;font-weight:bold;color:#1f6dad;" width="25%">其他费用</td>
  <td class="ta-c" style="background:#e8f4fd;font-weight:bold;color:#1f6dad;" width="25%">应收总计</td>
</tr>
<tr>
  <td class="ta-c">¥' . number_format($ro['service_fee'], 2) . '</td>
  <td class="ta-c">¥' . number_format($ro['parts_fee'], 2) . '</td>
  <td class="ta-c">¥' . number_format($ro['other_fee'], 2) . '</td>
  <td class="ta-c" style="font-weight:bold;font-size:12pt;">¥' . number_format($ro['total_fee'], 2) . '</td>
</tr>
<tr>
  <td colspan="2" style="font-size:10pt;">
    已付：<strong style="color:#1f6dad;">¥' . number_format($ro['paid_amount'], 2) . '</strong>
  </td>
  <td colspan="2" style="font-size:10pt;">
    待付：<strong style="color:#c0392b;">¥' . number_format($owe, 2) . '</strong>
    　' . e($pay_lbl) . '
  </td>
</tr></table>';

/* 备注 */
if (!empty($ro['notes'])) {
    echo '<table><tr><td class="sec">备注</td></tr>
    <tr><td style="padding:8pt;">' . nl2br(e($ro['notes'])) . '</td></tr></table>';
}

/* 签字 */
echo '<table style="margin-top:16pt;"><tr>
  <td class="sign-cell" width="33%">客户签字<br><br>___________________</td>
  <td class="sign-cell" width="34%">公司盖章</td>
  <td class="sign-cell" width="33%">工程师：' . e($ro['technician'] ?? '') . '<br><br>___________________</td>
</tr></table>';

echo doc_footer($cfg);
echo html_close();
