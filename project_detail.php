<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();
require_once 'config.php';
$conn     = getDBConnection();
$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? $_SESSION['user_name'] ?? '用户';
$role     = $_SESSION['role'] ?? '';

$id=intval($_GET['id']??0);
if(!$id){header('Location: project_list.php');exit;}

/* ── AJAX ── */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    header('Content-Type: application/json');
    $act=$_POST['action'];

    if($act==='link_payable'){
        $pids=json_decode($_POST['payable_ids']??'[]',true);
        if(!is_array($pids)||empty($pids)){echo json_encode(['ok'=>false,'msg'=>'未选择']);exit;}
        $ph=implode(',',array_fill(0,count($pids),'?'));
        $args=array_merge([$id],$pids);
        $types='i'.str_repeat('i',count($pids));
        $st=$conn->prepare("UPDATE accounts_payable SET project_id=? WHERE id IN($ph)");
        $st->bind_param($types,...$args);$st->execute();
        echo json_encode(['ok'=>true,'affected'=>$st->affected_rows]);
    }
    elseif($act==='unlink_payable'){
        $pid=intval($_POST['payable_id']);
        $st=$conn->prepare("UPDATE accounts_payable SET project_id=NULL WHERE id=? AND project_id=?");
        $st->bind_param('ii',$pid,$id);$st->execute();
        echo json_encode(['ok'=>true]);
    }
    elseif($act==='add_cost'){
        $ctype=$_POST['cost_type']??'misc';
        $desc=trim($_POST['description']??'');
        $qty=floatval($_POST['qty']??1);
        $unit=trim($_POST['unit']??'项');
        $uprice=floatval($_POST['unit_price']??0);
        $amount=$qty*$uprice;
        $cdate=$_POST['cost_date']?:date('Y-m-d');
        $notes=trim($_POST['notes']??'');
        if(!$desc){echo json_encode(['ok'=>false,'msg'=>'描述不能为空']);exit;}
        $st=$conn->prepare("INSERT INTO project_costs (project_id,cost_type,description,qty,unit,unit_price,amount,cost_date,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('issdsddssi',$id,$ctype,$desc,$qty,$unit,$uprice,$amount,$cdate,$notes,$user_id);
        $st->execute();
        echo json_encode(['ok'=>true,'id'=>$conn->insert_id,'amount'=>$amount]);
    }
    elseif($act==='del_cost'){
        $cid=intval($_POST['cost_id']);
        $st=$conn->prepare("DELETE FROM project_costs WHERE id=? AND project_id=?");
        $st->bind_param('ii',$cid,$id);$st->execute();
        echo json_encode(['ok'=>true]);
    }
    elseif($act==='update_status'){
        $s=$_POST['status']??'';
        if(in_array($s,['planning','in_progress','completed','cancelled'])){
            $st=$conn->prepare("UPDATE projects SET status=?,updated_at=NOW() WHERE id=?");
            $st->bind_param('si',$s,$id);$st->execute();
        }
        echo json_encode(['ok'=>true]);
    }
    elseif($act==='search_deliveries'){
        $kw=trim($_POST['kw']??'');
        $where=["(d.project_id IS NULL OR d.project_id=0)","d.approved=1"];
        $params=[];$types='';
        if($kw){
            $where[]='(d.customer_name LIKE ? OR d.delivery_no LIKE ?)';
            $k="%$kw%";$params[]=$k;$params[]=$k;$types.='ss';
        }
        $sql="SELECT * FROM deliveries d WHERE ".implode(' AND ',$where)." ORDER BY d.delivery_date DESC LIMIT 60";
        $st=$conn->prepare($sql);
        if($params) $st->bind_param($types,...$params);
        $st->execute();
        $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok'=>true,'rows'=>$rows]);
    }
    elseif($act==='link_delivery'){
        $dids=json_decode($_POST['delivery_ids']??'[]',true);
        if(!is_array($dids)||empty($dids)){echo json_encode(['ok'=>false,'msg'=>'未选择']);exit;}
        $ph=implode(',',array_fill(0,count($dids),'?'));
        $args=array_merge([$id],$dids);
        $types='i'.str_repeat('i',count($dids));
        $st=$conn->prepare("UPDATE deliveries SET project_id=? WHERE id IN($ph) AND approved=1");
        $st->bind_param($types,...$args);$st->execute();
        echo json_encode(['ok'=>true,'affected'=>$st->affected_rows]);
    }
    elseif($act==='unlink_delivery'){
        $did=intval($_POST['delivery_id']);
        $st=$conn->prepare("UPDATE deliveries SET project_id=NULL WHERE id=? AND project_id=?");
        $st->bind_param('ii',$did,$id);$st->execute();
        echo json_encode(['ok'=>true]);
    }
    elseif($act==='search_payables'){
        $kw=trim($_POST['kw']??'');
        $where=['(ap.project_id IS NULL OR ap.project_id=0)'];
        $params=[];$types='';
        if($kw){
            $where[]='(ap.supplier_name LIKE ? OR ap.bill_no LIKE ? OR ap.category LIKE ?)';
            $k="%$kw%";$params[]=$k;$params[]=$k;$params[]=$k;$types.='sss';
        }
        $sql="SELECT ap.*,s.company_name AS sup_name FROM accounts_payable ap LEFT JOIN suppliers s ON ap.supplier_id=s.id WHERE ".implode(' AND ',$where)." ORDER BY ap.bill_date DESC LIMIT 60";
        $st=$conn->prepare($sql);
        if($params) $st->bind_param($types,...$params);
        $st->execute();
        $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok'=>true,'rows'=>$rows]);
    }
    exit;
}

