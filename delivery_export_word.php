<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/_export_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$cfg  = getUserSettings($conn, $_SESSION['user_id']);
$uid  = (int)$_SESSION['user_id'];
$did  = (int)($_GET['id'] ?? 0);
if (!$did) die('无效ID');

$stmt = $conn->prepare("SELECT d.* FROM deliveries d WHERE d.id=? AND d.user_id=?");
$stmt->bind_param('ii', $did, $uid); $stmt->execute();
$d = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$d) die('送货单不存在或无权访问');

$stmt2 = $conn->prepare("
    SELECT di.*, pc.name AS cat_name, p.name AS p_name, p.spec AS p_spec
    FROM delivery_items di
    LEFT JOIN products p ON di.product_id=p.id
    LEFT JOIN product_categories pc ON p.category_id=pc.id
    WHERE di.delivery_id=? ORDER BY di.id ASC
");
$stmt2->bind_param('i', $did); $stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $stmt2->close();
$conn->close();

$date_str = $d['delivery_date'] ?? date('Y-m-d');

export_as_word('送货单_' . $d['delivery_no']);
echo html_open_word();
echo doc_header($cfg['company_name'] ?? '', '送　货　单',
                '送货单号：' . $d['delivery_no'],
                '送货日期：' . $date_str,
                $d['delivery_time'] ? '送货时间：' . $d['delivery_time'] : '');

/* 客户信息 */
echo '<table><tr><td class="sec" colspan="4">收货信息</td></tr>
<tr>
  <td class="lbl">客户名称</td><td class="val">' . e($d['customer_name'] ?? '') . '</td>
  <td class="lbl">联系人</td>  <td class="val">' . e($d['contact_name'] ?? '') . '</td>
</tr>
<tr>
  <td class="lbl">送货地址</td><td class="val">' . e($d['delivery_address'] ?? '') . '</td>
  <td class="lbl">联系电话</td><td class="val">' . e($d['contact_phone'] ?? '') . '</td>
</tr>
<tr>
  <td class="lbl">送货人员</td><td class="val">' . e($d['delivery_person'] ?? '') . '</td>
  <td class="lbl">关联报价单</td><td class="val">' . e($d['quote_no'] ?? '') . '</td>
</tr></table>';

/* 明细 */
echo '<table><tr><td class="sec" colspan="8">送货明细</td></tr>
<tr>
  <th width="4%">序号</th>
  <th width="10%">产品类型</th>
  <th width="24%">产品名称/服务项目</th>
  <th width="28%">规格型号/配置参数</th>
  <th width="6%">单位</th>
  <th width="7%">数量</th>
  <th width="10%">单价(¥)</th>
  <th width="11%">金额(¥)</th>
</tr>';
$total = 0; $r = 0;
foreach ($items as $it) {
    $name  = $it['p_name']  ?: $it['product_name'] ?? '';
    $spec  = $it['p_spec']  ?: $it['product_spec']  ?? '';
    $qty   = floatval($it['quantity'] ?? 1);
    $price = floatval($it['unit_price'] ?? 0);
    $amt   = $qty * $price;
    $total += $amt;
    $bg    = (++$r % 2 === 0) ? ' class="alt"' : '';
    echo '<tr' . $bg . '>
      <td class="ta-c">' . $r . '</td>
      <td>' . e($it['cat_name'] ?? '') . '</td>
      <td>' . e($name) . '</td>
      <td>' . e($spec) . '</td>
      <td class="ta-c">' . e($it['unit'] ?? '台') . '</td>
      <td class="ta-c">' . number_format($qty, 0) . '</td>
      <td class="ta-r">¥' . number_format($price, 2) . '</td>
      <td class="ta-r">¥' . number_format($amt, 2) . '</td>
    </tr>';
}
echo '<tr class="tot"><td colspan="7" class="ta-r">合　计</td>
    <th class="ta-r">¥' . number_format($total, 2) . '</th></tr></table>';

/* 备注 */
if (!empty($d['notes'])) {
    echo '<table><tr><td class="sec">备注</td></tr>
    <tr><td style="padding:8pt;">' . nl2br(e($d['notes'])) . '</td></tr></table>';
}

/* 签字 */
echo '<table style="margin-top:16pt;"><tr>
  <td class="sign-cell" width="33%">收货人签字<br><br>___________________</td>
  <td class="sign-cell" width="34%">公司盖章</td>
  <td class="sign-cell" width="33%">送货人：' . e($d['delivery_person'] ?? '') . '<br><br>___________________</td>
</tr></table>';

echo doc_footer($cfg);
echo html_close();
