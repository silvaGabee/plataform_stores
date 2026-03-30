<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Clientes</h1>
    <p>Clientes que compraram na loja, com quantidade de produtos e valor gasto.</p>
    <div id="customers-list"></div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-clientes.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
