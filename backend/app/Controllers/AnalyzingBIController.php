<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;

/**
 * Página do painel Analyzing BI (apenas gerente; multi-loja por slug).
 */
class AnalyzingBIController extends Controller
{
    public function panel(string $slug): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $store = (new StoreRepository())->findBySlug($slug);
        if (!$store) {
            http_response_code(404);
            echo 'Loja não encontrada';
            exit;
        }
        if (!can_access_store_panel((int) $store['id'])) {
            redirect(base_url('loja/' . $slug));
        }
        if (is_funcionario_panel_readonly((int) $store['id'])) {
            redirect(base_url('painel/' . $slug));
        }
        $data = [
            'store' => $store,
            'title' => 'Analyzing BI',
        ];
        $storeData = $data['store'];
        if (is_array($storeData) && isset($storeData['id'])) {
            $data['panel_readonly'] = is_funcionario_panel_readonly((int) $storeData['id']);
            $data['panel_is_gerente'] = is_gerente_store((int) $storeData['id']);
        } else {
            $data['panel_readonly'] = false;
            $data['panel_is_gerente'] = true;
        }
        extract($data);
        require PLATAFORM_BACKEND . '/views/panel/analyzing_bi.php';
    }
}
