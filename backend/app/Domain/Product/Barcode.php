<?php

namespace App\Domain\Product;

/**
 * Código de barras ou SKU de um produto.
 *
 * Aceita EAN e códigos internos, então não confere dígito verificador. O leitor
 * funciona como um teclado: digita o código e tecla Enter no campo de busca do PDV.
 */
final class Barcode
{
    public const MAX = 40;

    /**
     * Devolve o código em maiúsculas, ou null quando vier vazio (sem código).
     *
     * @param mixed $codigo
     */
    public static function normalize($codigo): ?string
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return null;
        }
        if (strlen($codigo) > self::MAX || !preg_match('/^[A-Z0-9._-]+$/', $codigo)) {
            throw new \InvalidArgumentException('Código de barras inválido: use até ' . self::MAX . ' letras, números, ponto, hífen ou sublinhado.');
        }

        return $codigo;
    }
}
