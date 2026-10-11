<link rel="manifest" href="/erp/manifest.json">
<meta name="theme-color" content="#1f6dad">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="电脑装机工程智能报价系统">
<link rel="apple-touch-icon" href="/erp/logo.png">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/erp/sw.js')
            .then(function(reg) { console.log('SW registered:', reg.scope); })
            .catch(function(err) { console.log('SW error:', err); });
    });
}
</script>
