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

export_as_excel('报价单_' . $q['quote_no']);
echo html_open_excel('报价单');

/* 文档说明行 */
echo '<table><tr>
  <td colspan="8" style="background:#1f6dad;color:#fff;font-size:13pt;font-weight:bold;
      text-align:center;padding:8pt;border:none;">'
  . e($cfg['company_name'] ?? '') . '　' . e($cfg['quote_title'] ?? '报价单') . '</td>
</tr><tr>
  <td colspan="4" style="border:none;font-size:10pt;">报价单号：<strong>' . e($q['quote_no']) . '</strong></td>
  <td colspan="4" class="ta-r" style="border:none;font-size:10pt;">日期：' . e($date_str) . '　有效期：' . $valid . ' 天</td>
</tr><tr>
  <td colspan="2" style="border:none;font-size:10pt;">客户：<strong>' . e($q['company_name']) . '</strong></td>
  <td colspan="2" style="border:none;font-size:10pt;">联系人：' . e($q['contact_person']) . '</td>
  <td colspan="2" style="border:none;font-size:10pt;">电话：' . e($q['contact_phone']) . '</td>
  <td colspan="2" style="border:none;font-size:10pt;"></td>
</tr></table>';

/* 明细 */
echo '<table>
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
      <td class="ta-r" style="mso-number-format:&quot;#,##0.00&quot;;">' . number_format($it['price'], 2) . '</td>
      <td class="ta-r" style="mso-number-format:&quot;#,##0.00&quot;;">' . number_format($it['subtotal'], 2) . '</td>
    </tr>';
}
$disc  = floatval($q['discount'] ?? 0);
$final = floatval($q['final_amount']);
echo '
<tr><td colspan="6" style="border:none;"></td>
    <td class="ta-r" style="background:#f5faff;font-weight:bold;">项目小计</td>
    <td class="ta-r" style="background:#f5faff;mso-number-format:&quot;#,##0.00&quot;;">' . number_format($total, 2) . '</td></tr>
<tr><td colspan="6" style="border:none;"></td>
    <td class="ta-r" style="background:#f5faff;font-weight:bold;">折扣/优惠</td>
    <td class="ta-r" style="background:#f5faff;color:#c0392b;mso-number-format:&quot;#,##0.00&quot;;">-' . number_format($disc, 2) . '</td></tr>
<tr class="tot"><td colspan="6" style="background:none;border:none;"></td>
    <th class="ta-r">最终总价</th>
    <th class="ta-r" style="mso-number-format:&quot;#,##0.00&quot;;">' . number_format($final, 2) . '</th></tr>
</table>';

if (!empty($q['terms'])) {
    echo '<table><tr><td class="sec">备注</td></tr>
    <tr><td>' . nl2br(e($q['terms'])) . '</td></tr></table>';
}
echo doc_footer($cfg);
echo html_close();
