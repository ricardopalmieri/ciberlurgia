<?php
/* Casca comum a todas as páginas públicas.
   Contrato com o JS: <html data-modo="2d|3d">, <main id="conteudo" data-pagina data-scripts>,
   o mapa de navegação vem de app/views/mapa.php (#dados-mapa) e é desenhado por navegacao.js. */

require_once __DIR__ . '/mapa.php';

function layout(string $titulo, string $pagina, string $html, array $o = []): void {
    $site = ler_colecao('site');
    $nome = $site['nome'] ?? 'Ciberlurgia';
    $tituloCompleto = $titulo === '' ? "$nome · " . ($site['tagline'] ?? '') : "$titulo · $nome";
    $desc = $o['descricao'] ?? ($site['descricao'] ?? '');
    $scripts = implode(' ', array_map('versao_asset', $o['scripts'] ?? []));
    $nav = [
        'linkoteca' => 'Linkoteca',
        'ferramentas' => 'Ferramentas',
        'coletivo' => 'Coletivo',
        'diario' => 'Diário',
        'contato' => 'Contato',
    ];
    $email = email_publico();
    $secao = $o['secao'] ?? $pagina;
    if (!empty($o['status'])) http_response_code($o['status']);
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="pt-BR" data-modo="2d" data-pagina="<?= e($pagina) ?>" data-base="<?= e(BASE) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($tituloCompleto) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#000000">
<meta property="og:title" content="<?= e($tituloCompleto) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($nome) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:url" content="<?= e(url_absoluta(caminho_requisicao())) ?>">
<meta property="og:image" content="<?= e(url_absoluta('/assets/img/og-ciberlurgia.jpg')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Selo da Ciberlurgia: CCCD bordado em creme sobre vermelho">
<meta name="twitter:card" content="summary_large_image">
<?php if (empty($o['status'])): ?><link rel="canonical" href="<?= e(url_absoluta(caminho_requisicao())) ?>"><?php endif ?>
<link rel="icon" href="<?= e(u('/assets/img/mini-128.webp')) ?>" type="image/webp">
<link rel="preload" href="<?= e(u('/assets/fonts/cbl-estencil-900.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(versao_asset('/assets/css/cbl.css')) ?>">
<link rel="stylesheet" href="<?= e(versao_asset('/assets/css/mundo.css')) ?>">
<script src="<?= e(versao_asset('/assets/js/modo.js')) ?>"></script>
<script type="module" src="<?= e(versao_asset('/assets/js/base.js')) ?>"></script>
</head>
<body>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<header class="topo">
  <div class="wrap topo__linha">
    <a class="marca" href="<?= e(u('/')) ?>" aria-label="<?= e($nome) ?>, página inicial" data-transicao>
      <img src="<?= e(u('/assets/img/mini-128.webp')) ?>" alt="" width="40" height="40">
      <span class="marca__nome"><?= e(mb_strtoupper($nome)) ?></span>
    </a>
    <nav class="menu" aria-label="Principal">
      <?php foreach ($nav as $slug => $rot): ?>
        <a href="<?= e(u("/$slug/")) ?>" data-transicao<?= $secao === $slug ? ' aria-current="page"' : '' ?>><?= e($rot) ?></a>
      <?php endforeach ?>
    </nav>
    <button class="botao-modo" id="alternar-modo" type="button" aria-pressed="false" title="Alternar navegação 2D e 3D">
      <span class="botao-modo__2d">2D</span><span class="botao-modo__3d">3D</span>
    </button>
  </div>
</header>
<?php
$dadosMapa = mapa_json($pagina);
$paiFolha = json_decode($dadosMapa, true);
$ehFolha = !$paiFolha['mapa'] && $paiFolha['pai'];
?>
<script type="application/json" id="dados-mapa"><?= $dadosMapa ?></script>
<section class="mapa" id="mapa" hidden aria-label="Mapa do site">
  <div class="mapa__palco" id="palco3d"></div>
  <div class="mapa__rotulos" id="rotulos"></div>
  <a class="mapa__voltar" id="mapa-voltar" href="<?= e(u('/')) ?>" hidden></a>
  <div class="mapa__info" id="mapa-info" aria-live="polite" hidden>
    <span class="mapa__linha"><span class="mapa__estado" id="info-estado"></span><strong class="mapa__nome" id="info-nome"></strong><span class="mapa__desc" id="info-desc"></span></span>
  </div>
  <p class="mapa__dica" id="mapa-dica" hidden>Arraste para girar, toque numa área para abrir</p>
  <div class="clarao" id="clarao" aria-hidden="true"></div>
</section>
<nav class="voltar-mapa" id="voltar-mapa" aria-label="Voltar"<?= $ehFolha ? '' : ' hidden' ?>>
  <?php if ($ehFolha): ?><a class="voltar-mapa__link" href="<?= e($paiFolha['pai']['url']) ?>" data-transicao>&larr; Voltar ao mapa: <?= e($paiFolha['pai']['titulo']) ?></a><?php endif ?>
</nav>
<main id="conteudo" tabindex="-1" data-pagina="<?= e($pagina) ?>" data-titulo="<?= e($tituloCompleto) ?>" data-scripts="<?= e($scripts) ?>">
<?= $html ?>
</main>
<footer class="rodape">
  <div class="wrap rodape__grade">
    <div>
      <p class="rodape__nome"><?= e(mb_strtoupper($nome)) ?></p>
      <p><?= e($site['rodape'] ?? '') ?></p>
    </div>
    <ul class="rodape__links">
      <li><a href="<?= e(u('/contato/')) ?>" data-transicao>Fale com a gente</a></li>
      <li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
      <?php foreach ($site['redes'] ?? [] as $r): ?><li><a href="<?= e(u($r['url'])) ?>" rel="noopener" target="_blank"><?= e($r['rotulo']) ?></a></li><?php endforeach ?>
    </ul>
    <ul class="rodape__links">
      <li><a href="<?= e(u('/manifesto/')) ?>" data-transicao>Manifesto</a></li>
      <li><a href="<?= e(u('/identidade/')) ?>">Identidade visual</a></li>
      <li><a href="<?= e(u('/identidade/padroes/')) ?>" data-transicao>Guia de padrões</a></li>
      <li class="cota">Conteúdo em CC BY-SA 4.0</li>
    </ul>
  </div>
</footer>
</body>
</html>
<?php
}
