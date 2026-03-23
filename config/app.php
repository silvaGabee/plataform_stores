<?php

return [
    'name'       => 'Plataforma de Lojas',
    'url'        => 'http://localhost/plataform_stores/public',
    'timezone'   => 'America/Sao_Paulo',
    'debug'      => true,
    'rapidapi_key' => getenv('RAPIDAPI_KEY') ?: (isset($_ENV['RAPIDAPI_KEY']) ? $_ENV['RAPIDAPI_KEY'] : ''),
];
