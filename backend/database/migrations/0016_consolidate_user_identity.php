<?php

/**
 * Funde as várias linhas de `users` que representam a mesma pessoa.
 *
 * O sistema criava uma linha por loja: a mesma pessoa tinha uma conta de
 * plataforma (store_id NULL) e mais uma para cada loja onde trabalhasse, cada
 * qual com sua própria senha. O login percorria todas e entrava na primeira
 * cuja senha casasse — com permissões diferentes, sem a pessoa saber qual.
 *
 * Aqui cada e-mail passa a ter uma linha só, e tudo que apontava para as outras
 * é repontuado. Escrita em PHP e não em SQL porque envolve decisão: qual
 * registro sobrevive, e o que fazer quando repontuar violaria uma chave única.
 *
 * Sem DDL: roda inteira dentro de uma transação e, se algo falhar, nada muda.
 */

return static function (PDO $pdo): void {
    /** Tabelas que apontam para users.id e não têm restrição de unicidade. */
    $referenciasSimples = [
        ['orders', 'customer_id'],
        ['orders', 'created_by'],
        ['cash_registers', 'opened_by'],
        ['stock_movements', 'user_id'],
        ['user_addresses', 'user_id'],
    ];

    /**
     * Tabelas com chave única envolvendo user_id: repontuar cegamente
     * duplicaria a chave. A linha do duplicado é descartada quando a canônica
     * já cobre a mesma combinação.
     *
     * [tabela, colunas que compõem a chave única além de user_id]
     */
    $referenciasComUnicidade = [
        ['employee_roles', ['role_id']],
        ['employee_goals', ['store_id', 'period']],
        ['store_members', ['store_id']],
    ];

    $duplicados = $pdo->query(
        'SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1'
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if ($duplicados === []) {
        echo '        nenhuma identidade duplicada a consolidar' . PHP_EOL;

        return;
    }

    $pdo->beginTransaction();
    try {
        $fundidos = 0;
        $senhasDivergentes = [];
        $descartados = [];

        foreach ($duplicados as $email) {
            // Canônica: a conta de plataforma, se existir — é a que o login
            // escolhia primeiro, logo é a senha que a pessoa usa hoje. Sem ela,
            // a mais antiga.
            $stmt = $pdo->prepare(
                'SELECT id, name, password, store_id FROM users WHERE email = ?
                  ORDER BY (store_id IS NULL) DESC, id ASC'
            );
            $stmt->execute([$email]);
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $canonica = array_shift($linhas);
            $canonicaId = (int) $canonica['id'];

            foreach ($linhas as $dup) {
                $dupId = (int) $dup['id'];

                if (!hash_equals((string) $canonica['password'], (string) $dup['password'])) {
                    $senhasDivergentes[$email] = true;
                }

                foreach ($referenciasSimples as [$tabela, $coluna]) {
                    $up = $pdo->prepare("UPDATE {$tabela} SET {$coluna} = ? WHERE {$coluna} = ?");
                    $up->execute([$canonicaId, $dupId]);
                }

                foreach ($referenciasComUnicidade as [$tabela, $colunasChave]) {
                    // Descarta as linhas do duplicado cuja combinação a canônica
                    // já possui — repontuá-las violaria a chave única.
                    $cond = implode(' AND ', array_map(
                        static fn (string $c): string => "d.{$c} = c.{$c}",
                        $colunasChave
                    ));
                    $del = $pdo->prepare(
                        "DELETE d FROM {$tabela} d
                         JOIN {$tabela} c ON c.user_id = ? AND {$cond}
                         WHERE d.user_id = ?"
                    );
                    $del->execute([$canonicaId, $dupId]);
                    if ($del->rowCount() > 0) {
                        // Duas contas da mesma pessoa tinham registro para a
                        // mesma combinação (ex.: a mesma meta na mesma loja e
                        // período). Só um pode restar — vale o que já estava na
                        // conta canônica. Perda de dado é sempre reportada.
                        $descartados[$tabela] = ($descartados[$tabela] ?? 0) + $del->rowCount();
                    }

                    $up = $pdo->prepare("UPDATE {$tabela} SET user_id = ? WHERE user_id = ?");
                    $up->execute([$canonicaId, $dupId]);
                }

                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$dupId]);
                $fundidos++;
            }
            
            if (trim((string) $canonica['name']) === '') {
                foreach ($linhas as $dup) {
                    if (trim((string) $dup['name']) !== '') {
                        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')
                            ->execute([$dup['name'], $canonicaId]);
                        break;
                    }
                }
            }
        }

        $pdo->commit();

        echo '        ' . count($duplicados) . ' e-mail(s) consolidado(s), '
            . $fundidos . ' registro(s) duplicado(s) removido(s)' . PHP_EOL;

        if ($senhasDivergentes !== []) {
            echo '        ATENÇÃO: ' . count($senhasDivergentes) . ' pessoa(s) tinham senhas'
                . ' diferentes entre as contas. Ficou valendo a da conta de plataforma;'
                . ' as demais precisam usar essa senha ou redefini-la.' . PHP_EOL;
        }
        foreach ($descartados as $tabela => $n) {
            echo '        ATENÇÃO: ' . $n . ' registro(s) de ' . $tabela . ' descartado(s) por'
                . ' colisão, as duas contas da mesma pessoa tinham a mesma combinação.'
                . ' Prevaleceu o da conta que sobreviveu.' . PHP_EOL;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
};
