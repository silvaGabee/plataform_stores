<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Estoque</h1>
    <p>Produtos com estoque baixo: <span id="low-stock-count">-</span></p>
    <div id="stock-list"></div>
    <div id="adjust-modal" class="modal hidden">
        <div class="modal-content">
            <h2>Ajustar estoque</h2>
            <form id="adjust-form">
                <input type="hidden" id="adjust-product-id">
                <p id="adjust-product-name"></p>
                <label>Tipo</label>
                <select id="adjust-type">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                    <option value="ajuste">Ajuste</option>
                </select>
                <label>Quantidade *</label>
                <input type="number" id="adjust-qty" min="1" required>
                <label>Motivo</label>
                <input type="text" id="adjust-reason">
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Aplicar</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                    <button type="button" class="btn btn-secondary" id="btn-delete-product-from-stock" style="background:#dc2626;color:#fff">Excluir produto</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script>window.panelStockReadonly = false;</script><script src="' . asset('js/panel-stock.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
