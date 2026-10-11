<?php
/**
 * pc_config_list.php — PC端常用配置模板列表
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$conn = getDBConnection();

$templates = $conn->query("
    SELECT t.id, t.category, t.level, t.name, t.description,
           GROUP_CONCAT(i.category_name ORDER BY i.sort_order SEPARATOR '|') AS cats,
           GROUP_CONCAT(i.item_name     ORDER BY i.sort_order SEPARATOR '|') AS items,
           GROUP_CONCAT(i.spec          ORDER BY i.sort_order SEPARATOR '|') AS specs,
           GROUP_CONCAT(i.cat_id        ORDER BY i.sort_order SEPARATOR '|') AS cat_ids,
           GROUP_CONCAT(i.unit          ORDER BY i.sort_order SEPARATOR '|') AS units,
           GROUP_CONCAT(IFNULL(i.quantity,1) ORDER BY i.sort_order SEPARATOR '|') AS quantities,
           COUNT(i.id) AS item_count
    FROM pc_config_templates t
    LEFT JOIN pc_config_template_items i ON i.template_id=t.id
    WHERE t.is_active=1
    GROUP BY t.id ORDER BY t.sort_order
")->fetch_all(MYSQLI_ASSOC);
$conn->close();

$groups = [];
foreach ($templates as $t) $groups[$t['category']][] = $t;

$catIcon  = ['三维设计'=>'🎨','视频编辑'=>'🎬','普通办公'=>'💼','电商跟单'=>'🛒','游戏'=>'🎮','AI大模型'=>'🤖','服务器'=>'🖥️'];
$levelColor = ['高配'=>'#cf1322','中配'=>'#d46b08','低配'=>'#389e0d'];
$levelBg    = ['高配'=>'#fff1f0','中配'=>'#fff7e6','低配'=>'#f6ffed'];
function e($v){return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
?>
<?php $page_title = '常用配置模板'; include __DIR__ . '/head.php'; ?>
<style>
.
.page-desc{font-size:14px;color:#718096;margin-bottom:24px;}
.tip-box{background:#fff7e6;border:1px solid #ffd591;border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:14px;color:#874d00;}
.cat-section{margin-bottom:32px;}
.cat-header{display:flex;align-items:center;gap:8px;font-size:18px;font-weight:700;color:#1a202c;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;}
.tpl-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
@media(max-width:1200px){.tpl-grid{grid-template-columns:repeat(2,1fr);}}
.tpl-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:2px solid transparent;cursor:pointer;transition:all .2s;}
.tpl-card:hover{border-color:#1f6dad;box-shadow:0 4px 16px rgba(31,109,173,.15);transform:translateY(-2px);}
.tpl-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.tpl-name{font-size:16px;font-weight:700;color:#1a202c;}
.tpl-level{font-size:12px;padding:3px 10px;border-radius:12px;font-weight:600;}
.tpl-desc{font-size:13px;color:#718096;margin-bottom:12px;line-height:1.5;}
.tpl-parts{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;}
.part-tag{font-size:12px;padding:3px 8px;border-radius:4px;background:#f7fafc;color:#4a5568;border:1px solid #e2e8f0;}
.part-tag.key{background:#ebf8ff;color:#1f6dad;border-color:#bee3f8;}
.tpl-footer{display:flex;justify-content:space-between;align-items:center;}
.tpl-count{font-size:12px;color:#a0aec0;}
.btn-use{padding:8px 20px;background:linear-gradient(135deg,#1f6dad,#135286);color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;transition:all .2s;}
.btn-use:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
</style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">⚡ 常用配置模板</div>
    <a href="quotes.php" class="btn btn-secondary">← 返回报价单</a>
</nav>

<main class="main">
    <div class="page-title">常用装机配置模板</div>
    <div class="page-desc">选择模板后自动生成报价单配件清单，从产品库选择具体型号，价格自动带出</div>
    <div class="tip-box">
        💡 <strong>使用说明：</strong>点击「使用此配置」→ 跳转新建报价单 → 配件行已预填 → 
        每行从产品库下拉选具体型号 → 价格自动带入 → 保存即可
    </div>

    <?php foreach($groups as $cat => $tpls): ?>
    <div class="cat-section">
        <div class="cat-header">
            <?=$catIcon[$cat]??'📦'?> <?=e($cat)?>
            <span style="font-size:13px;color:#a0aec0;font-weight:400;"><?=count($tpls)?>套配置</span>
        </div>
        <div class="tpl-grid">
        <?php foreach($tpls as $tpl):
            $lc = $levelColor[$tpl['level']]??'#1f6dad';
            $lb = $levelBg[$tpl['level']]??'#ebf8ff';
            $catNames = array_filter(explode('|', $tpl['cats']??''));
            $specs    = explode('|', $tpl['specs']??'');
            $items    = explode('|', $tpl['items']??'');
            $keyParts = ['CPU处理器','显卡','内存','SSD硬盘'];
        ?>
        <div class="tpl-card" onclick="useTemplate(<?=$tpl['id']?>, '<?=e($tpl['name'])?>')">
            <div class="tpl-header">
                <div class="tpl-name"><?=e($tpl['name'])?></div>
                <div class="tpl-level" style="background:<?=$lb?>;color:<?=$lc?>;"><?=e($tpl['level'])?></div>
            </div>
            <div class="tpl-desc"><?=e($tpl['description']??'')?></div>
            <div class="tpl-parts">
                <?php foreach($catNames as $j=>$cn): if(!$cn) continue;
                    $isKey = in_array($cn, $keyParts);
                    $spec  = trim($specs[$j]??'');
                    $item  = trim($items[$j]??'');
                ?>
                <span class="part-tag <?=$isKey?'key':''?>">
                    <?=e($cn)?>
                    <?php if($isKey && $spec): ?>
                    <span style="color:#718096;"> · <?=e(mb_substr($spec,0,10))?></span>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="tpl-footer">
                <span class="tpl-count"><?=$tpl['item_count']?>项配件</span>
                <button class="btn-use" onclick="event.stopPropagation();useTemplate(<?=$tpl['id']?>, '<?=e($tpl['name'])?>')">
                    使用此配置 →
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</main>

<script>
function useTemplate(id, name) {
    window.location.href = 'quote_create.php?tpl_id=' + id + '&tpl_name=' + encodeURIComponent(name);
}
</script>
</body>
</html>
