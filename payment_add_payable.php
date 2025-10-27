<?php
/**
 * =====================================================
 * 文件名：payment_add_payable.php
 * 功能：新增应付账款（带供应商搜索和快速新建）
 * 版本：v2.1 - 修复供应商显示问题
 * =====================================================
 */

session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// 获取所有供应商列表（移除 is_active 限制，显示所有供应商）
$suppliers = [];
$supplier_query = "SELECT id, supplier_code, company_name, contact_person, contact_phone, payment_terms 
                   FROM suppliers 
                   ORDER BY company_name";
$result = $conn->query($supplier_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// 获取应付账款中的供应商名称（用于补充）
$payable_suppliers = [];
$payable_query = "SELECT DISTINCT supplier_name 
                  FROM accounts_payable 
                  WHERE supplier_name IS NOT NULL AND supplier_name != ''
                  ORDER BY supplier_name";
$payable_result = $conn->query($payable_query);
if ($payable_result) {
    while ($row = $payable_result->fetch_assoc()) {
        // 检查是否已在suppliers表中
        $exists = false;
        foreach ($suppliers as $s) {
            if ($s['company_name'] === $row['supplier_name']) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $payable_suppliers[] = [
                'id' => 0,  // 临时ID
                'supplier_code' => '',
                'company_name' => $row['supplier_name'],
                'contact_person' => null,
                'contact_phone' => null,
                'payment_terms' => 0
            ];
        }
    }
}

// 合并两个列表
$all_suppliers = array_merge($suppliers, $payable_suppliers);

// 处理应付款表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payable') {
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    $supplier_name = $_POST['supplier_name'];
    $bill_no = $_POST['bill_no'];
    $bill_date = $_POST['bill_date'];
    $total_amount = $_POST['total_amount'];
    $category = !empty($_POST['category']) ? $_POST['category'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $notes = $_POST['notes'] ?? '';
    
    // 插入应付账款数据
    $sql = "INSERT INTO accounts_payable 
            (supplier_name, bill_no, bill_date, total_amount, outstanding_amount, category, due_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssddsss", $supplier_name, $bill_no, $bill_date, $total_amount, $total_amount, $category, $due_date, $notes);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = '应付款添加成功！';
        $_SESSION['message_type'] = 'success';
        header('Location: finance.php');
        exit;
    } else {
        $error = '添加失败：' . $conn->error;
    }
}

// 自动生成账单编号
$today = date('Ymd');
$bill_no_prefix = "AP-{$today}-";
$last_bill = $conn->query("SELECT bill_no FROM accounts_payable WHERE bill_no LIKE '{$bill_no_prefix}%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($last_bill) {
    $last_num = intval(substr($last_bill['bill_no'], -4));
    $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
} else {
    $new_num = '0001';
}
$default_bill_no = $bill_no_prefix . $new_num;

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增应付款 - 财务管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        /* 顶部导航 */
        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            text-decoration: none;
        }

        .btn-back {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        /* 主内容 */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            font-size: 14px;
            color: #718096;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        /* 调试信息 */
        .debug-info {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #2c5282;
        }

        .debug-info strong {
            color: #2a4365;
        }

        /* 表单卡片 */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .required {
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-hint {
            font-size: 13px;
            color: #718096;
            margin-top: 4px;
        }

        /* 供应商选择器 */
        .supplier-selector {
            position: relative;
        }

        .supplier-input-group {
            display: flex;
            gap: 8px;
        }

        .supplier-search-input {
            flex: 1;
        }

        .btn-add-supplier {
            padding: 10px 16px;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .btn-add-supplier:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .supplier-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 40px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 10;
            margin-top: 4px;
        }

        .supplier-dropdown.show {
            display: block;
        }

        .supplier-item {
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f7fafc;
        }

        .supplier-item:last-child {
            border-bottom: none;
        }

        .supplier-item:hover {
            background: #f7fafc;
        }

        .supplier-name {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .supplier-info {
            font-size: 12px;
            color: #718096;
        }

        .supplier-from-payable {
            background: #fef5e7;
        }

        .supplier-from-payable .supplier-name::after {
            content: " 📋";
            font-size: 12px;
        }

        .no-results {
            padding: 16px;
            text-align: center;
            color: #a0aec0;
            font-size: 14px;
        }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a202c;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: #f7fafc;
            border-radius: 6px;
            cursor: pointer;
            font-size: 20px;
            color: #718096;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #e2e8f0;
        }

        .modal-body {
            padding: 24px 32px;
        }

        .modal-footer {
            padding: 16px 32px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* 表单网格 */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .supplier-input-group {
                flex-direction: column;
            }
            
            .btn-add-supplier {
                width: 100%;
            }
        }

        /* 按钮 */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        /* 错误提示 */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar">
        <a href="finance.php" class="navbar-brand">
            <span>💳</span>
            <span>新增应付款</span>
        </a>
        <a href="finance.php" class="btn-back">← 返回财务管理</a>
    </nav>

    <!-- 主内容 -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">➕ 新增应付账款</h1>
            <div class="breadcrumb">
                <a href="index.php">首页</a>
                <span>/</span>
                <a href="finance.php">财务管理</a>
                <span>/</span>
                <span>新增应付款</span>
            </div>
        </div>

        <!-- 调试信息（开发时显示）-->
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-info">
            <strong>🔍 调试信息：</strong>
            <br>供应商表数据：<?php echo count($suppliers); ?> 条
            <br>应付账款供应商：<?php echo count($payable_suppliers); ?> 条
            <br>合计：<?php echo count($all_suppliers); ?> 条
            <?php if (count($all_suppliers) > 0): ?>
                <br>供应商列表：
                <?php foreach ($all_suppliers as $s): ?>
                    <br>- <?php echo htmlspecialchars($s['company_name']); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="form-card">
            <!-- 错误提示 -->
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <span>❌</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- 应付款表单 -->
            <form method="POST" action="" id="payableForm">
                <input type="hidden" name="action" value="add_payable">
                <input type="hidden" name="supplier_id" id="supplier_id">

                <!-- 供应商选择 -->
                <div class="form-group">
                    <label class="form-label">供应商 <span class="required">*</span></label>
                    <div class="supplier-selector">
                        <div class="supplier-input-group">
                            <input type="text" 
                                   name="supplier_name" 
                                   id="supplier_name" 
                                   class="form-input supplier-search-input" 
                                   placeholder="搜索或输入供应商名称..."
                                   autocomplete="off"
                                   required>
                            <button type="button" class="btn-add-supplier" onclick="openSupplierModal()">
                                ➕ 新建供应商
                            </button>
                        </div>
                        <div class="supplier-dropdown" id="supplierDropdown"></div>
                    </div>
                    <div class="form-hint">
                        输入名称搜索现有供应商，或点击按钮新建
                        <?php if (count($all_suppliers) > 0): ?>
                            （共 <?php echo count($all_suppliers); ?> 个供应商可选）
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <!-- 账单编号 -->
                    <div class="form-group">
                        <label class="form-label">账单编号 <span class="required">*</span></label>
                        <input type="text" name="bill_no" class="form-input" 
                               value="<?php echo $default_bill_no; ?>" required>
                        <div class="form-hint">自动生成，可修改</div>
                    </div>

                    <!-- 费用类别 -->
                    <div class="form-group">
                        <label class="form-label">费用类别</label>
                        <select name="category" class="form-select">
                            <option value="">请选择类别</option>
                            <option value="采购">采购</option>
                            <option value="租金">租金</option>
                            <option value="工资">工资</option>
                            <option value="运费">运费</option>
                            <option value="水电费">水电费</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- 账单日期 -->
                    <div class="form-group">
                        <label class="form-label">账单日期 <span class="required">*</span></label>
                        <input type="date" name="bill_date" class="form-input" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <!-- 到期日期 -->
                    <div class="form-group">
                        <label class="form-label">到期日期</label>
                        <input type="date" name="due_date" id="due_date" class="form-input">
                        <div class="form-hint">可选，用于逾期提醒</div>
                    </div>
                </div>

                <!-- 应付金额 -->
                <div class="form-group">
                    <label class="form-label">应付金额 <span class="required">*</span></label>
                    <input type="number" name="total_amount" class="form-input" 
                           step="0.01" min="0.01" placeholder="0.00" required>
                    <div class="form-hint">单位：元（人民币）</div>
                </div>

                <!-- 备注说明 -->
                <div class="form-group">
                    <label class="form-label">备注说明</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="可填写采购内容、付款条件等相关说明..."></textarea>
                </div>

                <!-- 表单按钮 -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" 
                            onclick="window.location.href='finance.php'">取消</button>
                    <button type="submit" class="btn btn-primary">💾 保存应付款</button>
                </div>
            </form>
        </div>
    </main>

    <!-- 新建供应商模态框 -->
    <div class="modal" id="supplierModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ 新建供应商</h2>
                <button type="button" class="modal-close" onclick="closeSupplierModal()">×</button>
            </div>
            <form id="supplierForm" onsubmit="saveSupplier(event)">
                <div class="modal-body">
                    <!-- 基本信息 -->
                    <div class="form-group">
                        <label class="form-label">公司名称 <span class="required">*</span></label>
                        <input type="text" name="company_name" id="new_company_name" 
                               class="form-input" placeholder="请输入公司全称" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">联系人</label>
                            <input type="text" name="contact_person" class="form-input" 
                                   placeholder="联系人姓名">
                        </div>
                        <div class="form-group">
                            <label class="form-label">联系电话</label>
                            <input type="tel" name="contact_phone" class="form-input" 
                                   placeholder="手机或固定电话">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">公司地址</label>
                        <input type="text" name="address" class="form-input" 
                               placeholder="详细地址">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">纳税人识别号</label>
                            <input type="text" name="tax_number" class="form-input" 
                                   placeholder="统一社会信用代码">
                        </div>
                        <div class="form-group">
                            <label class="form-label">账期天数</label>
                            <input type="number" name="payment_terms" class="form-input" 
                                   value="30" min="0" placeholder="30">
                            <div class="form-hint">付款账期，0为现结</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">备注说明</label>
                        <textarea name="description" class="form-textarea" 
                                  placeholder="供应商相关说明..." style="min-height: 80px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSupplierModal()">取消</button>
                    <button type="submit" class="btn btn-success" id="saveSupplierBtn">
                        💾 保存供应商
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 供应商数据（包含suppliers表和accounts_payable表的数据）
        const suppliers = <?php echo json_encode($all_suppliers); ?>;
        
        console.log('已加载供应商数据：', suppliers.length, '条');
        console.log('供应商列表：', suppliers);
        
        // 供应商搜索功能
        const supplierInput = document.getElementById('supplier_name');
        const supplierDropdown = document.getElementById('supplierDropdown');
        const supplierIdInput = document.getElementById('supplier_id');

        // 页面加载时显示所有供应商（可选）
        supplierInput.addEventListener('focus', function() {
            if (this.value.trim().length === 0 && suppliers.length > 0) {
                showAllSuppliers();
            }
        });

        supplierInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm.length === 0) {
                // 显示所有供应商
                showAllSuppliers();
                return;
            }

            // 过滤供应商
            const filtered = suppliers.filter(s => 
                s.company_name.toLowerCase().includes(searchTerm) ||
                (s.contact_person && s.contact_person.toLowerCase().includes(searchTerm)) ||
                (s.supplier_code && s.supplier_code.toLowerCase().includes(searchTerm))
            );

            // 显示结果
            if (filtered.length > 0) {
                supplierDropdown.innerHTML = filtered.map(s => {
                    const isFromPayable = s.id === 0;
                    const itemClass = isFromPayable ? 'supplier-item supplier-from-payable' : 'supplier-item';
                    return `
                        <div class="${itemClass}" onclick="selectSupplier(${s.id}, '${escapeHtml(s.company_name)}', ${s.payment_terms})">
                            <div class="supplier-name">${escapeHtml(s.company_name)}</div>
                            <div class="supplier-info">
                                ${s.contact_person ? escapeHtml(s.contact_person) : ''} 
                                ${s.contact_phone ? ' · ' + escapeHtml(s.contact_phone) : ''} 
                                ${s.payment_terms ? ' · 账期' + s.payment_terms + '天' : ''}
                                ${isFromPayable ? ' · 来自历史应付款' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
                supplierDropdown.classList.add('show');
            } else {
                supplierDropdown.innerHTML = '<div class="no-results">未找到匹配的供应商<br>可点击"新建供应商"按钮添加</div>';
                supplierDropdown.classList.add('show');
            }
        });

        // 显示所有供应商
        function showAllSuppliers() {
            if (suppliers.length === 0) {
                supplierDropdown.innerHTML = '<div class="no-results">暂无供应商<br>请点击"新建供应商"添加</div>';
                supplierDropdown.classList.add('show');
                return;
            }

            supplierDropdown.innerHTML = suppliers.map(s => {
                const isFromPayable = s.id === 0;
                const itemClass = isFromPayable ? 'supplier-item supplier-from-payable' : 'supplier-item';
                return `
                    <div class="${itemClass}" onclick="selectSupplier(${s.id}, '${escapeHtml(s.company_name)}', ${s.payment_terms})">
                        <div class="supplier-name">${escapeHtml(s.company_name)}</div>
                        <div class="supplier-info">
                            ${s.contact_person ? escapeHtml(s.contact_person) : ''} 
                            ${s.contact_phone ? ' · ' + escapeHtml(s.contact_phone) : ''} 
                            ${s.payment_terms ? ' · 账期' + s.payment_terms + '天' : ''}
                            ${isFromPayable ? ' · 来自历史应付款' : ''}
                        </div>
                    </div>
                `;
            }).join('');
            supplierDropdown.classList.add('show');
        }

        // HTML转义函数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // 选择供应商
        function selectSupplier(id, name, paymentTerms) {
            supplierInput.value = name;
            supplierIdInput.value = id;
            supplierDropdown.classList.remove('show');

            // 自动计算到期日期（如果有账期）
            if (paymentTerms > 0) {
                const billDate = document.querySelector('input[name="bill_date"]').value;
                if (billDate) {
                    const dueDate = new Date(billDate);
                    dueDate.setDate(dueDate.getDate() + paymentTerms);
                    document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
                }
            }
        }

        // 点击外部关闭下拉框
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.supplier-selector')) {
                supplierDropdown.classList.remove('show');
            }
        });

        // 打开新建供应商模态框
        function openSupplierModal() {
            document.getElementById('supplierModal').classList.add('show');
            document.getElementById('new_company_name').focus();
        }

        // 关闭模态框
        function closeSupplierModal() {
            document.getElementById('supplierModal').classList.remove('show');
            document.getElementById('supplierForm').reset();
        }

        // 保存新供应商（AJAX）
        function saveSupplier(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'add_supplier');
            
            const saveBtn = document.getElementById('saveSupplierBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '⏳ 保存中...';
            saveBtn.disabled = true;

            fetch('ajax_supplier.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 添加到供应商列表
                    suppliers.push(data.supplier);
                    
                    // 自动选择新建的供应商
                    supplierInput.value = data.supplier.company_name;
                    supplierIdInput.value = data.supplier.id;
                    
                    // 关闭模态框
                    closeSupplierModal();
                    
                    // 显示成功提示
                    alert('供应商添加成功！');
                } else {
                    alert('添加失败：' + (data.error || '未知错误'));
                }
            })
            .catch(error => {
                alert('网络错误：' + error.message);
                console.error('Error:', error);
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

        // 按ESC关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSupplierModal();
            }
        });
    </script>
</body>
</html>