<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\ReviewRepository;

/**
 * Moderação das avaliações (gerente): listar, responder, remover e restaurar.
 *
 * As rotas exigem store.reviews.manage no Guard. O id da avaliação sempre é
 * conferido junto com a loja da URL, para um gerente não mexer na avaliação
 * de outra loja trocando o número.
 */
class ReviewApiController extends Controller
{
    private const FILTROS = ['todas', 'sem_resposta', 'removidas', 'negativas'];

    /** GET ?filtro=todas|sem_resposta|removidas|negativas */
    public function list(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $filtro = (string) ($_GET['filtro'] ?? 'todas');
        if (!in_array($filtro, self::FILTROS, true)) {
            $filtro = 'todas';
        }
        $repo = new ReviewRepository();
        $this->json([
            'filtro' => $filtro,
            'contagens' => $repo->countsForStore($storeId),
            'avaliacoes' => array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'product_id' => (int) $r['product_id'],
                'product_name' => (string) $r['product_name'],
                'rating' => (int) $r['rating'],
                'comment' => $r['comment'],
                'reply' => $r['reply'],
                'replied_at' => $r['replied_at'],
                'hidden' => $r['hidden_at'] !== null,
                'updated_at' => (string) $r['updated_at'],
                'customer_name' => (string) $r['customer_name'],
                'customer_email' => (string) $r['customer_email'],
            ], $repo->listForStore($storeId, $filtro)),
        ]);
    }

    /** POST {reply} — texto vazio apaga a resposta. */
    public function reply(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        $texto = trim((string) ($this->getJsonInput()['reply'] ?? ''));
        if (mb_strlen($texto) > 1000) {
            $this->json(['error' => 'A resposta pode ter no máximo 1000 caracteres.'], 400);
            return;
        }
        $ok = (new ReviewRepository())->reply($id, $storeId, $texto !== '' ? $texto : null, $this->meuId());
        $ok ? $this->json(['success' => true]) : $this->json(['error' => 'Avaliação não encontrada'], 404);
    }

    public function hide(string $slug, int $id): void
    {
        $this->ocultar($slug, $id, true);
    }

    public function restore(string $slug, int $id): void
    {
        $this->ocultar($slug, $id, false);
    }

    private function ocultar(string $slug, int $id, bool $ocultar): void
    {
        $storeId = $this->storeOr404($slug);
        $ok = (new ReviewRepository())->setHidden($id, $storeId, $ocultar, $this->meuId());
        $ok ? $this->json(['success' => true]) : $this->json(['error' => 'Avaliação não encontrada'], 404);
    }

    private function meuId(): int
    {
        return (int) ($_SESSION['logged_user_id'] ?? 0);
    }

    private function storeOr404(string $slug): int
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            exit;
        }

        return $storeId;
    }
}
