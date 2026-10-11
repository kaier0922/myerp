<?php
/**
 * settings_helper.php
 * 读取当前登录用户的系统设置，返回关联数组
 * 所有单据页面 include 此文件后调用 getUserSettings()
 */

function getUserSettings($conn, $user_id) {
    $settings = [];
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // 默认值兜底（新用户没有配置时不报错）
    $defaults = [
        'company_name'    => '（未设置公司名称）',
        'company_short'   => '电脑装机工程智能报价系统',
        'contact_person'  => '',
        'contact_phone'   => '',
        'contact_email'   => '',
        'company_website' => '',
        'company_address' => '',
        'tax_id'          => '',
        'bank_name'       => '',
        'bank_account'    => '',
        'quote_valid_days'=> '3',
        'quote_title'     => '报　价　单',
        'quote_terms'     => '此报价单所列内容均为初步方案，具体配置与价格以实际沟通为准。',
        'quote_footer'    => '',
        'watermark_text'  => '',
        'logo_path'       => '',
    ];
    foreach ($defaults as $k => $v) {
        if (!isset($settings[$k]) || $settings[$k] === '') {
            $settings[$k] = $v;
        }
    }
    return $settings;
}
