<?php

/*
 * Ponto de entrada das Vercel Functions.
 *
 * O filesystem da função é somente-leitura, exceto `/tmp`. Como o próprio
 * framework consulta `LARAVEL_STORAGE_PATH` em `Application::storagePath()`,
 * apontar o storage inteiro para `/tmp` resolve logs, views compiladas, cache
 * de arquivo e sessões de uma só vez — sem tocar em nenhuma configuração.
 *
 * Cada invocação fria começa com `/tmp` vazio, daí a criação dos diretórios
 * aqui: o Laravel assume que eles existem.
 */

$storage = '/tmp/storage';

$diretorios = [
    'app/private',
    'app/public',
    'framework/cache/data',
    'framework/sessions',
    'framework/testing',
    'framework/views',
    'logs',
];

foreach ($diretorios as $diretorio) {
    if (! is_dir($caminho = $storage.'/'.$diretorio)) {
        mkdir($caminho, 0755, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

require __DIR__.'/../public/index.php';
