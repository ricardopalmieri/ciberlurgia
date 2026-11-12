<?php
/* Ciberlurgia: roteador único. No Apache/LiteSpeed chega aqui pelo .htaccess;
   localmente: php -S localhost:8080 index.php */
declare(strict_types=1);
require __DIR__ . '/app/nucleo.php';

$caminho = caminho_requisicao();   // já sem a base (subpasta)

// Subpasta sem barra final (/pontodigital/cccd): manda para a pasta com barra.
if (BASE !== '' && rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') === BASE) {
    header('Location: ' . BASE . '/', true, 301); exit;
}

// Servidor embutido do PHP: espelha as regras do .htaccess.
if (PHP_SAPI === 'cli-server') {
    if (preg_match('#^/(app|conteudo|privado|_fonte|docs|\.)#', $caminho) || str_contains($caminho, '/.')) {
        http_response_code(403); exit('Proibido');
    }
    if (str_starts_with($caminho, '/uploads/') && !preg_match('#\.(webp|jpe?g|png)$#', $caminho)) {
        http_response_code(403); exit('Proibido');
    }
    $arq = __DIR__ . $caminho;
    if ($caminho !== '/' && (is_file($arq) || is_file(rtrim($arq, '/') . '/index.html'))) return false;
}

cabecalhos_seguranca();

if ($caminho === '/admin' || str_starts_with($caminho, '/admin/')) {
    require __DIR__ . '/app/admin/roteador.php';
    exit;
}
if ($caminho === '/api/contagem') {
    require __DIR__ . '/app/api_contagem.php';
    exit;
}

// Barra no fim, sempre.
// (normaliza barras e contrabarras para nunca gerar "//host" ou "/\host": evita open redirect)
if ($caminho !== '/' && !str_ends_with($caminho, '/') && !str_contains(basename($caminho), '.')) {
    $destino = '/' . trim((string) preg_replace('#[\\\\/]+#', '/', $caminho), '/') . '/';
    if (preg_match('#[^\w\-/]#u', $destino)) $destino = '/';
    header('Location: ' . u($destino), true, 301);
    exit;
}

require __DIR__ . '/app/views/layout.php';
require __DIR__ . '/app/views/paginas.php';

$partes = array_values(array_filter(explode('/', $caminho)));
$rota = $partes[0] ?? '';

switch (true) {
    case $rota === '' :                      vista_home(); break;
    case $rota === 'linkoteca' && count($partes) === 1:   vista_linkoteca(); break;
    case $rota === 'ferramentas' && count($partes) === 1: vista_ferramentas(); break;
    case $rota === 'coletivo' && count($partes) === 1:    vista_coletivo(); break;
    case $rota === 'manifesto' && count($partes) === 1:   vista_pagina('manifesto', 'coletivo'); break;
    case $rota === 'diario' && count($partes) === 1:      vista_diario(); break;
    case $rota === 'diario' && count($partes) === 2:      vista_post($partes[1]); break;
    case $rota === 'identidade' && count($partes) === 1:
        require __DIR__ . '/app/views/identidade.php'; vista_identidade(); break;
    case $rota === 'identidade' && ($partes[1] ?? '') === 'padroes' && count($partes) === 2:
        require __DIR__ . '/app/views/padroes.php'; vista_padroes(); break;
    default: vista_404();
}
