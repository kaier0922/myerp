<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
?>
<?php $page_title = '添加客户 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

.header-actions { display:flex; gap:12px; }

.section-title { font-size:18px; font-weight:600; color:#1a202c; margin-bottom:20px; padding-bottom:12px; border-bottom:2px solid #e2e8f0; }

.form-label.required::after { content:'*'; color:#ef4444; margin-left:4px; }
.form-input, .form-textarea {
    width:100%; padding:10px 14px; border:1px solid #e2e8f0;
    border-radius:8px; font-size:14px; transition:all .2s; font-family:inherit;
}
.form-input:focus, .form-textarea:focus { outline:none; border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
.form-textarea { resize:vertical; min-height:80px; }
.form-hint { font-size:12px; color:#718096; margin-top:4px; }

@media (max-width:768px) { }

.form-card { max-width:860px; margin-left:auto; margin-right:auto; }
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">➕ 添加客户</h1>
        <div style="display:flex;gap:10px;">
            <a href="customers.php" class="btn btn-secondary">← 返回列表</a>
            <button class="btn btn-primary" onclick="saveCustomer()">💾 保存客户</button>
        </div>
    </div>

    <form id="customerForm">
        <input type="hidden" name="action" value="add">

        <div class="form-card">
            <h2 class="section-title">基本信息</h2>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">公司名称</label>
                    <input type="text" name="company_name" class="form-input" placeholder="请输入公司全称" required>
                </div>
                <div class="form-group">
                    <label class="form-label">客户简称
                        <button type="button" onclick="document.querySelector('[name=short_name]').value=document.querySelector('[name=company_name]').value"
                            style="margin-left:8px;font-size:11px;padding:2px 8px;background:#e2e8f0;border:none;border-radius:4px;cursor:pointer;color:#4a5568;">← 从公司名称复制</button>
                    </label>
                    <input type="text" name="short_name" class="form-input" placeholder="4-6字简称，留空则显示全称">
                    <div class="form-hint">系统内各处显示优先使用简称</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">联系人</label>
                    <input type="text" name="contact_name" class="form-input" placeholder="请输入联系人姓名" required>
                </div>
                <div class="form-group"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">联系电话</label>
                    <input type="tel" name="phone" class="form-input" placeholder="请输入联系电话" required>
                    <div class="form-hint">示例：13800138000</div>
                </div>
                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input type="email" name="email" class="form-input" placeholder="请输入邮箱地址（可选）">
                    <div class="form-hint">示例：example@company.com</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">纳税人识别号（税号）</label>
                    <input type="text" name="tax_number" class="form-input" placeholder="请输入税号（可选）">
                    <div class="form-hint">企业统一社会信用代码 / 纳税人识别号</div>
                </div>
                <div class="form-group">
                    <label class="form-label">公司地址</label>
                    <input type="text" name="address" class="form-input" placeholder="请输入公司地址（可选）">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">备注</label>
                <textarea name="notes" class="form-textarea" placeholder="其他备注信息（可选）" rows="3"></textarea>
            </div>
        </div>
    </form>
</main>

<script>
function saveCustomer() {
    const form = document.getElementById('customerForm');
    const companyVal = form.querySelector('[name="company_name"]')?.value.trim();
    if (!companyVal) { sapToast('请填写公司名称！', 'warning'); return; }
    const formData = new FormData(form);
    const btn = event.target;
    btn.disabled = true; btn.textContent = '保存中...';
    fetch('customer_save.php', { method:'POST', body:formData })
    .then(r => { if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(data => {
        if (data.success) { sapToast(data.message, 'success'); setTimeout(()=>window.location.href='customers.php',1200); }
        else { sapToast('保存失败: '+data.message, 'error'); btn.disabled=false; btn.textContent='💾 保存客户'; }
    })
    .catch(err => { sapToast('保存出错: '+err.message, 'error'); btn.disabled=false; btn.textContent='💾 保存客户'; });
}
document.getElementById('customerForm').addEventListener('keypress', function(e) {
    if (e.key==='Enter' && e.target.tagName!=='TEXTAREA') { e.preventDefault(); saveCustomer(); }
});
</script>

<!-- SAP风格 Toast 消息（右上角滑入） -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:280px;max-width:420px;padding:14px 18px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:18px;flex-shrink:0;margin-top:1px;"></span>
  <span id="sapToastMsg" style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<script>
function sapToast(msg, type, duration) {
    type     = type     || 'info';
    duration = duration || 3000;
    const colors = { success:'#16a34a', error:'#dc2626', warning:'#d97706', info:'#1f6dad' };
    const icons  = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
    const el = document.getElementById('sapToast');
    if (!el) { console.warn('sapToast element missing'); return; }
    document.getElementById('sapToastIcon').textContent = icons[type]  || 'ℹ️';
    document.getElementById('sapToastMsg').textContent  = msg;
    el.style.background = colors[type] || '#1f6dad';
    el.style.display    = 'flex';
    el.style.opacity    = '1';
    clearTimeout(el._timer);
    el._timer = setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => { el.style.display = 'none'; }, 320);
    }, duration);
}
</script>

</body>
</html>
