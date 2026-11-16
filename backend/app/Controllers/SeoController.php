<?php

namespace App\Controllers;

use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Repositories\StoreVitrineCategoryRepository;

/**
 * robots.txt e sitemap.xml.
 *
 * Os dois respondiam 404: buscadores só achavam um produto seguindo links.
 * O sitemap lista as páginas públicas (início, privacidade, vitrine, categorias
 * e produtos que aparecem na vitrine). Painel, conta e checkout ficam de fora.
 *
 * Num servidor com o DocumentRoot em public/ (produção), os endereços ficam
 * na raiz do domínio, que é onde os buscadores procuram.
 */
class SeoController extends Controller
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        $base = rtrim(base_url(), '/');
        echo "User-agent: *\n";
        // Páginas de conta, compra e gestão: nada a indexar.
        foreach (['/painel/', '/api/', '/minha-conta', '/lojas', '/criar-loja', '/sair'] as $caminho) {
            echo 'Disallow: ' . $this->caminhoRelativo($caminho) . "\n";
        }
        foreach (['carrinho', 'checkout', 'entrar', 'meus-pedidos', 'meus-enderecos', 'favoritos', 'pedido/'] as $sub) {
            echo 'Disallow: ' . $this->caminhoRelativo('/loja/*/' . $sub) . "\n";
        }
        echo "\nSitemap: {$base}/sitemap.xml\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $urls = [base_url(), base_url('privacidade')];
        $catRepo = new StoreVitrineCategoryRepository();
        $prodRepo = new ProductRepository();
        foreach ((new StoreRepository())->all() as $loja) {
            $slug = (string) $loja['slug'];
            $urls[] = base_url('loja/' . $slug);
            foreach ($catRepo->listByStore((int) $loja['id']) as $cat) {
                $urls[] = base_url('loja/' . $slug . '/categoria/' . (int) $cat['id']);
            }
            // Os mesmos produtos que a vitrine mostra (com estoque).
            foreach ($prodRepo->listByStore((int) $loja['id'], true) as $p) {
                $urls[] = base_url('loja/' . $slug . '/produto/' . (int) $p['id']);
            }
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_unique($urls) as $u) {
            echo '  <url><loc>' . htmlspecialchars($u, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
        }
        echo "</urlset>\n";
    }

    /** "/painel/" → "/plataform_stores/public/painel/" quando a instalação está numa subpasta. */
    private function caminhoRelativo(string $caminho): string
    {
        $prefixo = rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/');

        return $prefixo . $caminho;
    }
}
