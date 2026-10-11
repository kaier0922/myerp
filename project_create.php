<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
$conn     = getDBConnection();
$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? $_SESSION['user_name'] ?? '用户';
$role     = $_SESSION['role'] ?? '';

$edit_id=intval($_GET['id']??0);
$proj=null;
if($edit_id){
    $s=$conn->prepare("SELECT * FROM projects WHERE id=?");
    $s->bind_param('i',$edit_id);$s->execute();
    $proj=$s->get_result()->fetch_assoc();
}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name    =trim($_POST['project_name']??'');
    $ptype   =$_POST['project_type']??'other';
    $status  =$_POST['status']??'planning';
    $cust_id =intval($_POST['customer_id']??0)?:null;
    $quote_id=intval($_POST['quote_id']??0)?:null;
    $repair_id=intval($_POST['repair_id']??0)?:null;
    $amount  =floatval($_POST['contract_amount']??0);
    $sdate   =$_POST['start_date']?:null;
    $edate   =$_POST['end_date']?:null;
    $notes   =trim($_POST['notes']??'');
    if(!$name){$error='项目名称不能为空';}
    if(!$error){
        if($edit_id){
            $st=$conn->prepare("UPDATE projects SET project_name=?,project_type=?,status=?,customer_id=?,quote_id=?,repair_id=?,contract_amount=?,start_date=?,end_date=?,notes=?,updated_at=NOW() WHERE id=?");
            $st->bind_param('ssssssdsssi',$name,$ptype,$status,$cust_id,$quote_id,$repair_id,$amount,$sdate,$edate,$notes,$edit_id);
            $st->execute();
            writeLog($conn, 'projects', 'create', '新建项目');
            header("Location: project_detail.php?id=$edit_id");exit;
        }else{
            $year=date('Y');
            $cnt=$conn->query("SELECT COUNT(*) FROM projects WHERE project_no LIKE 'P{$year}%'")->fetch_row()[0]+1;
            $pno='P'.$year.str_pad($cnt,4,'0',STR_PAD_LEFT);
            $st=$conn->prepare("INSERT INTO projects (project_no,project_name,project_type,status,customer_id,quote_id,repair_id,contract_amount,start_date,end_date,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param('sssssssdsssi',$pno,$name,$ptype,$status,$cust_id,$quote_id,$repair_id,$amount,$sdate,$edate,$notes,$user_id);
            $st->execute();
            header("Location: project_detail.php?id=".$conn->insert_id);exit;
        }
    }
}
$customers=$conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name FROM customers ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);
$quotes=$conn->query("SELECT id,quote_no,project_name AS title,final_amount FROM quotes ORDER BY created_at DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
$repairs=$conn->query("SELECT id,order_no AS repair_no,CONCAT(device_brand,' ',device_model) AS device_name FROM repair_orders ORDER BY created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
$fq=intval($_GET['from_quote']??0);
$qd=null;
if($fq){$s=$conn->prepare("SELECT * FROM quotes WHERE id=?");$s->bind_param('i',$fq);$s->execute();$qd=$s->get_result()->fetch_assoc();}
?>
<?php $page_title = ($edit_id?'编辑':'新建') . '项目'; include __DIR__ . '/head.php'; ?>
<style>
.form-card{max-width:860px;margin:0 auto;}
.main-content.drawer-open { margin-right: 420px; }

.page-subtitle { color: #718096; font-size: 14px; }

.tabs {
    background: white; border-radius: 12px; padding: 8px;
    margin-bottom: 24px; display: flex; gap: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.tab-btn {
    flex: 1; padding: 12px 24px; background: transparent; border: none;
    border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 500;
    color: #718096; transition: all 0.2s;
}
.tab-btn:hover  { background: #f7fafc; }
.tab-btn.active { background: linear-gradient(135deg,#1f6dad 0%,#135286 100%); color: white; }
.tab-content        { display: none; }
.tab-content.active { display: block; }

.stats-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }
.stat-box { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.stat-box-label { font-size: 13px; color: #718096; margin-bottom: 8px; }
.stat-box-value { font-size: 24px; font-weight: 700; color: #1a202c; }
.positive { color: #1f6dad !important; }
.negative { color: #f56565 !important; }

.card-title  { font-size: 18px; font-weight: 600; color: #1a202c; }

.amount { font-weight: 600; font-family: 'Courier New', monospace; }
.overdue { color: #f56565; font-weight: 600; }

.btn-group { display: flex; gap: 6px; flex-wrap: wrap; }

/* ============================================================
   详情抽屉（右侧滑入面板）
   ============================================================ */
.drawer-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.3);
    z-index: 299; display: none;
}
.drawer-overlay.open { display: block; }

.detail-drawer {
    position: fixed; top: 0; right: -420px; width: 420px; height: 100vh;
    background: white; box-shadow: -4px 0 24px rgba(0,0,0,0.12);
    z-index: 300; transition: right 0.3s ease;
    display: flex; flex-direction: column;
}
.detail-drawer.open { right: 0; }

.drawer-header {
    padding: 20px 24px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
    background: linear-gradient(135deg,#1f6dad 0%,#135286 100%);
}
.drawer-title { font-size: 16px; font-weight: 600; color: white; }
.drawer-close {
    width: 32px; height: 32px; background: rgba(255,255,255,0.2); border: none;
    border-radius: 6px; color: white; font-size: 18px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.drawer-close:hover { background: rgba(255,255,255,0.35); }

.drawer-body { flex: 1; overflow-y: auto; padding: 20px 24px; }

/* 抽屉内信息块 */
.dw-section { margin-bottom: 20px; }
.dw-section-title {
    font-size: 11px; font-weight: 600; color: #a0aec0;
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;
    padding-bottom: 6px; border-bottom: 1px solid #f0f0f0;
}
.dw-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; font-size: 14px; }
.dw-label { color: #718096; flex-shrink: 0; width: 90px; }
.dw-value { color: #1a202c; font-weight: 500; text-align: right; flex: 1; }
.dw-value.amount { font-family: 'Courier New',monospace; }

/* 金额大显示 */
.dw-amount-box {
    background: #f7fafc; border-radius: 10px; padding: 16px;
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;
    margin-bottom: 20px; text-align: center;
}
.dw-amount-item .av { font-size: 18px; font-weight: 700; font-family: 'Courier New',monospace; }
.dw-amount-item .al { font-size: 11px; color: #718096; margin-top: 4px; }

/* 收付款历史 */
.payment-item {
    background: #f7fafc; border-radius: 8px; padding: 12px;
    margin-bottom: 8px; border-left: 3px solid #1f6dad;
}
.payment-item .pi-top { display: flex; justify-content: space-between; margin-bottom: 4px; }
.payment-item .pi-amount { font-size: 15px; font-weight: 600; color: #1f6dad; font-family: 'Courier New',monospace; }
.payment-item .pi-date   { font-size: 12px; color: #718096; }
.payment-item .pi-method { font-size: 12px; color: #4a5568; }
.payment-item .pi-note   { font-size: 12px; color: #718096; margin-top: 4px; }

.no-payment { text-align: center; padding: 24px; color: #a0aec0; font-size: 14px; }

/* 抽屉底部操作按钮 */
.drawer-footer {
    padding: 16px 24px; border-top: 1px solid #e2e8f0; flex-shrink: 0;
    display: flex; gap: 8px; flex-wrap: wrap;
}
.dw-btn {
    padding: 8px 14px; border: none; border-radius: 6px; font-size: 13px;
    font-weight: 500; cursor: pointer; transition: all 0.2s;
}
.dw-btn-primary  { background: linear-gradient(135deg,#1f6dad 0%,#135286 100%); color: white; }
.dw-btn-primary:hover  { transform: translateY(-1px); }
.dw-btn-amber    { background: #d97706; color: white; }
.dw-btn-amber:hover    { background: #b45309; }
.dw-btn-red      { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.dw-btn-red:hover      { background: #fecaca; }
.dw-btn-gray     { background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; }
.dw-btn-gray:hover     { background: #edf2f7; }

.edit-form { display: none; background: #f7fafc; border-radius: 8px; padding: 16px; margin-top: 12px; }
.edit-form.show { display: block; }
.ef-row { margin-bottom: 12px; }
.ef-row label { display: block; font-size: 12px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.ef-row input, .ef-row textarea, .ef-row select {
    width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 13px; font-family: inherit;
}
.ef-row input:focus, .ef-row textarea:focus { outline: none; border-color: #1f6dad; }
.ef-actions { display: flex; gap: 8px; }

.form-section-title{font-size:14px;font-weight:600;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin:24px 0 16px;padding-bottom:8px;border-bottom:1px solid #e2e8f0}
.form-row.cols2{grid-template-columns:1fr 1fr}
.form-row.cols3{grid-template-columns:1fr 1fr 1fr}
.form-row.cols1{grid-template-columns:1fr}
.fg label{display:block;font-size:13px;font-weight:500;color:#4a5568;margin-bottom:6px}
.fg input,.fg select,.fg textarea{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#2d3748;box-sizing:border-box;transition:border-color .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1)}
.fg textarea{height:90px;resize:vertical}
.fg .hint{font-size:12px;color:#a0aec0;margin-top:4px}
.err-box{background:#fff5f5;border:1px solid #fc8181;border-radius:8px;padding:12px 16px;margin-bottom:20px;color:#c53030;font-size:14px}
</style>
</head><body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <div class="page-header">
    <h1 class="page-title"><?=$edit_id?'✏️ 编辑项目':'➕ 新建项目'?></h1>
    <a href="project_list.php" style="padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;font-weight:500;text-decoration:none;">← 返回列表</a>
  </div>
  <div class="form-card">
    <?php if($error): ?><div class="err-box">⚠️ <?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post">
    <div class="form-section-title">基本信息</div>
    <div class="form-row cols2">
      <div class="fg"><label>项目名称 <span style="color:#f56565">*</span></label>
        <input type="text" name="project_name" value="<?=htmlspecialchars($proj['project_name']??$qd['title']??'')?>" placeholder="如：XX公司弱电工程"></div>
      <div class="fg"><label>项目类型</label>
        <select name="project_type">
          <option value="weak_current" <?=($proj['project_type']??'')==='weak_current'?'selected':''?>>弱电工程</option>
          <option value="hardware"     <?=($proj['project_type']??'')==='hardware'?'selected':''?>>硬件采购</option>
          <option value="repair"       <?=($proj['project_type']??'')==='repair'?'selected':''?>>维修工单</option>
          <option value="other"        <?=($proj['project_type']??'other')==='other'?'selected':''?>>其他</option>
        </select></div>
    </div>
    <div class="form-row cols3">
      <div class="fg"><label>客户</label>
        <select name="customer_id">
          <option value="">— 选择客户 —</option>
          <?php foreach($customers as $c): ?>
          <option value="<?=$c['id']?>" <?=($proj['customer_id']??$qd['customer_id']??0)==$c['id']?'selected':''?>><?=htmlspecialchars($c['company_name'])?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="fg"><label>预算参考金额 (¥)（可选）</label>
        <input type="number" name="contract_amount" step="any" value="<?=$proj['contract_amount']??$qd['final_amount']??0?>" placeholder="0.00">
        <div class="hint">可选。实际收入以关联送货单为准，此处填预算/合同金额作参考</div></div>
      <div class="fg"><label>项目状态</label>
        <select name="status">
          <option value="planning"    <?=($proj['status']??'planning')==='planning'?'selected':''?>>规划中</option>
          <option value="in_progress" <?=($proj['status']??'')==='in_progress'?'selected':''?>>进行中</option>
          <option value="completed"   <?=($proj['status']??'')==='completed'?'selected':''?>>已完成</option>
          <option value="cancelled"   <?=($proj['status']??'')==='cancelled'?'selected':''?>>已取消</option>
        </select></div>
    </div>
    <div style="background:#ebf8ff;border:1px solid #bee3f8;border-radius:10px;padding:14px 18px;margin-bottom:16px">
      <div style="font-size:13px;font-weight:600;color:#2b6cb0;margin-bottom:6px">🚚 送货单 & 成本在详情页录入</div>
      <div style="font-size:13px;color:#4a5568;line-height:1.8">
        保存项目后，进入<strong>项目详情页</strong>操作：<br>
        &nbsp;&nbsp;① 关联已审核的<strong>送货单</strong>（作为收入）<br>
        &nbsp;&nbsp;② 关联<strong>应付账单</strong>（供应商材料成本）<br>
        &nbsp;&nbsp;③ 手工录入<strong>人工 / 差旅 / 杂费</strong><br>
        <span style="color:#1f6dad">毛利 = 送货收入 − 应付成本 − 手工成本</span>
      </div>
    </div>
    <div class="form-section-title">时间与备注</div>
    <div class="form-row cols2">
      <div class="fg"><label>开始日期</label><input type="date" name="start_date" value="<?=$proj['start_date']??''?>"></div>
      <div class="fg"><label>预计结束日期</label><input type="date" name="end_date" value="<?=$proj['end_date']??''?>"></div>
    </div>
    <div class="form-row cols1">
      <div class="fg"><label>备注</label><textarea name="notes"><?=htmlspecialchars($proj['notes']??'')?></textarea></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn-primary">💾 保存项目</button>
      <a href="<?=$edit_id?'project_detail.php?id='.$edit_id:'project_list.php'?>" class="btn-secondary">取消</a>
    </div>
    </form>
  </div>
</div>
</body></html>
