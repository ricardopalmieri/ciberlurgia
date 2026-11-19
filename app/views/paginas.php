<?php
/* Páginas públicas. Tudo é renderizado no servidor (funciona sem JS);
   o JS só acelera busca, filtros e transições. */

function normalizar(string $s): string {
    $s = mb_strtolower($s);
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','î'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

function ler_contagem(): array {
    $arq = DIR_PRIVADO . '/contagem.json';
    $d = is_file($arq) ? json_decode((string) file_get_contents($arq), true) : null;
    return is_array($d) ? $d + ['cliques' => [], 'buscas' => []] : ['cliques' => [], 'buscas' => []];
}

function so_dominio(string $url): string {
    return preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
}

function cabeca_pagina(string $sobretitulo, string $titulo, string $intro = '', string $cor = 'forja'): string {
    return '<header class="cabeca faixa-concreto"><div class="wrap">'
        . '<p class="cota">' . e($sobretitulo) . '</p>'
        . '<h1 class="titulo-estencil">' . e($titulo) . '</h1>'
        . ($intro !== '' ? '<p class="cabeca__intro">' . e($intro) . '</p>' : '')
        . '</div></header>';
}

/** Iniciais para o blob de quem não tem foto: primeiro e último nome. */
function iniciais(string $nome): string {
    $partes = preg_split('/\s+/u', trim($nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$partes) return '';
    $a = mb_substr($partes[0], 0, 1);
    $b = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';
    return mb_strtoupper($a . $b);
}

/* ---------------- Capa ---------------- */

function vista_home(): void {
    $site = ler_colecao('site');
    $nLinks = count(ler_colecao('links'));
    $nFerr = count(ler_colecao('ferramentas'));
    $nPessoas = count(ler_colecao('pessoas'));
    $posts = array_slice(posts_publicados(), 0, 3);
    $gotas = [
        ['linkoteca', 'Linkoteca', "$nLinks links para quem faz cultura no ABC", 'forja', 'g'],
        ['ferramentas', 'Ferramentas', "$nFerr ferramentas livres para usar agora", 'arco', 'm'],
        ['coletivo', 'Coletivo', "$nPessoas pessoas na bancada, manifesto e contato", 'brasa', 'm'],
        ['manifesto', 'Manifesto', 'A ferramenta é de quem usa', 'cereja', 'p'],
        ['diario', 'Diário', 'Notícias da oficina', 'fusao', 'p'],
    ];
    ob_start(); ?>
<section class="capa faixa-preta">
  <div class="wrap capa__grade">
    <div class="capa__texto">
      <p class="cota">Coletivo de cultura digital · Santo André, ABC Paulista</p>
      <h1 class="titulo-estencil titulo-estencil--gigante">CIBERLURGIA</h1>
      <p class="capa__tagline"><?= e($site['tagline'] ?? '') ?></p>
      <p class="capa__chamada"><?= e($site['chamada'] ?? '') ?></p>
    </div>
    <nav class="mosaico" aria-label="Áreas do site">
      <?php foreach ($gotas as [$slug, $nome, $desc, $cor, $tam]): ?>
      <a class="mosaico__item mosaico__item--<?= $tam ?>" href="<?= e(u("/$slug/")) ?>" data-gota="<?= $slug ?>" data-cor="<?= $cor ?>" data-transicao>
        <span class="gota gota--<?= $cor ?>" aria-hidden="true"></span>
        <span class="mosaico__nome"><?= e($nome) ?></span>
        <span class="mosaico__desc"><?= e($desc) ?></span>
      </a>
      <?php endforeach ?>
    </nav>
  </div>
</section>
<?php if ($posts): ?>
<section class="faixa faixa-concreto">
  <div class="wrap">
    <h2 class="titulo-secao">Do diário</h2>
    <div class="grade-cartoes"><?php foreach ($posts as $p) echo cartao_post($p); ?></div>
  </div>
</section>
<?php endif;
    layout('', 'home', (string) ob_get_clean());
}

/* ---------------- Linkoteca ---------------- */

function vista_linkoteca(): void {
    $links = array_map(fn($l) => ['url' => u($l['url'])] + $l, ler_colecao('links'));   // links internos ("/...") ganham a base
    $cats = ler_colecao('categorias');
    $nomeCat = array_column($cats, 'nome', 'slug');
    $corCat = array_column($cats, 'cor', 'slug');
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
    $cat = (string) ($_GET['cat'] ?? '');
    $cid = (string) ($_GET['cidade'] ?? '');
    if (!isset($nomeCat[$cat])) $cat = '';
    if (!in_array($cid, CIDADES, true)) $cid = '';

    $termos = array_filter(explode(' ', normalizar($q)));
    $res = array_values(array_filter($links, function ($l) use ($termos, $cat, $cid, $nomeCat) {
        if ($cat !== '' && $l['categoria'] !== $cat) return false;
        if ($cid !== '' && ($l['cidade'] ?? '') !== $cid) return false;
        $palheiro = normalizar(implode(' ', [$l['titulo'], $l['descricao'] ?? '', $l['cidade'] ?? '', $nomeCat[$l['categoria']] ?? '', implode(' ', $l['tags'] ?? []), so_dominio($l['url'])]));
        foreach ($termos as $t) if (!str_contains($palheiro, $t)) return false;
        return true;
    }));

    $cont = ler_contagem()['cliques'];
    $porId = array_column($links, null, 'id');
    arsort($cont);
    $top = [];
    foreach ($cont as $id => $n) { if (isset($porId[$id])) $top[] = $porId[$id]; if (count($top) === 6) break; }
    foreach ($links as $l) { if (count($top) >= 6) break; if (!empty($l['destaque']) && !in_array($l, $top, true)) $top[] = $l; }

    $usadas = array_unique(array_filter(array_column($links, 'cidade')));
    $cidades = array_values(array_filter(CIDADES, fn($c) => in_array($c, $usadas, true)));
    $dados = ['links' => $links, 'categorias' => $cats];
    $filtrando = $q !== '' || $cat !== '' || $cid !== '';

    ob_start();
    echo cabeca_pagina('Linkoteca', 'Linkoteca', 'Os endereços que a gente mais usa para tirar certidão, achar edital, falar com o conselho e acompanhar o dinheiro público da cultura no Grande ABC.', 'forja'); ?>
<section class="faixa faixa-giz linkoteca" data-linkoteca>
  <div class="wrap">
    <form class="busca" action="<?= e(u('/linkoteca/')) ?>" method="get" role="search">
      <label class="busca__rotulo" for="busca-q">O que você procura?</label>
      <div class="busca__linha">
        <input class="busca__campo" id="busca-q" name="q" type="search" value="<?= e($q) ?>" placeholder="certidão, edital, Mauá, conselho…" autocomplete="off" enterkeyhint="search">
        <button class="botao botao--fogo" type="submit">Buscar</button>
      </div>
      <div class="filtros">
        <fieldset class="filtros__cats">
          <legend class="cota">Categorias</legend>
          <a class="chip<?= $cat === '' ? ' chip--ativo' : '' ?>" href="<?= e(u('/linkoteca/')) ?>" data-categoria="">Todas</a>
          <?php foreach ($cats as $c): ?>
          <a class="chip chip--<?= e($c['cor'] ?: 'forja') ?><?= $cat === $c['slug'] ? ' chip--ativo' : '' ?>" href="<?= e(u('/linkoteca/?cat=' . $c['slug'])) ?>" data-categoria="<?= e($c['slug']) ?>" data-gota="cat-<?= e($c['slug']) ?>" data-cor="<?= e($c['cor'] ?: 'forja') ?>"><?= e($c['nome']) ?></a>
          <?php endforeach ?>
        </fieldset>
        <label class="filtros__cidade">
          <span class="cota">Cidade</span>
          <select name="cidade" class="campo">
            <option value="">Todas</option>
            <?php foreach ($cidades as $c): ?><option<?= $cid === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach ?>
          </select>
        </label>
        <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif ?>
      </div>
    </form>

    <section class="procurados" aria-labelledby="t-procurados"<?= $filtrando ? ' hidden' : '' ?> data-procurados>
      <h2 class="titulo-secao" id="t-procurados">Mais procurados</h2>
      <ol class="procurados__lista">
        <?php foreach ($top as $i => $l): ?>
        <li><a href="<?= e($l['url']) ?>" rel="noopener" target="_blank" data-contar="<?= e($l['id']) ?>"><span class="procurados__n"><?= $i + 1 ?></span><?= e($l['titulo']) ?></a></li>
        <?php endforeach ?>
      </ol>
    </section>

    <p class="resultados__total cota" aria-live="polite" data-total><?= count($res) ?> <?= count($res) === 1 ? 'link' : 'links' ?></p>
    <ul class="resultados" data-resultados>
      <?php foreach ($res as $l) echo item_link($l, $nomeCat, $corCat); ?>
    </ul>
    <p class="vazio" data-vazio<?= $res ? ' hidden' : '' ?>>Nada encontrado. Tente outra palavra ou <a href="<?= e(u('/linkoteca/')) ?>">veja todos os links</a>.</p>
  </div>
  <script type="application/json" id="dados-links"><?= json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</section>
<?php
    layout('Linkoteca', 'linkoteca', (string) ob_get_clean(), ['scripts' => ['/assets/js/linkoteca.js']]);
}

function item_link(array $l, array $nomeCat, array $corCat): string {
    $cor = $corCat[$l['categoria']] ?? 'forja';
    $ver = !empty($l['verificado']) ? '<span>verificado em ' . e(data_br($l['verificado'])) . '</span>' : '';
    return '<li class="link-item" data-id="' . e($l['id']) . '">'
        . '<a class="link-item__titulo" href="' . e($l['url']) . '" rel="noopener" target="_blank" data-contar="' . e($l['id']) . '">' . e($l['titulo']) . ' <span aria-hidden="true">↗</span></a>'
        . ($l['descricao'] ? '<p>' . e($l['descricao']) . '</p>' : '')
        . '<p class="link-item__meta cota"><span class="selo-cat selo-cat--' . e($cor) . '">' . e($nomeCat[$l['categoria']] ?? '') . '</span>'
        . (!empty($l['cidade']) ? '<span>' . e($l['cidade']) . '</span>' : '')
        . '<span>' . e(so_dominio($l['url'])) . '</span>' . $ver . '</p></li>';
}

/* ---------------- Ferramentas ---------------- */

function vista_ferramentas(): void {
    $fs = ler_colecao('ferramentas');
    $cores = ['arco', 'forja', 'brasa', 'fusao', 'cereja'];
    ob_start();
    echo cabeca_pagina('Ferramentas úteis', 'Ferramentas', 'Ferramentas livres que rodam no navegador, sem cadastro e sem instalar nada. Abre, usa e leva o que fez.', 'arco'); ?>
<section class="faixa faixa-giz">
  <div class="wrap grade-ferramentas">
    <?php foreach ($fs as $i => $f): $cor = $cores[$i % count($cores)]; ?>
    <article class="cartao ferramenta" data-gota="ferr-<?= e($f['id']) ?>" data-cor="<?= $cor ?>">
      <div class="ferramenta__img">
        <?php if ($f['imagem']): ?><img src="<?= e(u($f['imagem'])) ?>" alt="" loading="lazy" width="480" height="300"><?php else: ?><span class="gota gota--<?= $cor ?>" aria-hidden="true"></span><?php endif ?>
      </div>
      <div class="cartao__corpo">
        <h2 class="cartao__titulo"><?= e($f['titulo']) ?></h2>
        <p><?= e($f['descricao']) ?></p>
        <?php if ($f['autoria']): ?><p class="cota">por <?= e($f['autoria']) ?></p><?php endif ?>
        <a class="botao botao--fogo" href="<?= e(u($f['url'])) ?>" rel="noopener" target="_blank">Abrir ferramenta <span aria-hidden="true">↗</span></a>
      </div>
    </article>
    <?php endforeach ?>
  </div>
</section>
<?php
    layout('Ferramentas úteis', 'ferramentas', (string) ob_get_clean());
}

/* ---------------- Coletivo ---------------- */

function pagina(string $slug): ?array {
    foreach (ler_colecao('paginas') as $p) if ($p['slug'] === $slug) return $p;
    return null;
}

function vista_coletivo(): void {
    $sobre = pagina('sobre') ?? ['titulo' => 'Sobre', 'subtitulo' => '', 'corpo' => ''];
    $man = pagina('manifesto');
    $pessoas = ler_colecao('pessoas');
    ob_start();
    echo cabeca_pagina('Sobre o coletivo', $sobre['titulo'], $sobre['subtitulo'], 'brasa'); ?>
<section class="faixa faixa-giz">
  <div class="wrap texto-longo"><?= md($sobre['corpo']) ?></div>
</section>
<section class="faixa faixa-preta" aria-labelledby="t-pessoas">
  <div class="wrap">
    <div class="titulo-com-acoes">
      <h2 class="titulo-secao" id="t-pessoas">Quem está na bancada</h2>
      <div class="carrossel__botoes" data-carrossel-botoes hidden>
        <button class="botao botao--icone" type="button" data-carrossel-ant aria-label="Anterior">←</button>
        <button class="botao botao--icone" type="button" data-carrossel-prox aria-label="Próxima">→</button>
      </div>
    </div>
    <ul class="carrossel" data-carrossel tabindex="0" aria-label="Pessoas do coletivo">
      <?php foreach ($pessoas as $i => $p): ?>
      <li class="pessoa" data-gota="pessoa-<?= e($p['id']) ?>" data-cor="<?= ['forja','arco','brasa','fusao'][$i % 4] ?>">
        <?php if (!empty($p['foto'])): ?>
        <img class="pessoa__foto" src="<?= e(u($p['foto'])) ?>" alt="Retrato de <?= e($p['nome']) ?>" loading="lazy" width="320" height="320">
        <?php else: ?>
        <div class="pessoa__retrato" aria-hidden="true"><span class="blob blob--<?= ['forja','arco','brasa','fusao'][$i % 4] ?>"><span class="blob__iniciais"><?= e(iniciais($p['nome'])) ?></span></span></div>
        <?php endif ?>
        <h3 class="pessoa__nome"><?= e($p['nome']) ?></h3>
        <p class="cota pessoa__funcao"><?= e($p['funcao']) ?></p>
        <p class="pessoa__bio"><?= e($p['bio']) ?></p>
        <?php if ($p['contatos']): ?>
        <ul class="pessoa__contatos">
          <?php foreach ($p['contatos'] as $c): ?><li><a href="<?= e(u($c['url'])) ?>" rel="noopener" target="_blank"><?= e($c['rotulo']) ?></a></li><?php endforeach ?>
        </ul>
        <?php endif ?>
      </li>
      <?php endforeach ?>
    </ul>
  </div>
</section>
<?php if ($man): ?>
<section class="faixa faixa-concreto manifesto-chamada">
  <div class="wrap">
    <p class="cota">Manifesto</p>
    <h2 class="titulo-estencil"><?= e($man['subtitulo'] ?: $man['titulo']) ?></h2>
    <div class="texto-longo"><?= md(implode("\n\n", array_slice(preg_split("/\n{2,}/", (string) $man['corpo']), 0, 2))) ?></div>
    <p><a class="botao botao--fogo" href="<?= e(u('/manifesto/')) ?>" data-transicao data-gota="manifesto" data-cor="cereja">Ler o manifesto inteiro</a></p>
  </div>
</section>
<?php endif ?>
<section class="faixa faixa-giz">
  <div class="wrap identidade-chamada">
    <span class="gota gota--fusao" aria-hidden="true"></span>
    <div>
      <h2 class="titulo-secao">Identidade visual</h2>
      <p>O manual com marca, cores, fontes e o jeito de aplicar tudo isso. O material é livre: pode estudar, copiar e adaptar.</p>
      <p class="acoes">
        <a class="botao" href="<?= e(u('/identidade/')) ?>">Abrir o manual</a>
        <a class="botao botao--arco" href="<?= e(u('/identidade/padroes/')) ?>" data-transicao>Guia de padrões do site</a>
      </p>
    </div>
  </div>
</section>
<?php
    layout('Sobre o coletivo', 'coletivo', (string) ob_get_clean());
}

function vista_pagina(string $slug, string $secao): void {
    $p = pagina($slug);
    if (!$p) { vista_404(); return; }
    ob_start();
    echo cabeca_pagina('Ciberlurgia', $p['titulo'], $p['subtitulo'], 'cereja'); ?>
<article class="faixa faixa-giz"><div class="wrap texto-longo"><?= md($p['corpo']) ?></div></article>
<?php
    layout($p['titulo'], $slug, (string) ob_get_clean(), ['secao' => $secao, 'descricao' => $p['subtitulo']]);
}

/* ---------------- Diário ---------------- */

function posts_publicados(): array {
    $ps = array_values(array_filter(ler_colecao('diario'), fn($p) => !empty($p['publicado'])));
    usort($ps, fn($a, $b) => strcmp($b['data'], $a['data']));
    return $ps;
}

function cartao_post(array $p): string {
    return '<article class="cartao post-cartao">'
        . ($p['imagem'] ? '<img src="' . e(u($p['imagem'])) . '" alt="" loading="lazy" width="480" height="300">' : '')
        . '<div class="cartao__corpo"><p class="cota">' . e(data_br($p['data'])) . '</p>'
        . '<h3 class="cartao__titulo"><a href="' . e(u('/diario/' . $p['slug'] . '/')) . '" data-transicao data-gota="post-' . e($p['slug']) . '">' . e($p['titulo']) . '</a></h3>'
        . '<p>' . e($p['resumo']) . '</p></div></article>';
}

function vista_diario(): void {
    $ps = posts_publicados();
    ob_start();
    echo cabeca_pagina('Diário', 'Diário da oficina', 'O que a gente anda fazendo, aprendendo e soldando.', 'fusao'); ?>
<section class="faixa faixa-giz"><div class="wrap">
  <?php if ($ps): ?><div class="grade-cartoes"><?php foreach ($ps as $p) echo cartao_post($p); ?></div>
  <?php else: ?><p class="vazio">Ainda não tem post publicado.</p><?php endif ?>
</div></section>
<?php
    layout('Diário', 'diario', (string) ob_get_clean());
}

function vista_post(string $slug): void {
    foreach (posts_publicados() as $p) {
        if ($p['slug'] !== $slug) continue;
        ob_start();
        echo cabeca_pagina(data_br($p['data']), $p['titulo'], $p['resumo'], 'fusao'); ?>
<article class="faixa faixa-giz"><div class="wrap texto-longo">
  <?php if ($p['imagem']): ?><img class="post__img" src="<?= e(u($p['imagem'])) ?>" alt="" width="960" height="540"><?php endif ?>
  <?= md($p['corpo']) ?>
  <?php if ($p['tags']): ?><p class="cota">Marcado como: <?= e(implode(', ', $p['tags'])) ?></p><?php endif ?>
  <p><a class="botao" href="<?= e(u('/diario/')) ?>" data-transicao>← Voltar ao diário</a></p>
</div></article>
<?php
        layout($p['titulo'], 'post', (string) ob_get_clean(), ['secao' => 'diario', 'descricao' => $p['resumo']]);
        return;
    }
    vista_404();
}

/* ---------------- Sitemap (para buscadores, no domínio oficial) ---------------- */

function vista_sitemap(): void {
    $urls = ['/', '/linkoteca/', '/ferramentas/', '/coletivo/', '/manifesto/', '/diario/', '/contato/', '/identidade/'];
    foreach (posts_publicados() as $p) $urls[] = '/diario/' . $p['slug'] . '/';
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $c) echo '  <url><loc>' . e(url_absoluta($c)) . "</loc></url>\n";
    echo "</urlset>\n";
}

function vista_404(): void {
    ob_start();
    echo cabeca_pagina('Erro 404', 'Peça fora do lugar', 'Esse endereço não existe ou mudou de bancada.', 'cereja'); ?>
<section class="faixa faixa-giz"><div class="wrap"><p><a class="botao botao--fogo" href="<?= e(u('/')) ?>" data-transicao>Voltar para a capa</a></p></div></section>
<?php
    layout('Página não encontrada', '404', (string) ob_get_clean(), ['status' => 404]);
}
