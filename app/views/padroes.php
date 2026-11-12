<?php
/* Guia de padrões: /identidade/padroes/ (index.php chama vista_padroes()).
   Cada exemplo é HTML de verdade: o bloco "vivo" e o <pre> (recolhido em <details>) saem do mesmo texto.
   Sem JS. Estilos do guia em assets/css/padroes.css; os componentes vêm de cbl.css. */

/** Exemplo com a amostra viva e o HTML para copiar (escapado). */
function padroes_exemplo(string $rotulo, string $html): string {
    return '<div class="pp-exemplo">'
        . '<p class="cota">' . e($rotulo) . '</p>'
        . '<div class="pp-vivo">' . preg_replace('~(href|src|action)="/(?!/)~', '$1="' . BASE . '/', $html) . '</div>'
        . '<details class="pp-codigo"><summary>Ver HTML</summary><pre><code>' . e($html) . '</code></pre></details>'
        . '</div>';
}

/** Só código (PHP ou JS de exemplo), sem amostra. */
function padroes_codigo(string $rotulo, string $codigo): string {
    return '<div class="pp-exemplo">'
        . '<p class="cota">' . e($rotulo) . '</p>'
        . '<details class="pp-codigo"><summary>Ver código</summary><pre><code>' . e($codigo) . '</code></pre></details>'
        . '</div>';
}

function padroes_secao(string $id, string $num, string $titulo, string $intro, string $corpo, string $faixa = 'faixa-giz'): string {
    return '<section class="faixa ' . $faixa . ' pp-secao" id="' . $id . '" aria-labelledby="t-' . $id . '">'
        . '<div class="wrap">'
        . '<p class="cota">' . e($num) . '</p>'
        . '<h2 class="titulo-secao" id="t-' . $id . '">' . e($titulo) . '</h2>'
        . '<p class="pp-intro">' . e($intro) . '</p>'
        . $corpo
        . '</div></section>';
}

/** Tabela das coleções do painel, lida de esquemas(). Não escrever à mão. */
function padroes_tabela_colecoes(): string {
    $linhas = '';
    foreach (esquemas() as $nome => $esq) {
        $formato = $esq['tipo'] === 'objeto' ? 'um registro só' : 'lista, chave: ' . $esq['chave'];
        $campos = [];
        foreach ($esq['campos'] as $c) {
            $campos[] = e($c['rotulo']) . ' <span class="cota">' . e($c['tipo']) . '</span>';
        }
        $linhas .= '<tr><th scope="row"><code>' . e('conteudo/' . $nome . '.json') . '</code></th>'
            . '<td>' . e($esq['rotulo']) . '</td>'
            . '<td>' . e($formato) . '</td>'
            . '<td>' . implode('<br>', $campos) . '</td></tr>';
    }
    return '<div class="pp-tabela-wrap"><table class="pp-tabela"><thead><tr>'
        . '<th scope="col">Arquivo</th><th scope="col">Nome no painel</th><th scope="col">Formato</th><th scope="col">Campos (tipo)</th>'
        . '</tr></thead><tbody>' . $linhas . '</tbody></table></div>';
}

