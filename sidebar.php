<?php
// 自动推断当前页对应的高亮菜单项（前缀匹配，长前缀优先）
$_sb_file = basename($_SERVER['PHP_SELF']);
$_sb_role = $_SESSION['role'] ?? '';
$_sb_is_admin = in_array($_sb_role, ['admin', 'super_admin']);
$_sb_is_super = ($_sb_role === 'super_admin');

$_sb_prefix_map = [
    'feedback'        => 'feedback.php',
    'system_log'      => 'system_logs.php',
    'project'         => 'project_list.php',
    'quote'           => 'quotes.php',
    'solution'        => 'solutions.php',
    'contract'        => 'contracts.php',
    'deliver'         => 'deliveries.php',
    'sales_report'    => 'deliveries.php',
    'repair'          => 'repair.php',
    'rma'             => 'repair.php',
    'technician'      => 'repair.php',
    'recycle'         => 'recycle.php',
    'receipt'         => 'finance.php',
    'payment'         => 'finance.php',
    'customer'        => 'customers.php',
    'supplier'        => 'suppliers.php',
    'product'         => 'products.php',
    'rental'          => 'products.php',
    'sn_'             => 'products.php',
    'stock'           => 'products.php',
    'inventory'       => 'products.php',
    'seal'            => 'seals.php',
    'user'            => 'users.php',
    'sysadmin'        => 'sysadmin.php',
    'settings'        => 'settings.php',
    'index'           => 'index.php',
    'finance'         => 'finance.php',
];

$_sb_active = $_sb_file;
foreach ($_sb_prefix_map as $prefix => $target) {
    if (strpos($_sb_file, $prefix) === 0) {
        $_sb_active = $target;
        break;
    }
}

if (!function_exists('_sb_cls')) {
    function _sb_cls(string $href): string {
        global $_sb_active;
        return 'menu-item' . ($href === $_sb_active ? ' active' : '');
    }
}
?>
<aside class="sidebar">
    <div class="menu-section">
        <div class="menu-title">主要功能</div>
        <a href="index.php"         class="<?=_sb_cls('index.php')?>"><span class="menu-item-icon">🏠</span><span>工作台</span></a>
        <a href="quotes.php"        class="<?=_sb_cls('quotes.php')?>"><span class="menu-item-icon">📋</span><span>报价管理</span></a>
        <a href="solutions.php"     class="<?=_sb_cls('solutions.php')?>"><span class="menu-item-icon">📐</span><span>方案管理</span></a>
        <a href="contracts.php"     class="<?=_sb_cls('contracts.php')?>"><span class="menu-item-icon">📝</span><span>合同管理</span></a>
        <a href="deliveries.php"    class="<?=_sb_cls('deliveries.php')?>"><span class="menu-item-icon">🚚</span><span>送货管理</span></a>
        <a href="repair.php"        class="<?=_sb_cls('repair.php')?>"><span class="menu-item-icon">🔧</span><span>维修管理</span></a>
        <a href="recycle.php"       class="<?=_sb_cls('recycle.php')?>"><span class="menu-item-icon">♻️</span><span>电脑回收</span></a>
        <a href="finance.php"       class="<?=_sb_cls('finance.php')?>"><span class="menu-item-icon">💰</span><span>财务管理</span></a>
        <a href="project_list.php"  class="<?=_sb_cls('project_list.php')?>"><span class="menu-item-icon">📊</span><span>项目成本</span></a>
    </div>
    <div class="menu-section">
        <div class="menu-title">基础数据</div>
        <a href="customers.php" class="<?=_sb_cls('customers.php')?>"><span class="menu-item-icon">🏢</span><span>客户管理</span></a>
        <a href="suppliers.php" class="<?=_sb_cls('suppliers.php')?>"><span class="menu-item-icon">🚚</span><span>供应商管理</span></a>
        <a href="products.php"  class="<?=_sb_cls('products.php')?>"><span class="menu-item-icon">📦</span><span>产品管理</span></a>
    </div>
    <div class="menu-section">
        <div class="menu-title">公司设置</div>
        <a href="settings.php" class="<?=_sb_cls('settings.php')?>"><span class="menu-item-icon">⚙️</span><span>公司设置</span></a>
        <a href="seals.php"    class="<?=_sb_cls('seals.php')?>"><span class="menu-item-icon">🔏</span><span>公章管理</span></a>
        <a href="users.php"    class="<?=_sb_cls('users.php')?>"><span class="menu-item-icon">👥</span><span>用户管理</span></a>
        <?php if ($_sb_is_super): ?>
        <div class="menu-title" style="margin-top:8px">系统管理</div>
        <a href="sysadmin.php"    class="<?=_sb_cls('sysadmin.php')?>"><span class="menu-item-icon">🛡️</span><span>系统管理</span></a>
        <a href="system_logs.php" class="<?=_sb_cls('system_logs.php')?>"><span class="menu-item-icon">📋</span><span>操作日志</span></a>
        <?php endif; ?>
        <div class="menu-title" style="margin-top:8px">帮助与支持</div>
        <a href="feedback.php" class="<?=_sb_cls('feedback.php')?>"><span class="menu-item-icon">💬</span><span>问题反馈</span></a>
    </div>
</aside>
