<?php
/* Identidade visual dentro do site: o manual estático (identidade/manual.html) num quadro, com o cabeçalho do site em cima.
   Quem chama: index.php (rota /identidade/). Folha do mapa: pai = Coletivo (ver mapa_pai em mapa.php). */

function vista_identidade(): void {
    ob_start(); ?>
<div class="identidade">
  <nav class="identidade__barra" aria-label="Manual de identidade">
    <a class="identidade__link" href="<?= e(u('/coletivo/')) ?>" data-transicao>&larr; Voltar ao coletivo</a>
    <a class="identidade__link" href="<?= e(u('/identidade/manual.html')) ?>" target="_blank" rel="noopener">Abrir em tela cheia</a>
    <a class="identidade__link" href="<?= e(u('/identidade/padroes/')) ?>" data-transicao>Guia de padr&otilde;es</a>
  </nav>
  <iframe class="identidade__quadro" src="<?= e(u('/identidade/manual.html')) ?>" title="Manual de identidade visual"></iframe>
</div>
<?php
    $html = (string) ob_get_clean();
    layout('Identidade visual', 'identidade', $html, ['secao' => 'coletivo']);
}
