<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/_export_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$cfg  = getUserSettings($conn, $_SESSION['user_id']);
$uid  = (int)$_SESSION['user_id'];
$id   = (int)($_GET['id'] ?? 0);
if (!$id) die('无效ID');

$stmt = $conn->prepare("
    SELECT r.*, COALESCE(COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name) AS customer_name,
           u.username AS creator_name
    FROM receipts r
    LEFT JOIN customers c ON r.customer_id=c.id
    LEFT JOIN users u ON r.created_by=u.id
    WHERE r.id=? AND r.user_id=?
");
$stmt->bind_param('ii', $id, $uid); $stmt->execute();
$r = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$r) die('收据不存在');

$stmt2 = $conn->prepare("SELECT * FROM receipt_items WHERE receipt_id=? ORDER BY id ASC");
$stmt2->bind_param('i', $id); $stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $stmt2->close();
$conn->close();

$payer   = $r['customer_name'] ?: $r['walkin_name'] ?: '散客';
$has_tax = floatval($r['tax_amount']) > 0;

export_as_excel('收据_' . $r['receipt_no']);
echo html_open_excel('收据');

echo '<table><tr>
  <td colspan="6" style="background:#1f6dad;color:#fff;font-size:14pt;font-weight:bold;
      text-align:center;padding:8pt;letter-spacing:8pt;border:none;">收　据</td>
</tr><tr>
  <td colspan="3" style="border:none;font-size:10pt;">收据号：<strong>' . e($r['receipt_no']) . '</strong></td>
  <td colspan="3" class="ta-r" style="border:none;font-size:10pt;">
    日期：' . date('Y-m-d', strtotime($r['created_at'])) . '　经办：' . e($r['creator_name'] ?? '') . '
  </td>
</tr><tr>
  <td colspan="2" style="border:none;font-size:10pt;">付款方：<strong>' . e($payer) . '</strong></td>
  <td colspan="2" style="border:none;font-size:10pt;">收款方式：' . e($r['payment_method'] ?: '现金') . '</td>
  <td colspan="2" class="ta-r" style="border:none;font-size:12pt;font-weight:bold;color:#1f6dad;">
    合计：¥' . number_format(floatval($r['total_amount']), 2) . '
  </td>
</tr></table>';

if (!empty($items)) {
    echo '<table>
    <tr>
      <th width="30%">项目名称</th>
      <th width="22%">规格</th>
      <th width="8%">数量</th>
      <th width="8%">单位</th>
      <th width="16%">单价(¥)</th>
      <th width="16%">金额(¥)</th>
    </tr>';
    foreach ($items as $it) {
        echo '<tr>
          <td>' . e($it['item_name']) . '</td>
          <td>' . e($it['spec'] ?? '') . '</td>
          <td class="ta-c">' . rtrim(rtrim(number_format((float)$it['qty'], 2, '.', ''), '0'), '.') . '</td>
          <td class="ta-c">' . e($it['unit'] ?? '') . '</td>
          <td class="ta-r" style="mso-number-format:&quot;#,##0.00&quot;;">' . number_format((float)$it['unit_price'], 2) . '</td>
          <td class="ta-r" style="mso-number-format:&quot;#,##0.00&quot;;">' . number_format((float)$it['amount'], 2) . '</td>
        </tr>';
    }
    if ($has_tax) {
        echo '<tr><td colspan="5" class="ta-r" style="font-weight:bold;">小计</td>
              <td class="ta-r">¥' . number_format((float)$r['subtotal'], 2) . '</td></tr>
              <tr><td colspan="5" class="ta-r" style="font-weight:bold;">税额</td>
              <td class="ta-r">¥' . number_format((float)$r['tax_amount'], 2) . '</td></tr>';
    }
    echo '<tr class="tot"><td colspan="5" class="ta-r">合　计</td>
          <th class="ta-r" style="mso-number-format:&quot;#,##0.00&quot;;">' . number_format((float)$r['total_amount'], 2) . '</th></tr>';
    if (floatval($r['paid_amount']) != floatval($r['total_amount'])) {
        echo '<tr><td colspan="5" class="ta-r" style="font-weight:bold;">实　收</td>
              <td class="ta-r" style="color:#1f6dad;font-weight:bold;">¥' . number_format((float)$r['paid_amount'], 2) . '</td></tr>';
    }
    echo '</table>';
}

echo doc_footer($cfg);
echo html_close();
