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
           c.address AS customer_address, u.username AS creator_name
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

/* receipt_desc 逻辑 */
$receipt_desc = '';
if ($r['related_type'] === 'receivable' && $r['related_id']) {
    $ds = $conn->prepare("SELECT ar.notes, ar.bill_no, d.delivery_no
        FROM accounts_receivable ar
        LEFT JOIN deliveries d ON ar.related_id=d.id AND ar.related_type='delivery'
        WHERE ar.id=? AND ar.user_id=?");
    $ds->bind_param('ii', (int)$r['related_id'], $uid); $ds->execute();
    $ar = $ds->get_result()->fetch_assoc(); $ds->close();
    if ($ar) {
        if ($r['note'])             $receipt_desc = $r['note'];
        elseif ($ar['delivery_no']) $receipt_desc = '送货单 ' . $ar['delivery_no'] . ' 货款';
        elseif ($ar['notes'])       $receipt_desc = $ar['notes'];
        else                        $receipt_desc = '应收账款 ' . $ar['bill_no'];
    }
} elseif ($r['note']) {
    $receipt_desc = $r['note'];
}
$conn->close();

$date_str    = date('Y 年 m 月 d 日', strtotime($r['created_at']));
$payer       = $r['customer_name'] ?: $r['walkin_name'] ?: '散客';
$has_tax     = floatval($r['tax_amount']) > 0;

export_as_word('收据_' . $r['receipt_no']);
echo html_open_word();

/* 标题居中 */
echo '<table cellpadding="0" cellspacing="0" style="margin-bottom:6pt;">
<tr>
  <td style="border:none;text-align:center;padding:10pt 0 0;">
    <div style="font-size:9pt;color:#666;">' . e($cfg['company_name'] ?? '') . '</div>
    <div style="font-size:26pt;font-weight:bold;letter-spacing:14pt;color:#1f6dad;margin:6pt 0;">收　　据</div>
    <div style="font-size:10pt;color:#666;">NO：' . e($r['receipt_no']) . '　　日期：' . e($date_str) . '　　经办：' . e($r['creator_name'] ?? '') . '</div>
  </td>
</tr></table>';

/* 付款方信息 */
echo '<table><tr>
  <td class="lbl" width="15%">付款单位</td>
  <td width="42%">' . e($payer) . ($r['walkin_phone'] ? '　' . e($r['walkin_phone']) : '') . '</td>
  <td class="lbl" width="15%">收款方式</td>
  <td width="28%">' . e($r['payment_method'] ?: '现金') . '</td>
</tr>';
if ($r['customer_address']) {
    echo '<tr>
      <td class="lbl">客户地址</td>
      <td colspan="3">' . e($r['customer_address']) . '</td>
    </tr>';
}
if ($receipt_desc) {
    echo '<tr>
      <td class="lbl">收款说明</td>
      <td colspan="3" style="color:#92400e;font-weight:bold;">' . e($receipt_desc) . '</td>
    </tr>';
}
if ($r['note'] && $r['note'] !== $receipt_desc) {
    echo '<tr><td class="lbl">备　　注</td><td colspan="3">' . e($r['note']) . '</td></tr>';
}
echo '</table>';

/* 金额大框 */
echo '<table style="margin:8pt 0;"><tr>
  <td width="60%" style="background:#e8f4fd;border:1.5pt solid #1f6dad;padding:10pt 12pt;font-size:11pt;font-weight:bold;color:#333;">
    金额（大写）：' . toChinese(floatval($r['total_amount'])) . '
  </td>
  <td width="40%" style="background:#1f6dad;border:1.5pt solid #1f6dad;padding:10pt 12pt;
      font-size:18pt;font-weight:bold;color:#fff;text-align:center;">
    ￥' . number_format(floatval($r['total_amount']), 2) . '
  </td>
</tr></table>';

/* 明细表 */
if (!empty($items)) {
    echo '<table><tr>
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
          <td class="ta-r">¥' . number_format((float)$it['unit_price'], 2) . '</td>
          <td class="ta-r">¥' . number_format((float)$it['amount'], 2) . '</td>
        </tr>';
    }
    if ($has_tax) {
        echo '<tr><td colspan="5" class="ta-r" style="background:#f5faff;font-weight:bold;">小计</td>
              <td class="ta-r" style="background:#f5faff;">¥' . number_format((float)$r['subtotal'], 2) . '</td></tr>
              <tr><td colspan="5" class="ta-r" style="background:#f5faff;font-weight:bold;">税额</td>
              <td class="ta-r" style="background:#f5faff;">¥' . number_format((float)$r['tax_amount'], 2) . '</td></tr>';
    }
    echo '<tr class="tot"><td colspan="5" class="ta-r">合　计</td>
          <th class="ta-r">¥' . number_format((float)$r['total_amount'], 2) . '</th></tr>';
    if (floatval($r['paid_amount']) != floatval($r['total_amount'])) {
        echo '<tr><td colspan="5" class="ta-r" style="font-weight:bold;">实　收</td>
              <td class="ta-r" style="color:#1f6dad;font-weight:bold;">¥' . number_format((float)$r['paid_amount'], 2) . '</td></tr>';
    }
    echo '</table>';
}

/* 签字 */
echo '<table style="margin-top:16pt;"><tr>
  <td class="sign-cell" width="50%">收款人（签章）：___________________</td>
  <td class="sign-cell" width="50%">核对人：___________________</td>
</tr></table>';

echo doc_footer($cfg);
echo html_close();
