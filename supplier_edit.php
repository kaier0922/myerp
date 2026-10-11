<?php
/**
 * =====================================================
 * 文件名：supplier_edit.php
 * 功能：编辑供应商
 * 描述：修改供应商信息
 * 版本：1.0
 * 更新日期：2025-10-22
 * =====================================================
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'suppliers', 'update', '修改供应商');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// 获取供应商ID
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($supplier_id <= 0) {
    $_SESSION['error_message'] = '无效的供应商ID';
    header('Location: suppliers.php');
    exit;
}

// ==================== 处理POST请求（更新供应商）====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 获取供应商信息
        $company_name = trim($input['company_name'] ?? '');
        $short_name   = trim($input['short_name']   ?? '');
        $contact_person = trim($input['contact_person'] ?? '');
        $contact_phone = trim($input['contact_phone'] ?? '');
        $contact_email = trim($input['contact_email'] ?? '');
        $address = trim($input['address'] ?? '');
        $tax_number = trim($input['tax_number'] ?? '');
        $bank_name = trim($input['bank_name'] ?? '');
        $bank_account = trim($input['bank_account'] ?? '');
        $payment_terms = intval($input['payment_terms'] ?? 0);
        $credit_limit = floatval($input['credit_limit'] ?? 0);
        $description = trim($input['description'] ?? '');
        $is_active = intval($input['is_active'] ?? 1);
        
        // 验证必填项
        if (empty($company_name)) {
            throw new Exception('请输入公司名称');
        }
        
        if (empty($contact_person)) {
            throw new Exception('请输入联系人');
        }
        
        if (empty($contact_phone)) {
            throw new Exception('请输入联系电话');
        }
        
        // 更新供应商
        $stmt = $conn->prepare("
            UPDATE suppliers SET
                company_name = ?,
                short_name = ?,
                contact_person = ?,
                contact_phone = ?,
                contact_email = ?,
                address = ?,
                tax_number = ?,
                bank_name = ?,
                bank_account = ?,
                payment_terms = ?,
                credit_limit = ?,
                description = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ? AND user_id = " . intval($_SESSION['user_id']) . "
        ");

        $stmt->bind_param(
            "ssssssssidssii",
            $company_name, $short_name, $contact_person, $contact_phone, $contact_email, $address,
            $tax_number, $bank_name, $bank_account, $payment_terms, $credit_limit,
            $description, $is_active, $supplier_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('供应商更新失败：' . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => '供应商更新成功'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// ==================== 获取供应商信息 ====================
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = '供应商不存在';
    header('Location: suppliers.php');
    exit;
}

$supplier = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<?php $page_title = '编辑供应商 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

        .btn-back {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-group.full-wid

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .form-group label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-control {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-help {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: 0.4s;
            border-radius: 26px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #1f6dad;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .info-bar {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #92400e;
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading.active {
            display: flex;
        }

        .loading-spinner {
            background: white;
            padding: 32px;
            border-radius: 12px;
            text-align: center;
        }

        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #1f6dad;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">编辑供应商</h1>
            <div class="btn-group">
                <a href="suppliers.php" class="btn btn-back">← 返回列表</a>
                <button type="button" class="btn btn-primary" onclick="updateSupplier()">💾 保存修改</button>
            </div>
        </div>

        <div class="info-bar">
            📝 正在编辑供应商：<?php echo htmlspecialchars($supplier['company_name']); ?> 
            （编号：<?php echo htmlspecialchars($supplier['supplier_code']); ?>）
        </div>

        <!-- 基本信息 -->
        <div class="form-card">
            <h2 class="form-section-title">基本信息</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">公司全称</label>
                    <input type="text" id="company_name" class="form-control"
                           value="<?php echo htmlspecialchars($supplier['company_name']); ?>">
                </div>

                <div class="form-group">
                    <label>供应商简称
                        <button type="button" onclick="document.getElementById('short_name').value=document.getElementById('company_name').value"
                            style="margin-left:6px;font-size:11px;padding:2px 8px;background:#e2e8f0;border:none;border-radius:4px;cursor:pointer;color:#4a5568;">← 从全称复制</button>
                    </label>
                    <input type="text" id="short_name" class="form-control" placeholder="4-6字简称"
                           value="<?php echo htmlspecialchars($supplier['short_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="required">联系人</label>
                    <input type="text" id="contact_person" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['contact_person']); ?>">
                </div>

                <div class="form-group">
                    <label class="required">联系电话</label>
                    <input type="text" id="contact_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['contact_phone']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>联系邮箱</label>
                    <input type="email" id="contact_email" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['contact_email']); ?>">
                </div>

                <div class="form-group full-width">
                    <label>公司地址</label>
                    <input type="text" id="address" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['address']); ?>">
                </div>
            </div>
        </div>

        <!-- 财务信息 -->
        <div class="form-card">
            <h2 class="form-section-title">财务信息</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>税号</label>
                    <input type="text" id="tax_number" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['tax_number']); ?>">
                    <span class="form-help">用于开具发票</span>
                </div>

                <div class="form-group">
                    <label>开户行</label>
                    <input type="text" id="bank_name" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['bank_name']); ?>">
                </div>

                <div class="form-group">
                    <label>银行账号</label>
                    <input type="text" id="bank_account" class="form-control" 
                           value="<?php echo htmlspecialchars($supplier['bank_account']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>账期（天）</label>
                    <input type="number" id="payment_terms" class="form-control" 
                           value="<?php echo $supplier['payment_terms']; ?>" min="0">
                    <span class="form-help">约定的付款天数，0表示现结</span>
                </div>

                <div class="form-group">
                    <label>信用额度（元）</label>
                    <input type="number" id="credit_limit" class="form-control" 
                           value="<?php echo $supplier['credit_limit']; ?>" min="0" step="any">
                    <span class="form-help">0表示不限额</span>
                </div>
            </div>
        </div>

        <!-- 其他信息 -->
        <div class="form-card">
            <h2 class="form-section-title">其他信息</h2>
            
            <div class="form-row">
                <div class="form-group full-width">
                    <label>备注说明</label>
                    <textarea id="description" class="form-control"><?php echo htmlspecialchars($supplier['description']); ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>供应商状态</label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label class="switch">
                            <input type="checkbox" id="is_active" 
                                   <?php echo ($supplier['is_active'] == 1) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span id="status_text"><?php echo ($supplier['is_active'] == 1) ? '合作中' : '已停用'; ?></span>
                    </div>
                    <span class="form-help">停用后不会在采购单中显示</span>
                </div>
            </div>
        </div>
    </main>

    <div id="loading" class="loading">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div>正在保存...</div>
        </div>
    </div>

    <script>
        document.getElementById('is_active').addEventListener('change', function() {
            document.getElementById('status_text').textContent = this.checked ? '合作中' : '已停用';
        });

        function collectFormData() {
            const companyName = document.getElementById('company_name').value.trim();
            const shortName   = document.getElementById('short_name').value.trim();
            const contactPerson = document.getElementById('contact_person').value.trim();
            const contactPhone = document.getElementById('contact_phone').value.trim();
            const contactEmail = document.getElementById('contact_email').value.trim();
            const address = document.getElementById('address').value.trim();
            const taxNumber = document.getElementById('tax_number').value.trim();
            const bankName = document.getElementById('bank_name').value.trim();
            const bankAccount = document.getElementById('bank_account').value.trim();
            const paymentTerms = parseInt(document.getElementById('payment_terms').value) || 0;
            const creditLimit = parseFloat(document.getElementById('credit_limit').value) || 0;
            const description = document.getElementById('description').value.trim();
            const isActive = document.getElementById('is_active').checked ? 1 : 0;
            
            if (!companyName) {
                alert('请输入公司名称');
                return null;
            }
            
            if (!contactPerson) {
                alert('请输入联系人');
                return null;
            }
            
            if (!contactPhone) {
                alert('请输入联系电话');
                return null;
            }
            
            return {
                company_name: companyName,
                short_name: shortName,
                contact_person: contactPerson,
                contact_phone: contactPhone,
                contact_email: contactEmail,
                address: address,
                tax_number: taxNumber,
                bank_name: bankName,
                bank_account: bankAccount,
                payment_terms: paymentTerms,
                credit_limit: creditLimit,
                description: description,
                is_active: isActive
            };
        }

        async function updateSupplier() {
            const formData = collectFormData();
            
            if (!formData) {
                return;
            }
            
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('supplier_edit.php?id=<?php echo $supplier_id; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                document.getElementById('loading').classList.remove('active');
                
                if (result.success) {
                    alert('✓ 供应商更新成功');
                    window.location.href = 'suppliers.php';
                } else {
                    alert('✗ 更新失败：' + result.message);
                }
            } catch (error) {
                document.getElementById('loading').classList.remove('active');
                console.error('更新错误:', error);
                alert('✗ 更新出错，请重试');
            }
        }
    </script>
</body>
</html>
