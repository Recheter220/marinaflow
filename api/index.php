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

/*
 * Falha anterior ao handler de exceções do Laravel — erro fatal no bootstrap,
 * escrita barrada pelo filesystem somente-leitura — não produz página de erro,
 * só um 500 vazio, e o painel da plataforma trunca o log mostrando apenas as
 * últimas linhas, justamente onde a causa raiz não está. Com `APP_DEBUG`
 * ligado, a cadeia inteira vai no corpo da resposta, que não é truncado.
 * Com ele desligado a exceção sobe intacta, como se este bloco não existisse.
 */
try {
    require __DIR__.'/../public/index.php';
} catch (Throwable $excecao) {
    $debug = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');

    if (! filter_var((string) $debug, FILTER_VALIDATE_BOOL)) {
        throw $excecao;
    }

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    for ($atual = $excecao; $atual !== null; $atual = $atual->getPrevious()) {
        printf(
            "%s: %s\n    em %s:%d\n\n%s\n\n%s\n\n",
            $atual::class,
            $atual->getMessage(),
            $atual->getFile(),
            $atual->getLine(),
            $atual->getTraceAsString(),
            str_repeat('-', 72),
        );
    }
}