/* ── 加载数据 ── */
$st=$conn->prepare("SELECT p.*,COALESCE(c.short_name, c.company_name) AS company_name,q.quote_no,q.project_name AS q_title,q.final_amount AS q_amount
    FROM projects p LEFT JOIN customers c ON p.customer_id=c.id LEFT JOIN quotes q ON p.quote_id=q.id
    WHERE p.id=?");
$st->bind_param('i',$id);$st->execute();
$proj=$st->get_result()->fetch_assoc();
if(!$proj){header('Location: project_list.php');exit;}

$del=$conn->prepare("SELECT * FROM deliveries WHERE project_id=? AND approved=1 ORDER BY delivery_date DESC");
$del->bind_param('i',$id);$del->execute();
$delivery_rows=$del->get_result()->fetch_all(MYSQLI_ASSOC);
$delivery_total=array_sum(array_column($delivery_rows,'total_amount'));
$del_cnt=count($delivery_rows);

$pay=$conn->prepare("SELECT ap.*,s.company_name AS sup_name FROM accounts_payable ap LEFT JOIN suppliers s ON ap.supplier_id=s.id WHERE ap.project_id=? ORDER BY ap.bill_date DESC");
$pay->bind_param('i',$id);$pay->execute();
$payable_rows=$pay->get_result()->fetch_all(MYSQLI_ASSOC);
$payable_total=array_sum(array_column($payable_rows,'total_amount'));

$mc=$conn->prepare("SELECT * FROM project_costs WHERE project_id=? ORDER BY cost_date DESC,id DESC");
$mc->bind_param('i',$id);$mc->execute();
$manual_rows=$mc->get_result()->fetch_all(MYSQLI_ASSOC);
$manual_total=array_sum(array_column($manual_rows,'amount'));

$total_cost=$payable_total+$manual_total;
$contract=floatval($proj['contract_amount']);
$profit=$delivery_total-$total_cost;
$margin=$delivery_total>0?$profit/$delivery_total*100:0;
$cost_ratio=$delivery_total>0?min(100,$total_cost/$delivery_total*100):0;

$type_labels=['weak_current'=>'弱电工程','hardware'=>'硬件采购','repair'=>'维修工单','other'=>'其他'];
$status_labels=['planning'=>'规划中','in_progress'=>'进行中','completed'=>'已完成','cancelled'=>'已取消'];
$status_badge=['planning'=>'warning','in_progress'=>'info','completed'=>'success','cancelled'=>''];
$ctype_labels=['labor'=>'人工工时','transport'=>'运输差旅','misc'=>'其他杂费'];
$ctype_icons=['labor'=>'👷','transport'=>'🚚','misc'=>'💰'];
$pay_badge=['未付款'=>'danger','部分付款'=>'warning','已付款'=>'success'];
$labor_sum=array_sum(array_column(array_filter($manual_rows,fn($r)=>$r['cost_type']==='labor'),'amount'));
$transport_sum=array_sum(array_column(array_filter($manual_rows,fn($r)=>$r['cost_type']==='transport'),'amount'));
$misc_sum=array_sum(array_column(array_filter($manual_rows,fn($r)=>$r['cost_type']==='misc'),'amount'));
?>
<?php $page_title = '项目详情 - ' . htmlspecialchars($proj['project_name']); include __DIR__ . '/head.php'; ?>
<style>

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

.detail-grid{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start}
.tab-btn{flex:1;padding:10px 20px;background:transparent;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;color:#718096;transition:all .2s}
.tab-btn.active{background:linear-gradient(135deg,#1f6dad,#135286);color:white}
.tabs{background:white;border-radius:12px;padding:6px;margin-bottom:20px;display:flex;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.tab-content{display:none}.tab-content.active{display:block}
.fg label{display:block;font-size:13px;font-weight:500;color:#4a5568;margin-bottom:5px}
.fg input,.fg select{width:100%;padding:8px 11px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box}
.fg input:focus,.fg select:focus{outline:none;border-color:#1f6dad}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px}
.row4{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px;margin-bottom:12px}
.search-result{border:1px solid #e2e8f0;border-radius:10px;max-height:300px;overflow-y:auto;margin-top:10px}
.sr-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid #f0f0f0;font-size:13px;transition:background .15s}
.sr-item:hover{background:#f7fafc}.sr-item:last-child{border:none}
.sr-item input[type=checkbox]{width:16px;height:16px;cursor:pointer;flex-shrink:0;accent-color:#1f6dad}
.btn-full{width:100%;padding:11px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-top:4px}
.btn-danger{padding:5px 12px;background:white;color:#f56565;border:1px solid #fc8181;border-radius:6px;font-size:12px;cursor:pointer}
.btn-sm-p{padding:6px 14px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;text-decoration:none;display:inline-block}
.profit-box{border-radius:10px;padding:16px;margin-top:16px}
.profit-pos{background:#ebf8ff;border-left:4px solid #1f6dad}
.profit-neg{background:#fff5f5;border-left:4px solid #f56565}
.progress-wrap{height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;margin-top:8px}
.progress-fill{height:100%;border-radius:4px;transition:width .5s}
.cost-summary-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:14px}
.cost-summary-row:last-of-type{border:none}
.meta-item{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#718096;margin-right:16px}
.status-sel{background:transparent;border:none;font-size:13px;font-weight:600;cursor:pointer;color:inherit}
.status-sel option{color:#2d3748;background:white}
.toast{position:fixed;bottom:24px;right:24px;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:500;color:white;z-index:9999;display:none;box-shadow:0 4px 16px rgba(0,0,0,.15)}
</style>
</head><body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

<!-- 项目头部 -->
<div class="page-header">
  <div style="display:flex;justify-content:space-between;align-items:flex-start">
  <div>
    <h1 class="page-title"><?=htmlspecialchars($proj['project_name'])?></h1>
    <div style="margin-top:8px">
      <span class="meta-item">📋 <?=htmlspecialchars($proj['project_no'])?></span>
      <span class="meta-item">🏷️ <?=$type_labels[$proj['project_type']]??$proj['project_type']?></span>
      <?php if($proj['company_name']): ?><span class="meta-item">🏢 <?=htmlspecialchars($proj['company_name'])?></span><?php endif; ?>
      <?php if($proj['quote_no']): ?><span class="meta-item">📄 <a href="quote_view.php?id=<?=$proj['quote_id']?>" style="color:#1f6dad"><?=htmlspecialchars($proj['quote_no'])?></a> (¥<?=number_format($proj['q_amount'],2)?>)</span><?php endif; ?>
      <?php if($proj['start_date']): ?><span class="meta-item">📅 <?=$proj['start_date']?> ~ <?=$proj['end_date']?:'未定'?></span><?php endif; ?>
    </div>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <span class="badge <?=$status_badge[$proj['status']]??''?>" style="font-size:13px;padding:7px 16px">
      <select class="status-sel" onchange="updateStatus(this.value)">
        <?php foreach($status_labels as $k=>$v): ?>
        <option value="<?=$k?>" <?=$proj['status']===$k?'selected':''?>><?=$v?></option>
        <?php endforeach; ?>
      </select>
    </span>
    <a href="project_create.php?id=<?=$id?>" class="btn-secondary" style="padding:7px 16px;font-size:13px">✏️ 编辑</a>
    <a href="project_list.php" class="btn-secondary" style="padding:7px 16px;font-size:13px">← 返回</a>
  </div>
  </div>
</div>

<!-- KPI -->
<div class="stats-row">
  <div class="stat-box"><div class="stat-box-label">送货收入（已审核）</div><div class="stat-box-value positive">¥<?=number_format($delivery_total,2)?></div><div style="font-size:12px;color:#a0aec0;margin-top:4px"><?=$del_cnt?>张送货单</div></div>
  <div class="stat-box"><div class="stat-box-label">应付账单成本</div><div class="stat-box-value negative" id="kpi_payable">¥<?=number_format($payable_total,2)?></div><div style="font-size:12px;color:#a0aec0;margin-top:4px" id="kpi_payable_cnt"><?=count($payable_rows)?>笔账单</div></div>
  <div class="stat-box"><div class="stat-box-label">手工录入成本</div><div class="stat-box-value" style="color:#718096" id="kpi_manual">¥<?=number_format($manual_total,2)?></div><div style="font-size:12px;color:#a0aec0;margin-top:4px" id="kpi_manual_cnt"><?=count($manual_rows)?>条记录</div></div>
  <div class="stat-box"><div class="stat-box-label">预计毛利</div><div class="stat-box-value <?=$profit>=0?'positive':'negative'?>" id="kpi_profit">¥<?=number_format($profit,2)?></div><div style="font-size:12px;color:#a0aec0;margin-top:4px" id="kpi_margin"><?=number_format($margin,1)?>% 毛利率</div></div>
</div>

<!-- 主体 -->
<div class="detail-grid">
<div>
  <!-- 成本录入 -->
  <div class="content-card" style="margin-bottom:20px">
    <div class="card-header"><h2 class="card-title">💼 录入成本</h2></div>
    <div style="padding:20px">
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('delivery',this)">🚚 关联送货单</button>
        <button class="tab-btn" onclick="switchTab('payable',this)">📋 关联应付账单</button>
        <button class="tab-btn" onclick="switchTab('manual',this)">✏️ 手工录入</button>
      </div>

      <div class="tab-content active" id="tab-delivery">
        <p style="font-size:13px;color:#718096;margin-bottom:14px">搜索已审核的送货单，关联后作为本项目的实际收入依据。</p>
        <div style="display:flex;gap:10px">
          <input type="text" id="dkwInput" placeholder="客户名 / 送货单号" style="flex:1;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px" onkeydown="if(event.key==='Enter')searchDeliveries()">
          <button class="btn-primary" onclick="searchDeliveries()">🔍 搜索</button>
          <button class="btn-secondary" onclick="showAllDel()">全部未关联</button>
        </div>
        <div id="delSearchResult" style="display:none" class="search-result"></div>
        <div id="delLinkActions" style="display:none;margin-top:12px;display:flex;gap:10px;align-items:center">
          <button class="btn-primary" onclick="linkDeliveries()">✅ 关联选中</button>
          <span style="font-size:13px;color:#718096">已选 <strong id="delSelCount">0</strong> 条</span>
        </div>
      </div>
      <!-- 关联应付 -->
      <div class="tab-content" id="tab-payable">
        <p style="font-size:13px;color:#718096;margin-bottom:14px">搜索已录入的应付账单，勾选后批量关联到本项目。同一供应商多张账单均可选。</p>
        <div style="display:flex;gap:10px">
          <input type="text" id="kwInput" placeholder="供应商名称 / 账单号 / 分类" style="flex:1;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px" onkeydown="if(event.key==='Enter')searchPayables()">
          <button class="btn-primary" onclick="searchPayables()">🔍 搜索</button>
          <button class="btn-secondary" onclick="showAll()">显示全部</button>
        </div>
        <div id="searchResult" style="display:none" class="search-result"></div>
        <div id="linkActions" style="display:none;margin-top:12px;display:flex;gap:10px;align-items:center">
          <button class="btn-primary" onclick="linkSelected()">✅ 关联选中</button>
          <span style="font-size:13px;color:#718096">已选 <strong id="selCount">0</strong> 条</span>
        </div>
      </div>

      <!-- 手工录入 -->
      <div class="tab-content" id="tab-manual">
        <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap" id="ctypeTabs">
          <button class="tab-btn active" data-ct="labor"     onclick="selCT('labor',this)"     style="flex:none;padding:8px 18px;border-radius:20px">👷 人工工时</button>
          <button class="tab-btn"        data-ct="transport" onclick="selCT('transport',this)" style="flex:none;padding:8px 18px;border-radius:20px">🚚 运输差旅</button>
          <button class="tab-btn"        data-ct="misc"      onclick="selCT('misc',this)"      style="flex:none;padding:8px 18px;border-radius:20px">💰 其他杂费</button>
        </div>
        <input type="hidden" id="selCT" value="labor">
        <div class="row2">
          <div class="fg"><label>费用描述 *</label><input type="text" id="mc_desc" placeholder="如：施工人工 3人×2天"></div>
          <div class="fg"><label>发生日期</label><input type="date" id="mc_date" value="<?=date('Y-m-d')?>"></div>
        </div>
        <div class="row4">
          <div class="fg"><label>数量</label><input type="number" id="mc_qty" value="1" step="10" oninput="calcMC()"></div>
          <div class="fg"><label>单位</label><input type="text" id="mc_unit" value="项"></div>
          <div class="fg"><label>单价 (¥)</label><input type="number" id="mc_uprice" value="0" step="any" oninput="calcMC()"></div>
          <div class="fg"><label>金额 (¥)</label><input type="number" id="mc_amount" value="0" step="any" style="background:#ebf8ff;font-weight:600;color:#1f6dad" readonly></div>
        </div>
        <div class="fg" style="margin-bottom:14px"><label>备注</label><input type="text" id="mc_notes" placeholder="可选"></div>
        <button class="btn-full" onclick="submitManual()">💾 添加手工成本记录</button>
      </div>
    </div>
  </div>

  <div class="content-card" style="margin-bottom:20px">
    <div class="card-header">
      <h2 class="card-title">🚚 已关联送货单（收入）</h2>
      <span style="font-size:14px;color:#718096" id="delivery_list_hd"><?=$del_cnt?>张 · 合计 ¥<?=number_format($delivery_total,2)?></span>
    </div>
    <table>
    <thead><tr><th>送货单号</th><th>客户</th><th>送货日期</th><th>金额</th><th>备注</th><th>操作</th></tr></thead>
    <tbody id="deliveryTbody">
    <?php foreach($delivery_rows as $r): ?>
    <tr id="dr_<?=$r['id']?>">
      <td><code style="font-size:12px"><?=htmlspecialchars($r['delivery_no'])?></code></td>
      <td><?=htmlspecialchars($r['customer_name']??'—')?></td>
      <td style="color:#718096"><?=$r['delivery_date']?></td>
      <td class="amount positive">¥<?=number_format($r['total_amount'],2)?></td>
      <td style="color:#a0aec0;font-size:12px"><?=htmlspecialchars(mb_substr($r['notes']??'',0,20))?></td>
      <td><button class="btn-danger" onclick="unlinkDelivery(<?=$r['id']?>)">取消关联</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($delivery_rows)): ?>
    <tr id="deliveryEmpty"><td colspan="6" style="text-align:center;color:#a0aec0;padding:30px">暂无关联送货单</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
  </div>
  <!-- 应付账单列表 -->
  <div class="content-card" style="margin-bottom:20px">
    <div class="card-header">
      <h2 class="card-title">📋 已关联应付账单</h2>
      <span style="font-size:14px;color:#718096" id="payable_list_hd"><?=count($payable_rows)?>笔 · 合计 ¥<?=number_format($payable_total,2)?></span>
    </div>
    <table>
    <thead><tr><th>账单号</th><th>供应商</th><th>账单日期</th><th>金额</th><th>已付</th><th>状态</th><th>备注</th><th>操作</th></tr></thead>
    <tbody id="payableTbody">
    <?php foreach($payable_rows as $r): ?>
    <tr id="pr_<?=$r['id']?>">
      <td><code style="font-size:12px"><?=htmlspecialchars($r['bill_no'])?></code></td>
      <td><?=htmlspecialchars($r['supplier_name']??$r['sup_name']??'—')?></td>
      <td style="color:#718096"><?=$r['bill_date']?></td>
      <td class="amount">¥<?=number_format($r['total_amount'],2)?></td>
      <td class="amount positive">¥<?=number_format($r['paid_amount'],2)?></td>
      <td><span class="badge <?=$pay_badge[$r['status']]??'info'?>"><?=htmlspecialchars($r['status'])?></span></td>
      <td style="color:#a0aec0;font-size:12px"><?=htmlspecialchars(mb_substr($r['notes']??'',0,20))?></td>
      <td><button class="btn-danger" onclick="unlinkPayable(<?=$r['id']?>)">取消关联</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($payable_rows)): ?>
    <tr id="payableEmpty"><td colspan="8" style="text-align:center;color:#a0aec0;padding:30px">暂无关联账单</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
  </div>

  <!-- 手工成本列表 -->
  <div class="content-card">
    <div class="card-header">
      <h2 class="card-title">✏️ 手工成本明细</h2>
      <span style="font-size:14px;color:#718096" id="manual_list_hd"><?=count($manual_rows)?>条 · 合计 ¥<?=number_format($manual_total,2)?></span>
    </div>
    <table>
    <thead><tr><th>类型</th><th>描述</th><th>数量</th><th>单价</th><th>金额</th><th>日期</th><th>操作</th></tr></thead>
    <tbody id="manualTbody">
    <?php foreach($manual_rows as $r): ?>
    <tr id="mc_<?=$r['id']?>">
      <td><span class="badge info"><?=$ctype_icons[$r['cost_type']]?> <?=$ctype_labels[$r['cost_type']]?></span></td>
      <td><?=htmlspecialchars($r['description'])?></td>
      <td><?=rtrim(rtrim(number_format($r['qty'],3),'0'),'.')?> <?=htmlspecialchars($r['unit'])?></td>
      <td class="amount">¥<?=number_format($r['unit_price'],2)?></td>
      <td class="amount negative">¥<?=number_format($r['amount'],2)?></td>
      <td style="color:#718096"><?=$r['cost_date']?></td>
      <td><button class="btn-danger" onclick="delManual(<?=$r['id']?>,<?=$r['amount']?>,this)">删除</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($manual_rows)): ?>
    <tr id="manualEmpty"><td colspan="7" style="text-align:center;color:#a0aec0;padding:30px">暂无手工成本记录</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
  </div>
</div>

<!-- 右列 -->
<div>
  <div class="content-card">
    <div class="card-header"><h2 class="card-title">📊 成本构成</h2></div>
    <div style="padding:20px">
      <svg id="pie" width="100%" viewBox="0 0 200 200"></svg>
      <div style="margin-top:4px">
        <div class="cost-summary-row"><span style="color:#718096">🧾 供应商账单</span><div><span class="amount" id="s_payable">¥<?=number_format($payable_total,2)?></span> <span style="font-size:12px;color:#a0aec0" id="s_payable_pct"></span></div></div>
        <div class="cost-summary-row"><span style="color:#718096">👷 人工工时</span><div><span class="amount" id="s_labor">¥<?=number_format($labor_sum,2)?></span> <span style="font-size:12px;color:#a0aec0" id="s_labor_pct"></span></div></div>
        <div class="cost-summary-row"><span style="color:#718096">🚚 运输差旅</span><div><span class="amount" id="s_transport">¥<?=number_format($transport_sum,2)?></span> <span style="font-size:12px;color:#a0aec0" id="s_transport_pct"></span></div></div>
        <div class="cost-summary-row"><span style="color:#718096">💰 其他杂费</span><div><span class="amount" id="s_misc">¥<?=number_format($misc_sum,2)?></span> <span style="font-size:12px;color:#a0aec0" id="s_misc_pct"></span></div></div>
        <div style="display:flex;justify-content:space-between;padding:12px 0 0;border-top:2px solid #e2e8f0;font-weight:700;font-size:15px">
          <span>总成本</span><span id="s_total">¥<?=number_format($total_cost,2)?></span>
        </div>
      </div>
      <div class="profit-box <?=$profit>=0?'profit-pos':'profit-neg'?>" id="profitBox">
        <div style="font-size:12px;color:#718096;margin-bottom:6px">送货收入 ¥<?=number_format($delivery_total,2)?> 减去总成本</div>
        <div style="font-size:22px;font-weight:700" id="profitVal" class="<?=$profit>=0?'positive':'negative'?>">¥<?=number_format($profit,2)?></div>
        <div style="font-size:13px;color:#718096;margin-top:2px" id="marginVal">毛利率 <?=number_format($margin,1)?>%</div>
        <div class="progress-wrap">
          <div class="progress-fill" id="costBar" style="width:<?=$cost_ratio?>%;background:<?=$cost_ratio>90?'#f56565':($cost_ratio>70?'#ed8936':'#1f6dad')?>"></div>
        </div>
        <div style="font-size:12px;color:#a0aec0;margin-top:4px">成本占合同 <?=number_format($cost_ratio,1)?>%</div>
      </div>
      <?php if($proj['notes']): ?>
      <div style="margin-top:16px;background:#f7fafc;border-radius:8px;padding:12px;font-size:13px;color:#4a5568;white-space:pre-wrap"><?=htmlspecialchars($proj['notes'])?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</div>

<div class="toast" id="toast"></div>

<script>
const PID=<?=$id?>, CONTRACT=<?=$contract?>;
let deliveryAmt=<?=$delivery_total?>, deliveryCnt=<?=$del_cnt?>;
let payableAmt=<?=$payable_total?>, payableCnt=<?=count($payable_rows)?>;
let manualByType={labor:<?=$labor_sum?>,transport:<?=$transport_sum?>,misc:<?=$misc_sum?>};
let manualCnt=<?=count($manual_rows)?>;


async function searchDeliveries(kw){
    const kwv=kw??document.getElementById('dkwInput').value.trim();
    const fd=new FormData();fd.append('action','search_deliveries');fd.append('kw',kwv);
    const r=await fetch('',{method:'POST',body:fd});const d=await r.json();
    renderDelResult(d.rows||[]);
}
function showAllDel(){searchDeliveries('');}
function renderDelResult(rows){
    const div=document.getElementById('delSearchResult');
    if(!rows.length){div.innerHTML='<div style="padding:20px;text-align:center;color:#a0aec0">未找到未关联的已审核送货单</div>';div.style.display='block';return;}
    let html='';
    rows.forEach(r=>{
        html+=`<div class="sr-item"><input type="checkbox" data-id="${r.id}" data-amt="${r.total_amount}" data-no="${esc(r.delivery_no)}" data-cust="${esc(r.customer_name||'')}" data-date="${r.delivery_date}" onchange="countDelSel()">
            <div style="flex:1"><div style="font-weight:500">${esc(r.delivery_no)} · ${esc(r.customer_name||'—')}</div><div style="color:#a0aec0;font-size:12px">${r.delivery_date}</div></div>
            <div style="text-align:right"><div style="font-weight:600;color:#1f6dad;font-family:monospace">¥${parseFloat(r.total_amount).toFixed(2)}</div><div style="font-size:12px;color:#68d391">已审核</div></div>
        </div>`;
    });
    div.innerHTML=html;div.style.display='block';
    document.getElementById('delLinkActions').style.display='flex';countDelSel();
}
function countDelSel(){document.getElementById('delSelCount').textContent=document.querySelectorAll('#delSearchResult input:checked').length;}
async function linkDeliveries(){
    const chks=[...document.querySelectorAll('#delSearchResult input[type=checkbox]:checked')];
    if(!chks.length){showToast('请先勾选送货单',false);return;}
    const ids=chks.map(c=>parseInt(c.dataset.id));
    const fd=new FormData();fd.append('action','link_delivery');fd.append('delivery_ids',JSON.stringify(ids));
    const r=await fetch('',{method:'POST',body:fd});const d=await r.json();
    if(d.ok){
        chks.forEach(c=>{
            addDeliveryRow(parseInt(c.dataset.id),c.dataset.no,c.dataset.cust,c.dataset.date,parseFloat(c.dataset.amt));
            deliveryAmt+=parseFloat(c.dataset.amt);deliveryCnt++;c.closest('.sr-item').remove();
        });
        updateSummary();showToast('✅ 已关联 '+d.affected+' 张送货单');
        document.getElementById('delSelCount').textContent='0';
    }
}
function addDeliveryRow(id,no,cust,date,amt){
    document.getElementById('deliveryEmpty')?.remove();
    const tr=document.createElement('tr');tr.id='dr_'+id;
    tr.innerHTML=`<td><code style="font-size:12px">${esc(no)}</code></td><td>${esc(cust)}</td><td style="color:#718096">${date}</td><td class="amount positive">¥${amt.toFixed(2)}</td><td></td><td><button class="btn-danger" onclick="unlinkDelivery(${id})">取消关联</button></td>`;
    document.getElementById('deliveryTbody').insertBefore(tr,document.getElementById('deliveryTbody').firstChild);
}
async function unlinkDelivery(did){
    if(!confirm('取消此送货单与项目的关联？'))return;
    const row=document.getElementById('dr_'+did);
    const amt=parseFloat(row.cells[3].textContent.replace('¥','').replace(/,/g,''))||0;
    const fd=new FormData();fd.append('action','unlink_delivery');fd.append('delivery_id',did);
    const r=await fetch('',{method:'POST',body:fd});const d=await r.json();
    if(d.ok){row.remove();deliveryAmt-=amt;deliveryCnt--;updateSummary();showToast('已取消关联');}
}
function showToast(msg,ok=true){
    const t=document.getElementById('toast');
    t.textContent=msg;t.style.background=ok?'#1f6dad':'#f56565';
    t.style.display='block';setTimeout(()=>t.style.display='none',2500);
}
function switchTab(name,btn){
    document.querySelectorAll('.tabs .tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.tab-content').forEach((el,i)=>el.classList.toggle('active',el.id==='tab-'+name));
}
function selCT(t,btn){
    document.getElementById('selCT').value=t;
    document.querySelectorAll('#ctypeTabs .tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const units={labor:'人/天',transport:'次',misc:'项'};
    document.getElementById('mc_unit').value=units[t]||'项';
}
function calcMC(){
    const q=parseFloat(document.getElementById('mc_qty').value)||0;
    const p=parseFloat(document.getElementById('mc_uprice').value)||0;
    document.getElementById('mc_amount').value=(q*p).toFixed(2);
}
async function searchPayables(kw){
    const kwv=kw??document.getElementById('kwInput').value.trim();
    const fd=new FormData();fd.append('action','search_payables');fd.append('kw',kwv);
    const r=await fetch('',{method:'POST',body:fd});
    const d=await r.json();
    renderSearchResult(d.rows||[]);
}
function showAll(){searchPayables('');}
function renderSearchResult(rows){
    const div=document.getElementById('searchResult');
    if(!rows.length){div.innerHTML='<div style="padding:20px;text-align:center;color:#a0aec0;font-size:14px">未找到未关联的账单</div>';div.style.display='block';return;}
    let html='';
    rows.forEach(r=>{
        const sup=r.sup_name||r.supplier_name||'—';
        const sc={已付款:'#68d391',未付款:'#fc8181',部分付款:'#f6ad55'};
        html+=`<div class="sr-item">
            <input type="checkbox" data-id="${r.id}" data-amt="${r.total_amount}" onchange="countSel()">
            <div style="flex:1">
                <div style="font-weight:500;color:#2d3748">${esc(r.bill_no)} · ${esc(sup)}</div>
                <div style="color:#a0aec0;font-size:12px;margin-top:2px">${r.bill_date}${r.category?' · '+esc(r.category):''}</div>
            </div>
            <div style="text-align:right">
                <div style="font-weight:600;font-family:monospace">¥${parseFloat(r.total_amount).toFixed(2)}</div>
                <div style="font-size:12px;color:${sc[r.status]||'#a0aec0'};font-weight:500">${esc(r.status)}</div>
            </div>
        </div>`;
    });
    div.innerHTML=html;div.style.display='block';
    document.getElementById('linkActions').style.display='flex';
    countSel();
}
function countSel(){
    document.getElementById('selCount').textContent=document.querySelectorAll('#searchResult input[type=checkbox]:checked').length;
}
async function linkSelected(){
    const chks=[...document.querySelectorAll('#searchResult input[type=checkbox]:checked')];
    if(!chks.length){showToast('请先勾选账单',false);return;}
    const ids=chks.map(c=>parseInt(c.dataset.id));
    const fd=new FormData();fd.append('action','link_payable');fd.append('payable_ids',JSON.stringify(ids));
    const r=await fetch('',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){
        chks.forEach(c=>{
            const item=c.closest('.sr-item');
            const amt=parseFloat(c.dataset.amt);
            const bill=item.querySelectorAll('div')[1].querySelector('div:first-child').textContent;
            const date=item.querySelectorAll('div')[1].querySelector('div:last-child').textContent.split('·')[0].trim();
            const status=item.querySelectorAll('div')[2].querySelector('div:last-child').textContent;
            addPayableRow(parseInt(c.dataset.id),bill,date,amt,status);
            payableAmt+=amt;payableCnt++;
            item.remove();
        });
        updateSummary();showToast(`✅ 已关联 ${d.affected} 笔账单`);
        document.getElementById('selCount').textContent='0';
    }
}
function addPayableRow(id,bill,date,amt,status){
    document.getElementById('payableEmpty')?.remove();
    const sc={已付款:'success',未付款:'danger',部分付款:'warning'};
    const tr=document.createElement('tr');tr.id='pr_'+id;
    const parts=bill.split('·');
    tr.innerHTML=`<td><code style="font-size:12px">${esc(parts[0]?.trim()||'')}</code></td>
        <td>${esc(parts[1]?.trim()||'—')}</td>
        <td style="color:#718096">${date}</td>
        <td class="amount">¥${parseFloat(amt).toFixed(2)}</td>
        <td class="amount positive">¥0.00</td>
        <td><span class="badge ${sc[status]||'info'}">${esc(status)}</span></td>
        <td></td>
        <td><button class="btn-danger" onclick="unlinkPayable(${id})">取消关联</button></td>`;
    document.getElementById('payableTbody').insertBefore(tr,document.getElementById('payableTbody').firstChild);
}
async function unlinkPayable(pid){
    if(!confirm('取消此账单与本项目的关联？'))return;
    const row=document.getElementById('pr_'+pid);
    const amt=parseFloat(row.cells[3].textContent.replace('¥','').replace(/,/g,''))||0;
    const fd=new FormData();fd.append('action','unlink_payable');fd.append('payable_id',pid);
    const r=await fetch('',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){row.remove();payableAmt-=amt;payableCnt--;updateSummary();showToast('已取消关联');}
}
async function submitManual(){
    const desc=document.getElementById('mc_desc').value.trim();
    if(!desc){showToast('请填写费用描述',false);return;}
    const ct=document.getElementById('selCT').value;
    const fd=new FormData();
    fd.append('action','add_cost');fd.append('cost_type',ct);
    fd.append('description',desc);fd.append('qty',document.getElementById('mc_qty').value);
    fd.append('unit',document.getElementById('mc_unit').value);
    fd.append('unit_price',document.getElementById('mc_uprice').value);
    fd.append('cost_date',document.getElementById('mc_date').value);
    fd.append('notes',document.getElementById('mc_notes').value);
    const r=await fetch('',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){
        manualByType[ct]=(manualByType[ct]||0)+parseFloat(d.amount);
        manualCnt++;
        addManualRow(d.id,ct,desc,document.getElementById('mc_qty').value,document.getElementById('mc_unit').value,document.getElementById('mc_uprice').value,d.amount,document.getElementById('mc_date').value);
        updateSummary();showToast('✅ 已添加');
        ['mc_desc','mc_notes'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('mc_qty').value='1';
        document.getElementById('mc_uprice').value='0';
        document.getElementById('mc_amount').value='0';
    }
}
const ctIcons={labor:'👷',transport:'🚚',misc:'💰'};
const ctLabels={labor:'人工工时',transport:'运输差旅',misc:'其他杂费'};
function addManualRow(id,ct,desc,qty,unit,uprice,amt,date){
    document.getElementById('manualEmpty')?.remove();
    const tr=document.createElement('tr');tr.id='mc_'+id;
    tr.innerHTML=`<td><span class="badge info">${ctIcons[ct]} ${ctLabels[ct]}</span></td>
        <td>${esc(desc)}</td><td>${parseFloat(qty).toFixed(2)} ${esc(unit)}</td>
        <td class="amount">¥${parseFloat(uprice).toFixed(2)}</td>
        <td class="amount negative">¥${parseFloat(amt).toFixed(2)}</td>
        <td style="color:#718096">${date}</td>
        <td><button class="btn-danger" onclick="delManual(${id},${amt},this)">删除</button></td>`;
    document.getElementById('manualTbody').insertBefore(tr,document.getElementById('manualTbody').firstChild);
}
async function delManual(cid,amt,btn){
    if(!confirm('确认删除？'))return;
    const row=document.getElementById('mc_'+cid);
    const fd=new FormData();fd.append('action','del_cost');fd.append('cost_id',cid);
    const r=await fetch('',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){
        const ctText=row.cells[0].textContent;
        for(const[k,v] of Object.entries(ctLabels)){if(ctText.includes(v)){manualByType[k]=(manualByType[k]||0)-parseFloat(amt);break;}}
        row.remove();manualCnt--;updateSummary();showToast('已删除');
    }
}
async function updateStatus(s){
    const fd=new FormData();fd.append('action','update_status');fd.append('status',s);
    await fetch('',{method:'POST',body:fd});
    const bc={planning:'warning',in_progress:'info',completed:'success',cancelled:''};
    const badge=document.querySelector('.status-sel').closest('.badge');
    badge.className='badge '+(bc[s]||'');
    badge.style.fontSize='13px';badge.style.padding='7px 16px';
    showToast('✅ 状态已更新');
}
function updateSummary(){
    const mt=Object.values(manualByType).reduce((a,b)=>a+b,0);
    const tc=payableAmt+mt;
    const profit=deliveryAmt-tc;
    const margin=deliveryAmt>0?profit/deliveryAmt*100:0;
    const ratio=deliveryAmt>0?Math.min(100,tc/deliveryAmt*100):0;
    setText('kpi_payable','¥'+fmt(payableAmt));
    setText('kpi_payable_cnt',payableCnt+'笔账单');
    setText('kpi_manual','¥'+fmt(mt));
    setText('kpi_manual_cnt',manualCnt+'条记录');
    setText('kpi_profit','¥'+fmt(profit));
    document.getElementById('kpi_profit').className='stat-box-value '+(profit>=0?'positive':'negative');
    setText('kpi_margin',margin.toFixed(1)+'% 毛利率');
    setText('s_payable','¥'+fmt(payableAmt));
    setText('s_labor','¥'+fmt(manualByType.labor||0));
    setText('s_transport','¥'+fmt(manualByType.transport||0));
    setText('s_misc','¥'+fmt(manualByType.misc||0));
    setText('s_total','¥'+fmt(tc));
    if(tc>0){
        setText('s_payable_pct',(payableAmt/tc*100).toFixed(1)+'%');
        setText('s_labor_pct',((manualByType.labor||0)/tc*100).toFixed(1)+'%');
        setText('s_transport_pct',((manualByType.transport||0)/tc*100).toFixed(1)+'%');
        setText('s_misc_pct',((manualByType.misc||0)/tc*100).toFixed(1)+'%');
    }
    setText('profitVal','¥'+fmt(profit));
    document.getElementById('profitVal').className=profit>=0?'positive':'negative';
    setText('marginVal','毛利率 '+margin.toFixed(1)+'%');
    const bar=document.getElementById('costBar');
    bar.style.width=ratio+'%';
    bar.style.background=ratio>90?'#f56565':ratio>70?'#ed8936':'#1f6dad';
    const pb=document.getElementById('profitBox');
    pb.className='profit-box '+(profit>=0?'profit-pos':'profit-neg');
    setText('delivery_list_hd',deliveryCnt+'张 · 合计 ¥'+fmt(deliveryAmt));
    setText('payable_list_hd',payableCnt+'笔 · 合计 ¥'+fmt(payableAmt));
    setText('manual_list_hd',manualCnt+'条 · 合计 ¥'+fmt(mt));
    drawPie(payableAmt,manualByType.labor||0,manualByType.transport||0,manualByType.misc||0);
}
function drawPie(p,l,t,m){
    const svg=document.getElementById('pie');svg.innerHTML='';
    const segs=[{v:p,c:'#3b82f6'},{v:l,c:'#f59e0b'},{v:t,c:'#10b981'},{v:m,c:'#9ca3af'}];
    const total=segs.reduce((a,s)=>a+s.v,0);
    if(total<=0){const tx=mkSVG('text');tx.setAttribute('x','100');tx.setAttribute('y','108');tx.setAttribute('text-anchor','middle');tx.setAttribute('fill','#a0aec0');tx.setAttribute('font-size','13');tx.textContent='暂无成本数据';svg.appendChild(tx);return;}
    let angle=-Math.PI/2;
    segs.forEach(s=>{
        if(s.v<=0)return;
        const a=s.v/total*2*Math.PI,ea=angle+a;
        const x1=100+80*Math.cos(angle),y1=100+80*Math.sin(angle);
        const x2=100+80*Math.cos(ea),y2=100+80*Math.sin(ea);
        const path=mkSVG('path');
        path.setAttribute('d',`M100,100 L${x1},${y1} A80,80 0 ${a>Math.PI?1:0},1 ${x2},${y2} Z`);
        path.setAttribute('fill',s.c);path.setAttribute('stroke','#fff');path.setAttribute('stroke-width','3');
        svg.appendChild(path);angle=ea;
    });
    const ci=mkSVG('circle');ci.setAttribute('cx','100');ci.setAttribute('cy','100');ci.setAttribute('r','48');ci.setAttribute('fill','white');svg.appendChild(ci);
    const lb=mkSVG('text');lb.setAttribute('x','100');lb.setAttribute('y','97');lb.setAttribute('text-anchor','middle');lb.setAttribute('fill','#718096');lb.setAttribute('font-size','11');lb.textContent='总成本';svg.appendChild(lb);
    const vl=mkSVG('text');vl.setAttribute('x','100');vl.setAttribute('y','114');vl.setAttribute('text-anchor','middle');vl.setAttribute('fill','#1a202c');vl.setAttribute('font-size','13');vl.setAttribute('font-weight','700');vl.textContent=total>=10000?(total/10000).toFixed(1)+'万':total.toFixed(0);svg.appendChild(vl);
}
function mkSVG(tag){return document.createElementNS('http://www.w3.org/2000/svg',tag);}
function setText(id,v){const el=document.getElementById(id);if(el)el.textContent=v;}
function fmt(n){return parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
drawPie(<?=$payable_total?>,<?=$labor_sum?>,<?=$transport_sum?>,<?=$misc_sum?>);
</script>
</body></html>
