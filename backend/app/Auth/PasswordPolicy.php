<?php

namespace App\Auth;

/**
 * Regra única para senha nova: cadastro, funcionário criado pelo gerente e
 * troca de senha no painel.
 *
 * Antes não havia regra nenhuma — "1" era aceito, e o limite de tentativas do
 * login pouco adianta contra uma senha de um caractere. Só vale para senha
 * NOVA: quem já tem uma senha curta continua entrando normalmente.
 */
final class PasswordPolicy
{
    public const MINIMO = 8;
    /** bcrypt ignora o que passa de 72 bytes; acima disso a senha só parece mais forte. */
    public const MAXIMO = 72;

    /** Motivo da recusa, ou null se a senha serve. */
    public static function problema(string $senha): ?string
    {
        if (mb_strlen($senha) < self::MINIMO) {
            return 'A senha precisa ter pelo menos ' . self::MINIMO . ' caracteres.';
        }
        if (strlen($senha) > self::MAXIMO) {
            return 'A senha pode ter no máximo ' . self::MAXIMO . ' caracteres.';
        }
        if (trim($senha) === '') {
            return 'A senha não pode ser só espaços.';
        }

        return null;
    }
}