function vista_padroes(): void {
    $secoes = [
        ['principios', '01', 'Princípios'],
        ['cores', '02', 'Cores'],
        ['tipografia', '03', 'Tipografia'],
        ['faixas', '04', 'Faixas'],
        ['gota', '05', 'Gota'],
        ['botoes', '06', 'Botões'],
        ['campos', '07', 'Campos e busca'],
        ['chips', '08', 'Chips e selos'],
        ['cartoes', '09', 'Cartões e listas'],
        ['navegacao', '10', 'Navegação'],
        ['colecoes', '11', 'Conteúdo editável'],
        ['acessibilidade', '12', 'Acessibilidade e peso'],
    ];
    $indice = '<nav class="pp-indice" aria-label="Seções do guia"><ol>';
    foreach ($secoes as [$id, $num, $nome]) {
        $indice .= '<li><a href="#' . $id . '"><span class="n">' . $num . '</span>' . e($nome) . '</a></li>';
    }
    $indice .= '</ol></nav>';

    ob_start();
    echo '<link rel="stylesheet" href="' . e(versao_asset('/assets/css/padroes.css')) . '">';
    echo cabeca_pagina('Guia de padrões', 'Guia de padrões',
        'Os blocos que a gente usa para montar o site. Cada exemplo é o HTML de verdade: copia, troca o texto e pronto.',
        'arco');
    echo '<section class="faixa faixa-giz pp-topo"><div class="wrap">' . $indice . '</div></section>';

    /* 01 Princípios */
    $corpo = '<ol class="pp-lista">'
        . '<li><strong>Celular simples e pouca banda primeiro.</strong> O site tem que abrir rápido numa conexão ruim.</li>'
        . '<li><strong>2D é a base, 3D é camada.</strong> O 3D enfeita por cima. Nunca é o único jeito de usar.</li>'
        . '<li><strong>Contraste e alvo de toque de 44 px.</strong> Se não dá pra ler e tocar, não serve.</li>'
        . '<li><strong>A gota é a assinatura.</strong> É metal em fusão: gradiente de fogo com grão. Gota nunca tem contorno.</li>'
        . '<li><strong>O nome CIBERLURGIA nunca é gradiente.</strong> Sempre preto, giz ou cor sólida. O fogo é só da gota, da faixa e da borda.</li>'
        . '<li><strong>Texto direto.</strong> Frase curta, verbo na frente, sem enfeite.</li>'
        . '</ol>';
    echo padroes_secao('principios', '01', 'Princípios', 'Seis regras. Quando uma decisão ficar em dúvida, volta pra cá.', $corpo);

    /* 02 Cores */
    $cores = [
        ['--preto', 'Preto', '#000000', 'Texto, borda e fundo onde o fogo brilha.'],
        ['--concreto', 'Concreto', '#D2D1CB', 'Fundo claro. Sempre com grão.'],
        ['--giz', 'Giz', '#F5F4F0', 'Papel e cartões. Texto sobre preto.'],
        ['--grafite', 'Grafite', '#4A4A47', 'Texto secundário sobre claro.'],
        ['--cinza', 'Cinza', '#8A8984', 'Texto secundário sobre preto. Arame frio.'],
        ['--cereja', 'Cereja', '#6E0000', 'Forja a cerca de 750 °C. Fundo do hover em Mais procurados.'],
        ['--forja', 'Forja', '#E8180C', 'Forja a cerca de 900 °C. Texto só sobre preto, ou título grande no claro. Fundo de botão com texto preto.'],
        ['--brasa', 'Brasa', '#FF5A00', 'Forja a cerca de 1.000 °C. Linha de hover do menu.'],
        ['--fusao', 'Fusão', '#FFC400', 'Forja a cerca de 1.100 °C. Destaque sobre preto, grifo e foco no topo.'],
        ['--incand', 'Incandescente', '#FFF4CF', 'Forja a cerca de 1.300 °C. Brilho da gota e texto claro sobre o fogo.'],
        ['--arco', 'Arco', '#2D2DFF', 'Contraponto frio. Fundo de botão e linha. Texto em giz por cima.'],
        ['--arco-claro', 'Arco claro', '#7C82FF', 'Texto e linha sobre preto.'],
        ['--fogo', 'Fogo', 'gradiente', 'Gradiente só em gota, faixa (.fio-fogo) e borda, sempre sobre grão. Nunca em texto.'],
    ];
    $grade = '<ul class="pp-cores">';
    foreach ($cores as [$tok, $nome, $hex, $uso]) {
        $grade .= '<li class="pp-cor"><span class="pp-cor__amostra" style="background:var(' . e($tok) . ')" aria-hidden="true"></span>'
            . '<div><p class="pp-cor__nome">' . e($nome) . ' <code>' . e($tok) . '</code></p>'
            . '<p class="cota">' . e($hex) . '</p>'
            . '<p>' . e($uso) . '</p></div></li>';
    }
    $grade .= '</ul>';
    $corpo = '<div class="pp-vivo">' . $grade . '</div>'
        . '<p class="cota">Regra da forja</p>'
        . '<div class="pp-vivo">'
        . '<div class="pp-regra"><p class="pp-txt-forja">Forja sobre preto: funciona.</p></div>'
        . '<div class="pp-regra pp-regra--nao"><p class="pp-txt-forja-pequeno">Forja em texto pequeno sobre giz: não usa.</p></div>'
        . '</div>'
        . padroes_codigo('Uma amostra de cor (a grade acima é uma lista disso)', <<<'HTML'
        <li class="pp-cor">
          <span class="pp-cor__amostra" style="background:var(--forja)" aria-hidden="true"></span>
          <div>
            <p class="pp-cor__nome">Forja <code>--forja</code></p>
            <p class="cota">#E8180C</p>
            <p>Texto só sobre preto, ou título grande no claro.</p>
          </div>
        </li>
        HTML);
    echo padroes_secao('cores', '02', 'Cores',
        'Cada cor tem um trabalho. Sem dúvida, use preto, concreto e giz. A forja aparece como fogo, não como texto de parágrafo.',
        $corpo);

    /* 03 Tipografia */
    $corpo = '<ul class="pp-fontes">'
        . '<li><code>--f-estencil</code> CBL Estencil (Big Shoulders Stencil 900): título, palavra de ordem e número grande, em caixa alta.</li>'
        . '<li><code>--f-geo</code> CBL Jost 500 e 700: subtítulo, rótulo, botão e chamada.</li>'
        . '<li><code>--f-tec</code> CBL Normógrafo (Osifont): cota, carimbo, data e legenda.</li>'
        . '<li><code>--f-texto</code> Atkinson Hyperlegible 400 e 700: texto corrido e formulário.</li>'
        . '</ul>'
        . padroes_exemplo('Títulos e cotas', <<<'HTML'
        <p class="cota">Santo André · ABC Paulista</p>
        <h3 class="titulo-estencil">Oficina aberta</h3>
        <p class="titulo-secao">Quem está na bancada</p>
        HTML)
        . padroes_exemplo('Título gigante: cor sólida, nunca gradiente (regra: o nome nunca tem gradiente)', <<<'HTML'
        <p class="titulo-estencil titulo-estencil--gigante">Solda</p>
        HTML)
        . padroes_exemplo('Texto longo', <<<'HTML'
        <div class="texto-longo">
          <p>Texto corrido vai nessa caixa. Ela limita a linha em cerca de 68 caracteres, que é o ponto em que a leitura não cansa.</p>
          <h2>Subtítulo de seção</h2>
          <p>Um parágrafo a mais, pra ver o espaço entre eles.</p>
        </div>
        HTML);
    echo padroes_secao('tipografia', '03', 'Tipografia',
        'Quatro famílias, cada uma com uma função. Não troca de lugar. Escala: texto 17 px, cota 14 px, subtítulo 22 px, seção 64 px, capa 120 px ou mais. O nome CIBERLURGIA é sempre preto ou cor sólida, sem gradiente.', $corpo, 'faixa-concreto');

    /* 04 Faixas */
    $corpo = padroes_exemplo('Três faixas de fundo', <<<'HTML'
        <div class="faixa-preta pp-faixa"><p class="cota">faixa-preta</p><p>Capa e destaque. Texto em giz.</p></div>
        <div class="faixa-concreto pp-faixa"><p class="cota">faixa-concreto</p><p>O padrão. Fundo com grão, texto preto.</p></div>
        <div class="faixa-giz pp-faixa"><p class="cota">faixa-giz</p><p>Papel liso, pra texto longo e leitura.</p></div>
        <div class="fio-fogo" role="presentation"></div>
        HTML);
    echo padroes_secao('faixas', '04', 'Faixas',
        'Faixa é o fundo de uma seção. Preta pra destaque, concreto pra base, giz pra leitura. O fio-fogo é a régua de gradiente com grão, a única faixa de fogo.', $corpo);

    /* 05 Gota */
    $corpo = padroes_exemplo('As cinco cores da gota', <<<'HTML'
        <figure class="pp-gota"><span class="gota gota--forja" aria-hidden="true"></span><figcaption class="cota">gota--forja</figcaption></figure>
        <figure class="pp-gota"><span class="gota gota--brasa" aria-hidden="true"></span><figcaption class="cota">gota--brasa</figcaption></figure>
        <figure class="pp-gota"><span class="gota gota--fusao" aria-hidden="true"></span><figcaption class="cota">gota--fusao</figcaption></figure>
        <figure class="pp-gota"><span class="gota gota--cereja" aria-hidden="true"></span><figcaption class="cota">gota--cereja</figcaption></figure>
        <figure class="pp-gota"><span class="gota gota--arco" aria-hidden="true"></span><figcaption class="cota">gota--arco</figcaption></figure>
        HTML)
        . padroes_exemplo('data-gota e data-cor', <<<'HTML'
        <a class="pp-gota-link" href="/linkoteca/" data-gota="linkoteca" data-cor="forja" data-transicao>
          <span class="gota gota--forja" aria-hidden="true"></span>Linkoteca
        </a>
        HTML)
        . '<ul class="pp-lista pp-lista--curta">'
        . '<li><code>data-gota</code>: identificador da gota. Todo elemento com <code>data-gota</code> vira uma gota clicável no modo 3D.</li>'
        . '<li><code>data-cor</code>: a cor da gota. Uma de <code>forja</code>, <code>brasa</code>, <code>fusao</code>, <code>arco</code> ou <code>cereja</code>.</li>'
        . '</ul>';
    echo padroes_secao('gota', '05', 'Gota',
        'A gota é a assinatura do site. Aparece no mosaico da capa e nos cartões sem foto, sem contorno nenhum. A forma pulsa devagar, e para quando o aparelho pede movimento reduzido.',
        $corpo, 'faixa-preta');

    /* 06 Botões */
    $corpo = padroes_exemplo('Botões', <<<'HTML'
        <div class="pp-linha">
          <a class="botao" href="#botoes">Giz</a>
          <a class="botao botao--fogo" href="#botoes">Fogo</a>
          <button class="botao botao--arco" type="button">Arco</button>
          <button class="botao botao--icone" type="button" aria-label="Próxima">→</button>
        </div>
        <div class="faixa-preta pp-faixa">
          <a class="botao" href="#botoes">Botão sobre preto</a>
        </div>
        HTML)
        . '<p class="pp-nota">Um botão de fogo por bloco. Botão de só ícone precisa de <code>aria-label</code>. O botão é compacto (cerca de 32 px de altura); a área de toque de 44 px vem de um pseudo-elemento invisível.</p>';
    echo padroes_secao('botoes', '06', 'Botões',
        'O botão tem sombra e sobe no hover. O principal é o de fogo; os outros são apoio.', $corpo);

    /* 07 Campos e busca */
    $corpo = padroes_exemplo('Busca e select', <<<'HTML'
        <div class="busca" role="search">
          <label class="busca__rotulo" for="pp-busca">O que você procura?</label>
          <div class="busca__linha">
            <input class="busca__campo" id="pp-busca" type="search" placeholder="certidão, edital, Mauá…" autocomplete="off">
            <button class="botao botao--fogo" type="button">Buscar</button>
          </div>
        </div>
        <label class="filtros__cidade">
          <span class="cota">Cidade</span>
          <select class="campo" id="pp-cidade">
            <option>Todas</option>
            <option>Santo André</option>
            <option>Mauá</option>
          </select>
        </label>
        HTML)
        . '<p class="pp-nota">Campo de busca sem <code>name</code>: o texto fica no navegador e não vai para o servidor. Ids sempre com prefixo único.</p>';
    echo padroes_secao('campos', '07', 'Campos e busca',
        'Campo tem borda preta, sem arredondar. Foco aparece em azul. Todo campo tem rótulo visível.', $corpo);

    /* 08 Chips e selos */
    $corpo = padroes_exemplo('Chips (filtros)', <<<'HTML'
        <div class="pp-linha">
          <a class="chip chip--ativo" href="#chips">Todas</a>
          <a class="chip chip--forja" href="#chips">Editais</a>
          <a class="chip chip--arco" href="#chips">Conselhos</a>
          <button class="chip chip--brasa" type="button" aria-pressed="true">Tecnologia</button>
        </div>
        HTML)
        . padroes_exemplo('Selos de categoria', <<<'HTML'
        <p class="pp-linha">
          <span class="selo-cat selo-cat--cereja">Cultura</span>
          <span class="selo-cat selo-cat--fusao">Mídia livre</span>
          <span class="selo-cat selo-cat--arco">Conselhos</span>
        </p>
        HTML)
        . '<p class="pp-nota">Chip ativo: <code>chip--ativo</code> ou <code>aria-pressed="true"</code>. Cores: forja, brasa, fusao, arco e cereja.</p>';
    echo padroes_secao('chips', '08', 'Chips e selos',
        'Chip filtra. Selo marca a categoria. A bolinha tem a cor da categoria.', $corpo, 'faixa-concreto');

    /* 09 Cartões e listas */
    $corpo = padroes_exemplo('Cartão', <<<'HTML'
        <article class="cartao">
          <div class="cartao__corpo">
            <p class="cota">Categoria ou data</p>
            <h3 class="cartao__titulo"><a href="#cartoes">Título do cartão</a></h3>
            <p>Um parágrafo curto, uma ou duas linhas.</p>
            <a class="botao botao--fogo" href="#cartoes">Abrir</a>
          </div>
        </article>
        HTML)
        . padroes_exemplo('Item de lista de links', <<<'HTML'
        <ul class="resultados">
          <li class="link-item">
            <a class="link-item__titulo" href="#cartoes">Nome do link <span aria-hidden="true">↗</span></a>
            <p>Para que serve, numa frase.</p>
            <p class="link-item__meta cota"><span class="selo-cat selo-cat--forja">Editais</span><span>Santo André</span><span>exemplo.org</span></p>
          </li>
        </ul>
        HTML)
        . padroes_exemplo('Mais procurados', <<<'HTML'
        <section class="procurados" aria-labelledby="pp-t-procurados">
          <h3 class="titulo-secao" id="pp-t-procurados">Mais procurados</h3>
          <ol class="procurados__lista">
            <li><a href="#cartoes"><span class="procurados__n">1</span>Nome do link</a></li>
            <li><a href="#cartoes"><span class="procurados__n">2</span>Outro link</a></li>
          </ol>
        </section>
        HTML)
        . padroes_exemplo('Pessoa do coletivo', <<<'HTML'
        <ul class="carrossel" aria-label="Exemplo de pessoa">
          <li class="pessoa" data-gota="pessoa-exemplo" data-cor="arco">
            <div class="pessoa__retrato" aria-hidden="true"><span class="blob blob--arco"><span class="blob__iniciais">RP</span></span></div>
            <h3 class="pessoa__nome">Ricardo Palmieri</h3>
            <p class="cota pessoa__funcao">Na bancada</p>
            <p class="pessoa__bio">Uma frase sobre o que a pessoa faz na oficina.</p>
            <ul class="pessoa__contatos">
              <li><a href="#cartoes">Contato</a></li>
            </ul>
          </li>
        </ul>
        HTML)
        . '<p class="pp-nota">Lista de links da Linkoteca usa <code>item_link()</code>, em <code>app/views/paginas.php</code>. Não copie o HTML na mão.</p>';
    echo padroes_secao('cartoes', '09', 'Cartões e listas',
        'Cartão é a peça básica: topo com foto ou gota, título, texto e um botão. Listas usam as mesmas cores e fontes.', $corpo);

    /* 10 Navegação */
    $corpo = padroes_exemplo('Link com transição e o botão 2D/3D', <<<'HTML'
        <a class="botao" href="/linkoteca/" data-transicao>Ir para a Linkoteca</a>
        <div class="faixa-preta pp-faixa">
          <button class="botao-modo" type="button" aria-pressed="false" tabindex="-1">
            <span class="botao-modo__2d">2D</span><span class="botao-modo__3d">3D</span>
          </button>
        </div>
        HTML)
        . '<p class="pp-nota">O botão de verdade fica no topo, com <code>id="alternar-modo"</code>. Aqui é só o visual.</p>'
        . '<dl class="pp-dl">'
        . '<div><dt><code>data-transicao</code></dt><dd>Link com transição entre páginas. Sem o módulo de navegação, vira um link comum.</dd></div>'
        . '<div><dt><code>#alternar-modo</code></dt><dd>O botão 2D/3D. Troca o modo e guarda a escolha no aparelho.</dd></div>'
        . '<div><dt><code>cbl:modo</code></dt><dd>Evento no <code>document</code>, quando o botão troca o modo. Traz <code>detail.modo</code>: <code>2d</code> ou <code>3d</code>.</dd></div>'
        . '<div><dt><code>cbl:pagina</code></dt><dd>Evento no <code>document</code>, depois de cada carga. Traz <code>detail.pagina</code> e <code>detail.raiz</code> (o <code>main</code>).</dd></div>'
        . '<div><dt><code>#conteudo[data-scripts]</code></dt><dd>Módulos JS desta página, separados por espaço. O <code>base.js</code> carrega cada um e só depois dispara <code>cbl:pagina</code>.</dd></div>'
        . '</dl>'
        . padroes_codigo('Como a página declara seu JS (PHP)', <<<'PHP'
        layout('Linkoteca', 'linkoteca', $html, ['scripts' => ['/assets/js/linkoteca.js']]);
        PHP)
        . padroes_codigo('Como reagir a uma página nova (JS)', <<<'JS'
        document.addEventListener('cbl:pagina', (e) => {
          const raiz = e.detail.raiz; // o <main id="conteudo">
          // inicia o que a página precisa aqui
        });
        JS);
    echo padroes_secao('navegacao', '10', 'Navegação',
        'Os links comuns funcionam sem JS. O JS só acelera a troca de página e o 3D.', $corpo, 'faixa-preta');

    /* 11 Conteúdo editável */
    $corpo = '<p class="pp-nota">Estas coleções ficam em <code>conteudo/*.json</code> e são editadas no painel, em <code>/admin</code>. A tabela sai de <code>app/esquemas.php</code>, então muda sozinha quando o esquema muda.</p>'
        . padroes_tabela_colecoes()
        . padroes_codigo('Como o PHP lê uma coleção', <<<'PHP'
        $pessoas = ler_colecao('pessoas'); // lista de arrays, um por pessoa
        PHP);
    echo padroes_secao('colecoes', '11', 'Conteúdo editável',
        'O que o painel edita, arquivo por arquivo. Campo de link e de imagem têm regra própria no núcleo.', $corpo);

    /* 12 Acessibilidade e peso */
    $corpo = '<ul class="pp-checklist">'
        . '<li>Forja como texto só sobre preto, ou em título grande no claro.</li>'
        . '<li>Alvo de toque de 44 px no mínimo (botão compacto com área invisível ao redor).</li>'
        . '<li>Contraste AA: 4,5 para 1 em texto, 3 para 1 em título grande e borda.</li>'
        . '<li>Gota sem contorno preto. Nome CIBERLURGIA sem gradiente.</li>'
        . '<li>Foco de teclado visível: azul sobre claro, amarelo sobre preto.</li>'
        . '<li>Toda imagem tem <code>alt</code>. Se for decorativa, <code>alt=""</code>.</li>'
        . '<li>Botão só com ícone tem <code>aria-label</code>.</li>'
        . '<li>Imagem com <code>width</code> e <code>height</code>, pra a tela não pular.</li>'
        . '<li>Imagens em webp ou svg, fontes em woff2. Nada de script novo sem motivo.</li>'
        . '<li>Movimento: a gota pulsa devagar, e o CSS corta isso com movimento reduzido.</li>'
        . '<li>A página abre e se lê sem JS. Teste desligando o JS.</li>'
        . '<li>Sem CSS inline, a não ser a cor da amostra.</li>'
        . '</ul>';
    echo padroes_secao('acessibilidade', '12', 'Acessibilidade e peso',
        'Antes de publicar uma página nova, passa por esta lista.', $corpo, 'faixa-concreto');

    echo '<section class="faixa faixa-giz"><div class="wrap"><p class="pp-nota">Dúvida ou coisa que falta aqui? A gente ajusta. <a href="' . e(u('/identidade/')) . '">Voltar ao manual</a>.</p></div></section>';

    layout('Guia de padrões', 'padroes', (string) ob_get_clean(), ['secao' => 'coletivo']);
}
