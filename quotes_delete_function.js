// =====================================================
// 在 quotes.php 文件中，找到 deleteQuote 函数并替换为以下代码：
// =====================================================

/**
 * 删除报价单（使用AJAX）
 */
async function deleteQuote(id) {
    if (!confirm('确定要删除这个报价单吗？\n\n报价单将被标记为"已作废"状态，不会真正删除数据。')) {
        return;
    }
    
    try {
        const response = await fetch('quote_delete.php?id=' + id + '&ajax=1', {
            method: 'GET'
        });
        
        if (!response.ok) {
            throw new Error('网络请求失败');
        }
        
        const result = await response.json();
        
        if (result.success) {
            alert('✓ ' + result.message);
            window.location.reload(); // 刷新页面
        } else {
            alert('✗ 删除失败：' + result.message);
        }
    } catch (error) {
        console.error('删除错误:', error);
        alert('✗ 删除出错，请重试');
    }
}
