<?php

/**
 * Lê direto de getenv() em vez de usar o helper env(): este arquivo é
 * carregado por Database.php e por scripts de CLI que não necessariamente
 * já incluíram Helpers/functions.php.
 */
$appDebug = getenv('APP_DEBUG');
if ($appDebug === false) {
    $appDebug = $_ENV['APP_DEBUG'] ?? '';
}

return [
    'name'       => 'Plataforma de Lojas',
    'url'        => getenv('APP_URL') ?: 'http://localhost/plataform_stores/public',
    'timezone'   => 'America/Sao_Paulo',

    // Ligado, expõe mensagens de exceção na resposta e no HTML. Vem do .env
    // justamente para que subir em produção não dependa de alguém lembrar de
    // editar um arquivo versionado. Ausente = desligado (o padrão seguro).
    'debug'      => in_array(strtolower(trim((string) $appDebug)), ['1', 'true', 'on'], true),

    'rapidapi_key' => getenv('RAPIDAPI_KEY') ?: ($_ENV['RAPIDAPI_KEY'] ?? ''),
];
