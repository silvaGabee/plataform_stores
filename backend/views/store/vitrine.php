<?php
ob_start();
$bannerPath = trim((string) ($store['banner_path'] ?? ''));
$bannerUrl = $bannerPath !== '' ? base_url('uploads/' . str_replace('\\', '/', $bannerPath)) : null;
$active_category_id = 0;
$dynamic_view = '_dynamic_home';
require __DIR__ . '/_vitrine_page_wrap.php';
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/h-scroll-mask.js') . '"></script><script src="' . asset('js/store-vitrine-nav.js') . '"></script>';
require __DIR__ . '/layout_store.php';
