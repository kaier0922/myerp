<?php
/**
 * _export_helper.php — Word / Excel 导出公共工具
 * 勿直接访问，供各 *_export_word/excel.php 引用
 */

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

/* ── 人民币大写 ────────────────────────────────────────────── */
function toChinese($num) {
    $c1 = "零壹贰叁肆伍陆柒捌玖";
    $c2 = "分角元拾佰仟万拾佰仟亿";
    $num = round($num, 2) * 100;
    if ($num <= 0) return "零元整";
    $i = 0; $c = "";
    while ($num > 0) {
        $n = $num % 10;
        $p1 = mb_substr($c1, $n, 1, 'UTF-8');
        $p2 = mb_substr($c2, $i, 1, 'UTF-8');
        if ($n != 0 || in_array($p2, ['亿','万','元'])) $c = $p1 . $p2 . $c;
        else $c = $p1 . $c;
        $i++; $num = (int)($num / 10);
    }
    $c = preg_replace(['/零{2,}/', '/零([元万亿])/', '/零$/'], ['零', '$1', ''], $c);
    return $c . "整";
}

/* ── HTTP 响应头 ───────────────────────────────────────────── */
function export_as_word($filename) {
    header('Content-Type: application/vnd.ms-word; charset=utf-8');
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename) . '.doc');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
}

function export_as_excel($filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename) . '.xls');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
}

/* ── 公共 CSS（Word / Excel 均兼容）────────────────────────── */
function _export_css() {
    return '
<style>
@page { size: A4 portrait; margin: 1.5cm 1.8cm; }
body  { font-family: "宋体", SimSun, serif; font-size: 10.5pt;
        line-height: 1.5; color: #333; margin: 0; }
table { border-collapse: collapse; width: 100%; margin-bottom: 8pt; }
td, th { border: 1pt solid #c8d8e8; padding: 5pt 8pt;
          vertical-align: middle; font-size: 10pt; }
th     { background-color: #1f6dad; color: #fff; font-weight: bold;
          text-align: center; border-color: #1a5c9a; }
.lbl   { background-color: #e8f4fd; color: #1f6dad; font-weight: bold; width: 15%; }
.val   { width: 35%; }
.ta-r  { text-align: right; }
.ta-c  { text-align: center; }
.ta-l  { text-align: left; }
.alt   { background-color: #f5faff; }
.sec   { background-color: #e8f4fd; color: #1f6dad; font-weight: bold;
          font-size: 10pt; padding: 4pt 8pt; border-left: 3pt solid #1f6dad;
          border-top: none; border-right: none; border-bottom: 1pt solid #c8d8e8; }
.tot   { background-color: #1f6dad; color: #fff; font-weight: bold; }
.foot  { font-size: 9pt; color: #666; border-top: 1pt solid #ccc;
          padding-top: 6pt; margin-top: 6pt; }
.sign-cell { height: 44pt; vertical-align: bottom; padding-bottom: 6pt;
              text-align: center; font-size: 9.5pt; color: #555; }
</style>';
}

/* ── Word 文档 HTML 头 ─────────────────────────────────────── */
function html_open_word() {
    return '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="utf-8">
<!--[if gte mso 9]><xml>
<w:WordDocument>
  <w:View>Print</w:View><w:Zoom>90</w:Zoom>
  <w:DoNotOptimizeForBrowser/>
</w:WordDocument>
</xml><![endif]-->' . _export_css() . '</head><body>';
}

/* ── Excel 文档 HTML 头 ────────────────────────────────────── */
function html_open_excel($sheet = 'Sheet1') {
    $sn = e($sheet);
    return '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="utf-8">
<!--[if gte mso 9]><xml>
<x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>' . $sn . '</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->' . _export_css() . '</head><body>';
}

function html_close() { return '</body></html>'; }

/* ── 文档顶部蓝色标题栏（Word / Excel 通用）──────────────── */
function doc_header($company, $doc_type, $doc_no, $date_str, $extra = '') {
    return '
<table cellpadding="0" cellspacing="0" style="margin-bottom:10pt;">
<tr>
  <td style="background-color:#1f6dad;padding:10pt 14pt 10pt 16pt;border:none;width:60%;">
    <div style="font-size:9pt;color:rgba(255,255,255,0.75);margin-bottom:4pt;">'
        . e($company) . '</div>
    <div style="font-size:20pt;font-weight:bold;color:#fff;letter-spacing:6pt;">'
        . e($doc_type) . '</div>
  </td>
  <td style="background-color:#1f6dad;padding:10pt 16pt 10pt 8pt;text-align:right;border:none;vertical-align:middle;">
    <div style="font-size:10pt;color:#fff;font-weight:bold;">' . e($doc_no) . '</div>
    <div style="font-size:9pt;color:rgba(255,255,255,0.8);margin-top:4pt;">' . e($date_str) . '</div>'
        . ($extra ? '<div style="font-size:9pt;color:rgba(255,255,255,0.8);margin-top:2pt;">' . e($extra) . '</div>' : '') . '
  </td>
</tr>
</table>';
}

/* ── 公司页脚 ──────────────────────────────────────────────── */
function doc_footer($cfg) {
    $lines = [];
    $l1 = [];
    if ($cfg['contact_person']) $l1[] = '联系人：' . $cfg['contact_person'];
    if ($cfg['contact_phone'])  $l1[] = '电话：'   . $cfg['contact_phone'];
    if ($cfg['contact_email'])  $l1[] = '邮箱：'   . $cfg['contact_email'];
    if ($cfg['company_website'])$l1[] = '网址：'   . $cfg['company_website'];
    if ($l1) $lines[] = implode('　　', $l1);

    $l2 = [];
    if ($cfg['company_name']) $l2[] = $cfg['company_name'];
    if ($cfg['tax_id'])       $l2[] = '税号：' . $cfg['tax_id'];
    if ($cfg['bank_name'])    $l2[] = $cfg['bank_name'] . '：' . ($cfg['bank_account'] ?? '');
    if ($l2) $lines[] = implode('　　', $l2);

    if (!empty($cfg['quote_footer'])) $lines[] = $cfg['quote_footer'];

    if (!$lines) return '';
    $html = '<table style="margin-top:10pt;"><tr><td class="foot" style="border:none;">';
    $html .= implode('<br>', array_map('e', $lines));
    $html .= '</td></tr></table>';
    return $html;
}
