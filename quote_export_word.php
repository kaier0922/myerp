<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/_export_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$cfg  = getUserSettings($conn, $_SESSION['user_id']);
$uid  = (int)$_SESSION['user_id'];
$qid  = (int)($_GET['id'] ?? 0);
if (!$qid) die('无效ID');

$stmt = $conn->prepare("
    SELECT q.*, c.company_name AS company_name, c.address AS customer_address
    FROM quotes q LEFT JOIN customers c ON q.customer_id=c.id
    WHERE q.id=? AND q.user_id=?
");
$stmt->bind_param('ii', $qid, $uid); $stmt->execute();
$q = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$q) die('报价单不存在或无权访问');

$stmt2 = $conn->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY seq ASC");
$stmt2->bind_param('i', $qid); $stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $stmt2->close();
$conn->close();

$date_str = date('Y-m-d', strtotime($q['quote_date']));
$valid    = intval($q['valid_days'] ?? $cfg['quote_valid_days'] ?? 3);

export_as_word('报价单_' . $q['quote_no']);
echo html_open_word();
echo doc_header($cfg['company_name'] ?? '', $cfg['quote_title'] ?? '报价单',
                '报价单号：' . $q['quote_no'],
                '日期：' . $date_str, '有效期：' . $valid . ' 天');

/* 客户信息 */
echo '<table><tr><td class="sec" colspan="4">客户信息</td></tr>
<tr>
  <td class="lbl">客户名称</td><td class="val">' . e($q['company_name']) . '</td>
  <td class="lbl">联系人</td>  <td class="val">' . e($q['contact_person']) . '</td>
</tr>
<tr>
  <td class="lbl">客户地址</td><td class="val">' . e($q['customer_address']) . '</td>
  <td class="lbl">电　　话</td><td class="val">' . e($q['contact_phone']) . '</td>
</tr></table>';

/* 明细表格 */
echo '<table><tr><td class="sec" colspan="8">报价明细</td></tr>
<tr>
  <th width="4%">序号</th>
  <th width="10%">配件类型</th>
  <th width="22%">产品名称</th>
  <th width="30%">品牌型号/配置参数</th>
  <th width="6%">单位</th>
  <th width="6%">数量</th>
  <th width="11%">单价(¥)</th>
  <th width="11%">金额(¥)</th>
</tr>';
$total = 0; $r = 0;
foreach ($items as $it) {
    $total += $it['subtotal'];
    $spec   = trim(($it['brand'] ? $it['brand'] . ' ' : '') . ($it['spec'] ?: $it['model']));
    $bg     = (++$r % 2 === 0) ? ' class="alt"' : '';
    echo '<tr' . $bg . '>
      <td class="ta-c">' . e($it['seq']) . '</td>
      <td>' . e($it['category'] ?? '') . '</td>
      <td>' . e($it['product_name']) . '</td>
      <td>' . e($spec) . '</td>
      <td class="ta-c">' . e($it['unit']) . '</td>
      <td class="ta-c">' . intval($it['quantity']) . '</td>
      <td class="ta-r">¥' . number_format($it['price'], 2) . '</td>
      <td class="ta-r">¥' . number_format($it['subtotal'], 2) . '</td>
    </tr>';
}
$disc  = floatval($q['discount'] ?? 0);
$final = floatval($q['final_amount']);
echo '
<tr><td colspan="6" style="border:none;"></td>
    <td class="ta-r" style="background:#f5faff;font-weight:bold;">项目小计</td>
    <td class="ta-r" style="background:#f5faff;">¥' . number_format($total, 2) . '</td></tr>
<tr><td colspan="6" style="border:none;"></td>
    <td class="ta-r" style="background:#f5faff;font-weight:bold;">折扣/优惠</td>
    <td class="ta-r" style="background:#f5faff;color:#c0392b;">- ¥' . number_format($disc, 2) . '</td></tr>
<tr class="tot"><td colspan="6" style="background:none;border:none;"></td>
    <th class="ta-r">最终总价</th>
    <th class="ta-r">¥' . number_format($final, 2) . '</th></tr>
</table>';

/* 备注 */
if (!empty($q['terms'])) {
    echo '<table><tr><td class="sec">备注</td></tr>
    <tr><td style="padding:8pt;">' . nl2br(e($q['terms'])) . '</td></tr></table>';
}

/* 签字确认 */
echo '<table style="margin-top:16pt;"><tr>
  <td class="sign-cell" width="33%">客户签字<br><br>___________________</td>
  <td class="sign-cell" width="34%">公司盖章</td>
  <td class="sign-cell" width="33%">经办人：' . e($cfg['contact_person'] ?? '') . '<br><br>___________________</td>
</tr></table>';

echo doc_footer($cfg);
echo html_close();
