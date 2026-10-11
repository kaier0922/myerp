<?php
/**
 * 文件名：ledger_helper.php
 * 功能：总账自动过账 - 根据posting_rules把业务单据自动记成会计凭证
 * 失败不影响原业务流程，只记error_log
 */

function auto_post_voucher($conn, $user_id, $source_module, $category, $amount, $summary, $source_type, $source_id, $voucher_date, $tax_amount = 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM posting_rules WHERE user_id=? AND source_module=? AND match_category=? AND is_active=1 LIMIT 1");
        $stmt->bind_param('iss', $user_id, $source_module, $category);
        $stmt->execute();
        $rule = $stmt->get_result()->fetch_assoc();

        if (!$rule) {
            $star = '*';
            $stmt = $conn->prepare("SELECT * FROM posting_rules WHERE user_id=? AND source_module=? AND match_category=? AND is_active=1 LIMIT 1");
            $stmt->bind_param('iss', $user_id, $source_module, $star);
            $stmt->execute();
            $rule = $stmt->get_result()->fetch_assoc();
        }
        if (!$rule || $amount <= 0) return false;

        $period = date('Y-m', strtotime($voucher_date));
        $prefix = '记-' . str_replace('-', '', $period) . '-';
        $like = $prefix . '%';
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM vouchers WHERE user_id=? AND voucher_no LIKE ?");
        $stmt->bind_param('is', $user_id, $like);
        $stmt->execute();
        $cnt = $stmt->get_result()->fetch_assoc()['c'];
        $voucher_no = $prefix . str_pad($cnt + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO vouchers (user_id, voucher_no, voucher_date, period, summary, source_type, source_id, status, created_by) VALUES (?,?,?,?,?,?,?, 'posted', ?)");
        $stmt->bind_param('isssssii', $user_id, $voucher_no, $voucher_date, $period, $summary, $source_type, $source_id, $user_id);
        if (!$stmt->execute()) { error_log('ledger: voucher insert failed: ' . $stmt->error); return false; }
        $voucher_id = $conn->insert_id;

        $net_amount = $amount - $tax_amount;
        $entries = [];
        if ($tax_amount > 0 && !empty($rule['tax_subject_id'])) {
            $entries[] = [$rule['debit_subject_id'], 'debit', $amount];
            $entries[] = [$rule['credit_subject_id'], 'credit', $net_amount];
            $entries[] = [$rule['tax_subject_id'], 'credit', $tax_amount];
        } else {
            $entries[] = [$rule['debit_subject_id'], 'debit', $amount];
            $entries[] = [$rule['credit_subject_id'], 'credit', $amount];
        }

        $stmt = $conn->prepare("INSERT INTO voucher_entries (voucher_id, subject_id, direction, amount, summary, sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($entries as $i => $e) {
            list($subject_id, $direction, $amt) = $e;
            $stmt->bind_param('iisdsi', $voucher_id, $subject_id, $direction, $amt, $summary, $i);
            $stmt->execute();
        }
        return $voucher_id;
    } catch (Throwable $e) {
        error_log('ledger auto_post_voucher error: ' . $e->getMessage());
        return false;
    }
}
