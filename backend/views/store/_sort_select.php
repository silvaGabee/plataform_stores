<label class="ui-sort">
    <span>Ordenar por</span>
    <select class="js-store-sort" aria-label="Ordenar produtos">
        <?php $__ordem = storefront_current_sort(); foreach (storefront_sort_options() as $__k => $__l): ?>
            <option value="<?= $__k ?>"<?= $__k === $__ordem ? ' selected' : '' ?>><?= htmlspecialchars($__l) ?></option>
        <?php endforeach; ?>
    </select>
</label>
