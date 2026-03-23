<?php

/**
 * Configuração do banco de dados (XAMPP: usuário root, senha em branco).
 * Crie o banco e as tabelas executando: database/schema.sql no MySQL/phpMyAdmin.
 */
return [
    'host'     => 'localhost',
    'dbname'   => 'plataform_stores',  // nome do banco (crie se não existir)
    'charset'  => 'utf8mb4',
    'username' => 'root',
    'password' => '',                  // no XAMPP costuma ser vazio
];
