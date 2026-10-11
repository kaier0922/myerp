<?php
/**
 * =====================================================
 * 文件名：payment_add_payable.php
 * 功能：新增应付账款（带供应商搜索和快速新建）
 * 版本：v2.1 - 修复供应商显示问题
 * =====================================================
 */

session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'finance', 'pay', '新建应付款');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// 获取所有供应商列表（移除 is_active 限制，显示所有供应商）
$suppliers = [];
$supplier_query = "SELECT id, supplier_code, company_name, contact_person, contact_phone, payment_terms 
                   FROM suppliers WHERE " . tenantWhere() . " 
                   ORDER BY company_name";
$result = $conn->query($supplier_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// 获取应付账款中的供应商名称（用于补充）
$all_suppliers = $suppliers;

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
            (user_id, supplier_name, bill_no, bill_date, total_amount, outstanding_amount, category, due_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssddsss", $_SESSION['user_id'], $supplier_name, $bill_no, $bill_date, $total_amount, $total_amount, $category, $due_date, $notes);
    
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
<?php $page_title = '新增应付账款 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
/* 主内容区：居中布局 */
.main-content{display:flex;flex-direction:column;align-items:center;}
.page-header-row{width:100%;max-width:760px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
.form-card{width:100%;max-width:760px;}
.btn-back{padding:8px 16px;background:white;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;transition:all .2s;}
.btn-back:hover{background:#edf2f7;}
.required{color:#e53e3e;}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.form-textarea{min-height:100px;resize:vertical;}
.form-hint{font-size:12px;color:#a0aec0;margin-top:4px;}
.btn-success{background:linear-gradient(135deg,#1f6dad,#135286);color:white;}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
.alert-error{background:#fee2e2;color:#c53030;border:1px solid #fca5a5;}

/* 供应商下拉 */
.supplier-selector{position:relative;}
.supplier-input-group{display:flex;gap:8px;}
.supplier-search-input{flex:1;}
.btn-add-supplier{padding:9px 16px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;white-space:nowrap;transition:all .2s;}
.btn-add-supplier:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
.supplier-dropdown{position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.1);max-height:280px;overflow-y:auto;display:none;z-index:50;margin-top:4px;}
.supplier-dropdown.show{display:block;}
.supplier-item{padding:10px 16px;cursor:pointer;transition:background .15s;border-bottom:1px solid #f7fafc;}
.supplier-item:last-child{border-bottom:none;}
.supplier-item:hover{background:#f7fafc;}
.supplier-name{font-weight:500;color:#2d3748;font-size:14px;}
.supplier-info{font-size:12px;color:#a0aec0;margin-top:2px;}
.supplier-from-payable{background:#fffbeb;}
.supplier-from-payable .supplier-name::after{content:" 📋";font-size:11px;}
.no-results{padding:16px;text-align:center;color:#a0aec0;font-size:14px;}

/* 模态框 */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.modal-content{background:white;border-radius:14px;width:90%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25);}
.modal-header{padding:20px 28px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#1f6dad,#135286);border-radius:14px 14px 0 0;}
.modal-title{font-size:16px;font-weight:600;color:white;}
.modal-close{width:30px;height:30px;border:none;background:rgba(255,255,255,.2);border-radius:6px;cursor:pointer;font-size:18px;color:white;transition:background .2s;}
.modal-close:hover{background:rgba(255,255,255,.35);}
.modal-body{padding:24px 28px;}
.modal-footer{padding:16px 28px;border-top:1px solid #e2e8f0;display:flex;gap:10px;justify-content:flex-end;}
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-row">
            <h1 class="page-title">📤 新增应付账款</h1>
            <a href="finance.php" class="btn-back">← 返回财务管理</a>
        </div>
    </div>

    <div class="form-card">
        <?php if (isset($error)): ?>
        <div class="alert alert-error"><span>❌</span><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <form method="POST" action="" id="payableForm">
            <input type="hidden" name="action" value="add_payable">
            <input type="hidden" name="supplier_id" id="supplier_id">

            <!-- 供应商 -->
            <div class="form-group">
                <label class="form-label">供应商 <span class="required">*</span></label>
                <div class="supplier-selector">
                    <div class="supplier-input-group">
                        <input type="text" name="supplier_name" id="supplier_name"
                               class="form-input supplier-search-input"
                               placeholder="搜索或输入供应商名称..." autocomplete="off" required>
                        <button type="button" class="btn-add-supplier" onclick="openSupplierModal()">➕ 新建供应商</button>
                    </div>
                    <div class="supplier-dropdown" id="supplierDropdown"></div>
                </div>
                <div class="form-hint">输入名称搜索现有供应商，或点击按钮新建（共 <?php echo count($all_suppliers); ?> 个供应商）</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">账单编号 <span class="required">*</span></label>
                    <input type="text" name="bill_no" class="form-input" value="<?php echo $default_bill_no; ?>" required>
                    <div class="form-hint">自动生成，可修改</div>
                </div>
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
                <div class="form-group">
                    <label class="form-label">账单日期 <span class="required">*</span></label>
                    <input type="date" name="bill_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">到期日期</label>
                    <input type="date" name="due_date" id="due_date" class="form-input">
                    <div class="form-hint">可选，用于逾期提醒</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">应付金额（元）<span class="required">*</span></label>
                <input type="number" name="total_amount" class="form-input" step="any" min="0.01" placeholder="0.00" required>
            </div>

            <div class="form-group">
                <label class="form-label">备注说明</label>
                <textarea name="notes" class="form-textarea" placeholder="可填写采购内容、付款条件等相关说明..."></textarea>
            </div>

            <div class="form-actions">
                <a href="finance.php" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">💾 保存应付款</button>
            </div>
        </form>
    </div>
</div>

<!-- 新建供应商模态框 -->
<div class="modal" id="supplierModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title">➕ 新建供应商</span>
            <button type="button" class="modal-close" onclick="closeSupplierModal()">×</button>
        </div>
        <form id="supplierForm" onsubmit="saveSupplier(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">公司名称 <span class="required">*</span></label>
                    <input type="text" name="company_name" id="new_company_name" class="form-input" placeholder="请输入公司全称" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">联系人</label>
                        <input type="text" name="contact_person" class="form-input" placeholder="联系人姓名">
                    </div>
                    <div class="form-group">
                        <label class="form-label">联系电话</label>
                        <input type="tel" name="contact_phone" class="form-input" placeholder="手机或固定电话">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">公司地址</label>
                    <input type="text" name="address" class="form-input" placeholder="详细地址">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">纳税人识别号</label>
                        <input type="text" name="tax_number" class="form-input" placeholder="统一社会信用代码">
                    </div>
                    <div class="form-group">
                        <label class="form-label">账期天数</label>
                        <input type="number" name="payment_terms" class="form-input" value="30" min="0">
                        <div class="form-hint">付款账期，0为现结</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">备注说明</label>
                    <textarea name="description" class="form-textarea" style="min-height:80px;" placeholder="供应商相关说明..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSupplierModal()">取消</button>
                <button type="submit" class="btn btn-success" id="saveSupplierBtn">💾 保存供应商</button>
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
                    sapToast('供应商添加成功！', 'success');
                } else {
                    sapToast('添加失败：' + (data.error || '未知错误', 'error'));
                }
            })
            .catch(error => {
                sapToast('网络错误：' + error.message, 'error');
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

<!-- SAP Toast + Confirm -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:260px;max-width:400px;padding:13px 16px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:16px;flex-shrink:0;"></span>
  <span id="sapToastMsg"  style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<div id="sapConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9000;"></div>
<div id="sapConfirmBox" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:400px;max-width:92vw;background:white;border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,0.18);z-index:9001;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:15px 20px;display:flex;align-items:center;gap:10px;">
    <span id="sapConfirmIcon"  style="font-size:18px;">⚠️</span>
    <span id="sapConfirmTitle" style="font-size:15px;font-weight:600;color:white;">确认操作</span>
  </div>
  <div style="padding:22px 20px;">
    <p id="sapConfirmMsg" style="font-size:14px;color:#2d3748;line-height:1.7;margin:0;"></p>
  </div>
  <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:10px;">
    <button id="sapConfirmCancel"
      style="padding:8px 20px;border:1px solid #e2e8f0;border-radius:7px;background:#f7fafc;
             color:#4a5568;font-size:14px;cursor:pointer;"
      onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='#f7fafc'">取消</button>
    <button id="sapConfirmOk"
      style="padding:8px 20px;border:none;border-radius:7px;
             background:linear-gradient(135deg,#1f6dad,#135286);
             color:white;font-size:14px;cursor:pointer;"
      onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">确认</button>
  </div>
</div>
<script>
function sapToast(msg,type,dur){
    type=type||'info';dur=dur||3000;
    var C={success:'#16a34a',error:'#dc2626',warning:'#d97706',info:'#1f6dad'};
    var I={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
    var el=document.getElementById('sapToast');
    if(!el)return;
    document.getElementById('sapToastIcon').textContent=I[type]||'ℹ️';
    document.getElementById('sapToastMsg').textContent=msg;
    el.style.background=C[type]||'#1f6dad';
    el.style.display='flex';el.style.opacity='1';
    clearTimeout(el._t);
    el._t=setTimeout(function(){el.style.opacity='0';
        setTimeout(function(){el.style.display='none';},300);},dur);
}
function sapConfirm(msg,onOk,opts){
    opts=opts||{};
    document.getElementById('sapConfirmMsg').innerHTML=msg.replace(/\n/g,'<br>');
    document.getElementById('sapConfirmTitle').textContent=opts.title||'确认操作';
    document.getElementById('sapConfirmIcon').textContent=opts.icon||'⚠️';
    document.getElementById('sapConfirmOk').textContent=opts.okText||'确认';
    document.getElementById('sapConfirmOk').style.background=
        opts.danger?'linear-gradient(135deg,#ef4444,#dc2626)':'linear-gradient(135deg,#1f6dad,#135286)';
    document.getElementById('sapConfirmOverlay').style.display='block';
    document.getElementById('sapConfirmBox').style.display='block';
    var ok=document.getElementById('sapConfirmOk');
    var cancel=document.getElementById('sapConfirmCancel');
    var close=function(){
        document.getElementById('sapConfirmOverlay').style.display='none';
        document.getElementById('sapConfirmBox').style.display='none';
        ok.onclick=null;cancel.onclick=null;};
    ok.onclick=function(){close();onOk();};
    cancel.onclick=function(){close();};
}
</script>

</body>
</html>