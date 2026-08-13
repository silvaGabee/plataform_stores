<?php
$__configIc = static function (string $d): string {
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '"/></svg>';
};
$content = ob_start();
?>
<div class="panel-content panel-config-page">
    <header class="panel-page-header">
        <h1>Configurações</h1>
        <p class="panel-lead text-muted">Preferências e ações sensíveis da loja <strong id="config-page-store-name"><?= htmlspecialchars($store['name']) ?></strong>.</p>
    </header>

    <div class="panel-config-stack">
        <section id="config-store-name-section" class="panel-section-card panel-config-card" aria-labelledby="config-store-name-title">
            <div class="panel-section-head">
                <span class="panel-section-icon" aria-hidden="true"><?= $__configIc('M12 20h9 M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z') ?></span>
                <div class="panel-section-head-text">
                    <h2 id="config-store-name-title" class="panel-section-title">Nome da loja</h2>
                    <p class="panel-section-desc">Nome exibido na vitrine, no painel e na lista de lojas. O endereço <strong>/loja/<?= htmlspecialchars($store['slug']) ?></strong> não muda.</p>
                </div>
            </div>
            <div class="panel-section-body panel-config-card-body">
                <form id="config-store-name-form" class="panel-config-form">
                    <div class="panel-config-field">
                        <label for="config-store-name-input">Nome</label>
                        <input type="text" id="config-store-name-input" name="name" value="<?= htmlspecialchars($store['name']) ?>" maxlength="120" required autocomplete="organization" placeholder="Ex.: Minha Loja">
                    </div>
                    <div class="panel-config-actions">
                        <button type="submit" class="btn btn-primary btn-sm" id="config-store-name-save">Alterar nome</button>
                    </div>
                </form>
                <p id="config-store-name-msg" class="panel-form-msg panel-config-feedback" role="status" aria-live="polite"></p>
            </div>
        </section>

        <section id="config-store-slogan-section" class="panel-section-card panel-config-card" aria-labelledby="config-store-slogan-title">
            <div class="panel-section-head">
                <span class="panel-section-icon" aria-hidden="true"><?= $__configIc('M4 6h16 M4 12h10 M4 18h14') ?></span>
                <div class="panel-section-head-text">
                    <h2 id="config-store-slogan-title" class="panel-section-title">Slogan</h2>
                    <p class="panel-section-desc">Frase curta abaixo do nome no cabeçalho da vitrine. Deixe vazio para não mostrar.</p>
                </div>
            </div>
            <div class="panel-section-body panel-config-card-body">
                <form id="config-store-slogan-form" class="panel-config-form">
                    <div class="panel-config-field">
                        <label for="config-store-slogan-input">Slogan</label>
                        <input type="text" id="config-store-slogan-input" name="slogan" value="<?= htmlspecialchars(trim((string) ($store['slogan'] ?? ''))) ?>" maxlength="160" autocomplete="off" placeholder="Ex.: Treine forte. Vista elite.">
                    </div>
                    <div class="panel-config-actions">
                        <button type="submit" class="btn btn-primary btn-sm" id="config-store-slogan-save">Alterar slogan</button>
                    </div>
                </form>
                <p id="config-store-slogan-msg" class="panel-form-msg panel-config-feedback" role="status" aria-live="polite"></p>
            </div>
        </section>

        <section id="config-store-background-section" class="panel-section-card panel-config-card" aria-labelledby="config-store-background-title">
            <div class="panel-section-head">
                <span class="panel-section-icon" aria-hidden="true"><?= $__configIc('M12 3v18 M3 12h18') ?></span>
                <div class="panel-section-head-text">
                    <h2 id="config-store-background-title" class="panel-section-title">Cor de fundo da vitrine</h2>
                    <p class="panel-section-desc">Cor de fundo que será aplicada na vitrine pública da loja.</p>
                </div>
            </div>
            <div class="panel-section-body panel-config-card-body">
                <form id="config-store-background-form" class="panel-config-form">
                    <div class="panel-config-field panel-config-field--narrow">
                        <label for="config-store-background-color-input">Cor</label>
                        <div class="panel-color-chooser" data-target="config-store-background-color-input" data-large-grid="true">
                            <input type="hidden" id="config-store-background-color-input" name="background_color" value="<?= htmlspecialchars($store['background_color'] ?? '#ffffff') ?>">
                            <button type="button" id="config-store-background-color-trigger" class="panel-color-trigger" aria-label="Selecionar cor de fundo" style="background: <?= htmlspecialchars($store['background_color'] ?? '#ffffff') ?>"></button>
                            <div class="panel-color-palette" aria-hidden="true">
                                <!-- small defaults (kept for quick picks) -->
                                <button type="button" class="panel-color-swatch" data-color="#ffffff" style="background:#ffffff"></button>
                                <button type="button" class="panel-color-swatch" data-color="#000000" style="background:#000000"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ff385c" style="background:#ff385c"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ff8a00" style="background:#ff8a00"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ffd400" style="background:#ffd400"></button>
                                <button type="button" class="panel-color-swatch" data-color="#06b6d4" style="background:#06b6d4"></button>
                            </div>
                                <div class="panel-color-advanced hidden" aria-hidden="true">
                                <input type="text" class="panel-color-hex-input" placeholder="#RRGGBB">
                                <div class="panel-color-rgb">
                                    <label>R <input type="range" min="0" max="255" class="panel-color-r panel-color-range"></label>
                                    <label>G <input type="range" min="0" max="255" class="panel-color-g panel-color-range"></label>
                                    <label>B <input type="range" min="0" max="255" class="panel-color-b panel-color-range"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-config-actions">
                        <button type="submit" class="btn btn-primary btn-sm" id="config-store-background-save">Alterar cor</button>
                    </div>
                </form>
                <p id="config-store-background-msg" class="panel-form-msg panel-config-feedback" role="status" aria-live="polite"></p>
            </div>
        </section>

        <section id="config-store-appearance-section" class="panel-section-card panel-config-card" aria-labelledby="config-store-appearance-title">
            <div class="panel-section-head">
                <span class="panel-section-icon" aria-hidden="true"><?= $__configIc('M12 3v18 M3 12h18') ?></span>
                <div class="panel-section-head-text">
                    <h2 id="config-store-appearance-title" class="panel-section-title">Aparência da vitrine</h2>
                    <p class="panel-section-desc">Personalize a cor de fundo das categorias e o fundo do banner (se houver).</p>
                </div>
            </div>
            <div class="panel-section-body panel-config-card-body">
                <form id="config-store-appearance-form" class="panel-config-form">
                    <div class="panel-config-field panel-config-field--narrow">
                        <label for="config-store-categories-color-input">Cor das categorias</label>
                        <div class="panel-color-chooser" data-target="config-store-categories-color-input" data-large-grid="true">
                            <input type="hidden" id="config-store-categories-color-input" name="categories_background_color" value="<?= htmlspecialchars(trim((string) ($appearanceInitial['categories_background_color'] ?? ''))) ?>">
                            <button type="button" id="config-store-categories-color-trigger" class="panel-color-trigger" aria-label="Selecionar cor das categorias" style="background: <?= htmlspecialchars(trim((string) ($appearanceInitial['categories_background_color'] ?? '#ffffff'))); ?>"></button>
                            <div class="panel-color-palette" aria-hidden="true">
                                <button type="button" class="panel-color-swatch" data-color="#ffffff" style="background:#ffffff"></button>
                                <button type="button" class="panel-color-swatch" data-color="#000000" style="background:#000000"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ff385c" style="background:#ff385c"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ff8a00" style="background:#ff8a00"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ffd400" style="background:#ffd400"></button>
                                <button type="button" class="panel-color-swatch" data-color="#06b6d4" style="background:#06b6d4"></button>
                                <button type="button" class="panel-color-swatch" data-color="#10b981" style="background:#10b981"></button>
                                <button type="button" class="panel-color-swatch" data-color="#7c3aed" style="background:#7c3aed"></button>
                            </div>
                            <div class="panel-color-advanced hidden" aria-hidden="true">
                                <input type="text" class="panel-color-hex-input" placeholder="#RRGGBB">
                                <div class="panel-color-rgb">
                                    <label>R <input type="range" min="0" max="255" class="panel-color-r panel-color-range"></label>
                                    <label>G <input type="range" min="0" max="255" class="panel-color-g panel-color-range"></label>
                                    <label>B <input type="range" min="0" max="255" class="panel-color-b panel-color-range"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-config-field panel-config-field--narrow">
                        <label for="config-store-banner-bg-color-input">Cor do fundo do banner</label>
                        <div class="panel-color-chooser" data-target="config-store-banner-bg-color-input" data-large-grid="true">
                            <input type="hidden" id="config-store-banner-bg-color-input" name="banner_background_color" value="<?= htmlspecialchars(trim((string) ($appearanceInitial['banner_background_color'] ?? ''))) ?>">
                            <button type="button" id="config-store-banner-bg-color-trigger" class="panel-color-trigger" aria-label="Selecionar cor do fundo do banner" style="background: <?= htmlspecialchars(trim((string) ($appearanceInitial['banner_background_color'] ?? '#ffffff'))); ?>"></button>
                            <div class="panel-color-palette" aria-hidden="true">
                                <button type="button" class="panel-color-swatch" data-color="#ffffff" style="background:#ffffff"></button>
                                <button type="button" class="panel-color-swatch" data-color="#000000" style="background:#000000"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ff385c" style="background:#ff385c"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ff8a00" style="background:#ff8a00"></button>
                                <button type="button" class="panel-color-swatch" data-color="#ffd400" style="background:#ffd400"></button>
                                <button type="button" class="panel-color-swatch" data-color="#06b6d4" style="background:#06b6d4"></button>
                                <button type="button" class="panel-color-swatch" data-color="#10b981" style="background:#10b981"></button>
                                <button type="button" class="panel-color-swatch" data-color="#7c3aed" style="background:#7c3aed"></button>
                            </div>
                            <div class="panel-color-advanced hidden" aria-hidden="true">
                                <input type="text" class="panel-color-hex-input" placeholder="#RRGGBB">
                                <div class="panel-color-rgb">
                                    <label>R <input type="range" min="0" max="255" class="panel-color-r panel-color-range"></label>
                                    <label>G <input type="range" min="0" max="255" class="panel-color-g panel-color-range"></label>
                                    <label>B <input type="range" min="0" max="255" class="panel-color-b panel-color-range"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-config-actions">
                        <button type="submit" class="btn btn-primary btn-sm" id="config-store-appearance-save">Alterar aparência</button>
                    </div>
                </form>
                <p id="config-store-appearance-msg" class="panel-form-msg panel-config-feedback" role="status" aria-live="polite"></p>
            </div>
        </section>

        <section id="config-store-photo-section" class="panel-section-card panel-config-card" aria-labelledby="config-store-photo-title">
            <div class="panel-section-head">
                <span class="panel-section-icon" aria-hidden="true"><?= $__configIc('M4 5h16v14H4z M9 10a1.5 1.5 0 100-3 1.5 1.5 0 000 3z M20 15l-4-4-3 3-2-2-5 5') ?></span>
                <div class="panel-section-head-text">
                    <h2 id="config-store-photo-title" class="panel-section-title">Foto da loja</h2>
                    <p class="panel-section-desc">Ícone na aba do navegador, cabeçalho da vitrine e painel. Use imagem <strong>quadrada</strong> (logótipo ou ícone).</p>
                </div>
            </div>
            <div class="panel-section-body panel-config-card-body">
                <div class="panel-config-preview-block">
                    <p class="panel-config-preview-label">Pré-visualização</p>
                    <div id="config-store-photo-stage" class="panel-config-store-photo-stage">
                        <?php // Sem atributo src: com src="" o navegador requisita a própria página como imagem. O JS preenche ao escolher o arquivo. ?>
                        <img id="config-store-photo-preview" alt="" class="panel-config-store-photo-preview hidden" width="112" height="112" decoding="async">
                        <div id="config-store-photo-fallback" class="panel-config-store-photo-fallback" aria-hidden="true">
                            <span class="panel-config-store-photo-fallback-icon">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.35" opacity="0.4"/><circle cx="9" cy="10" r="1.5" fill="currentColor" opacity="0.45"/><path d="M20 15l-4-4-3 3-2-2-5 5" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" opacity="0.4"/></svg>
                            </span>
                            <span class="panel-config-store-photo-fallback-text">Sem foto</span>
                        </div>
                    </div>
                </div>
                <ul class="panel-config-hints" aria-label="Recomendações">
                    <li>JPG, PNG, WebP ou ICO</li>
                    <li>Quadrado, pelo menos 48×48 px</li>
                </ul>
                <form id="config-store-photo-form" class="panel-config-form">
                    <div class="panel-config-field">
                        <span class="panel-config-field-label">Ficheiro</span>
                        <div class="panel-config-file-drop">
                            <input type="file" id="config-store-photo-file" name="store_icon" class="panel-config-file-input" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp,.ico" title="Escolher imagem">
                            <label for="config-store-photo-file" class="btn btn-secondary btn-sm panel-config-file-browse">
                                <span class="panel-config-file-browse-icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </span>
                                Escolher imagem
                            </label>
                            <span id="config-store-photo-filename" class="panel-config-file-name">Nenhum ficheiro novo</span>
                        </div>
                    </div>
                    <div class="panel-config-actions">
                        <button type="submit" class="btn btn-primary btn-sm" id="config-store-photo-save">Alterar foto</button>
                        <button type="button" id="config-store-photo-remove" class="btn btn-secondary btn-sm hidden">Remover</button>
                    </div>
                </form>
                <p id="config-store-photo-msg" class="panel-form-msg panel-config-feedback" role="status" aria-live="polite"></p>
            </div>
        </section>

        <section class="panel-section-card panel-config-card panel-config-card--danger" aria-labelledby="config-store-delete-title">
            <div class="panel-section-head">
                <span class="panel-section-icon panel-section-icon--danger" aria-hidden="true"><?= $__configIc('M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v6 M14 11v6') ?></span>
                <div class="panel-section-head-text">
                    <h2 id="config-store-delete-title" class="panel-section-title">Excluir loja</h2>
                    <p class="panel-section-desc">Ação <strong>irreversível</strong>: apaga produtos, pedidos, clientes, funcionários e todas as configurações.</p>
                </div>
            </div>
            <div class="panel-section-body panel-config-card-body">
                <div class="panel-config-actions">
                    <button type="button" id="btn-show-delete-store" class="btn btn-danger btn-sm">Excluir loja</button>
                </div>
                <div id="store-delete-confirm" class="panel-config-form--split panel-config-delete-confirm hidden">
                    <p class="panel-config-delete-prompt">
                        Digite <strong>Excluir</strong> no campo abaixo para confirmar.
                    </p>
                    <div class="panel-config-field panel-config-field--narrow">
                        <label for="store-delete-confirmation-input">Confirmação</label>
                        <input type="text" id="store-delete-confirmation-input" autocomplete="off" placeholder="Excluir" spellcheck="false">
                    </div>
                    <div class="panel-config-actions">
                        <button type="button" id="btn-confirm-delete-store" class="btn btn-danger btn-sm">Confirmar exclusão</button>
                        <button type="button" id="btn-cancel-delete-store" class="btn btn-secondary btn-sm">Cancelar</button>
                    </div>
                    <p id="store-delete-msg" class="panel-form-msg panel-config-feedback" role="status" aria-live="polite"></p>
                </div>
            </div>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
// load existing appearance settings (categories/banner) from dashboard config
$appearanceInitial = [];
try {
    $cfgRepo = new \App\Repositories\StoreDashboardConfigRepository();
    $appearanceInitial = $cfgRepo->getConfig((int) ($store['id'] ?? 0))['appearance'] ?? [];
} catch (\Throwable $e) {
    $appearanceInitial = [];
}

$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . '; const storeNameInitial = ' . json_encode($store['name']) . '; const storeSloganInitial = ' . json_encode(trim((string) ($store['slogan'] ?? ''))) . '; const storeBackgroundColorInitial = ' . json_encode(trim((string) ($store['background_color'] ?? ''))) . '; const storeAppearanceInitial = ' . json_encode($appearanceInitial) . ';</script><script src="' . asset('js/panel-configuracoes.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
