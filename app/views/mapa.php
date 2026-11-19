<?php
/* Dados do mapa de navegação. Quem chama: layout.php (embute em <script id="dados-mapa">).
   Formato: {centro:{titulo,url,selo}, pai:{titulo,url}|null,
             nos:[{id,titulo,desc,url,cor,aberto,externo,ancora}]}
   Página sem opções (folha) devolve null. Nada aqui inventa conteúdo: tudo vem de ler_colecao(). */

function mapa_pai(string $pagina, array $ctx = []): ?array {
    $casa = ['titulo' => 'Ciberlurgia', 'url' => '/'];
    if (!empty($ctx['filtrada'])) return ['titulo' => 'Linkoteca', 'url' => '/linkoteca/'];
    return match ($pagina) {
        'home' => null,
        'manifesto', 'identidade' => ['titulo' => 'Coletivo', 'url' => '/coletivo/'],
        'post' => ['titulo' => 'Diário', 'url' => '/diario/'],
        default => $casa,
    };
}

function mapa_da_pagina(string $pagina, array $ctx = []): ?array {
    $pai = mapa_pai($pagina, $ctx);
    $no = fn(string $id, string $titulo, string $desc, string $url, string $cor, array $x = []): array => array_merge([
        'id' => $id, 'titulo' => $titulo, 'desc' => $desc, 'url' => $url, 'cor' => $cor,
        'aberto' => true, 'externo' => false, 'ancora' => false,
    ], $x);
    $forja = fn(string $id, string $titulo, string $desc): array => [
        'id' => $id, 'titulo' => $titulo, 'desc' => $desc, 'url' => '', 'cor' => 'cinza',
        'aberto' => false, 'externo' => false, 'ancora' => false,
    ];
    $nos = [];

    switch ($pagina) {
        case 'home':
            $nLinks = count(ler_colecao('links'));
            $nFerr = count(ler_colecao('ferramentas'));
            $nPessoas = count(ler_colecao('pessoas'));
            $nos = [
                $no('linkoteca', 'Linkoteca', "$nLinks links para quem faz cultura no ABC", '/linkoteca/', 'forja'),
                $no('ferramentas', 'Ferramentas', "$nFerr ferramentas livres para usar agora", '/ferramentas/', 'arco'),
                $forja('agenda', 'Agenda', 'Próximos encontros e chamadas abertas.'),
                $no('coletivo', 'Coletivo', "$nPessoas pessoas na bancada, manifesto e contato", '/coletivo/', 'brasa'),
                $forja('acervo', 'Acervo aberto', 'Ferramentas, obras e tutoriais que voltam para a rede, com autoria preservada.'),
                $no('diario', 'Diário', 'Notícias da oficina', '/diario/', 'fusao'),
                $forja('bancadas', 'Bancadas parceiras', 'Pontos de cultura, escolas e bibliotecas que recebem o coletivo.'),
            ];
            return ['centro' => ['titulo' => 'Ciberlurgia', 'url' => '/', 'selo' => true], 'pai' => null, 'nos' => $nos];

        case 'linkoteca':
            if (!empty($ctx['filtrada'])) return null;
            foreach (ler_colecao('categorias') as $c) {
                $nos[] = $no('cat-' . $c['slug'], $c['nome'], (string) ($c['descricao'] ?? ''),
                    '/linkoteca/?cat=' . rawurlencode($c['slug']), (string) ($c['cor'] ?: 'forja'));
            }
            $titulo = 'Linkoteca';
            break;

        case 'ferramentas':
            foreach (ler_colecao('ferramentas') as $i => $f) {
                $nos[] = $no('ferr-' . $f['id'], $f['titulo'], (string) ($f['descricao'] ?? ''), $f['url'],
                    ['forja', 'arco', 'brasa', 'fusao'][$i % 4], ['externo' => true]);
            }
            $titulo = 'Ferramentas';
            break;

        case 'coletivo':
            $nos[] = $no('sobre', 'Sobre', 'Quem somos e o que fazemos.', '/coletivo/#sobre', 'forja', ['ancora' => true]);
            foreach (ler_colecao('pessoas') as $i => $p) {
                $nos[] = $no('pessoa-' . $p['id'], $p['nome'], (string) ($p['funcao'] ?? ''), '/coletivo/#pessoa-' . $p['id'],
                    ['arco', 'brasa', 'fusao', 'forja'][$i % 4], ['ancora' => true]);
            }
            $nos[] = $no('manifesto', 'Manifesto', 'A ferramenta é de quem usa.', '/manifesto/', 'cereja');
            $nos[] = $no('identidade', 'Identidade visual', 'O manual com marca, cores, fontes e o jeito de aplicar.', '/identidade/', 'fusao');
            $nos[] = $no('contato', 'Contato', 'Escreva para a bancada.', '/contato/', 'arco');
            $titulo = 'Coletivo';
            break;

        case 'diario':
            foreach (array_slice(posts_publicados(), 0, 10) as $i => $p) {
                $nos[] = $no('post-' . $p['slug'], $p['titulo'], (string) ($p['resumo'] ?? ''), '/diario/' . $p['slug'] . '/',
                    ['fusao', 'brasa', 'forja', 'arco'][$i % 4]);
            }
            $titulo = 'Diário';
            break;

        default:
            return null;
    }

    if (!$nos) return null;
    return ['centro' => ['titulo' => $titulo, 'url' => '/' . $pagina . '/', 'selo' => false], 'pai' => $pai, 'nos' => array_slice($nos, 0, 14)];
}

/* O bloco que o layout imprime: mapa (ou null) + pai da página (para "voltar ao mapa" nas folhas). */
function mapa_json(string $pagina): string {
    $ctx = ['filtrada' => $pagina === 'linkoteca' && (
        (string) ($_GET['cat'] ?? '') !== '' || trim((string) ($_GET['q'] ?? '')) !== '' || (string) ($_GET['cidade'] ?? '') !== ''
    )];
    $dados = ['pagina' => $pagina, 'pai' => mapa_pai($pagina, $ctx), 'mapa' => mapa_da_pagina($pagina, $ctx)];
    // Só a saída ganha a base da subpasta (u() ignora http(s), mailto e vazio).
    if ($dados['pai']) $dados['pai']['url'] = u($dados['pai']['url']);
    if ($dados['mapa']) {
        $dados['mapa']['centro']['url'] = u($dados['mapa']['centro']['url']);
        if ($dados['mapa']['pai']) $dados['mapa']['pai']['url'] = u($dados['mapa']['pai']['url']);
        foreach ($dados['mapa']['nos'] as &$no) $no['url'] = u($no['url']);
        unset($no);
    }
    return json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}
