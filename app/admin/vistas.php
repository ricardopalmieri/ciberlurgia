<?php
/* Painel admin: páginas HTML (login, instalação, painel, editor, galeria, usuários). */
declare(strict_types=1);

function adm_layout(string $titulo, string $corpo, array $o = []): void {
    $logado = adm_logado();
    $css = versao_asset('/assets/admin/admin.css');
    $js = versao_asset('/assets/admin/admin.js');
    $comJs = !empty($o['js']);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR" data-base="' . e(BASE) . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<meta name="csrf" content="' . e(adm_csrf()) . '">'
        . '<title>' . e($titulo) . ' · Ciberlurgia</title>'
        . '<link rel="stylesheet" href="' . e($css) . '"></head><body>'
        . '<a class="pular" href="#conteudo">Pular para o conteúdo</a>'
        . '<header class="topo"><a class="marca" href="' . BASE . '/admin/">Ciberlurgia <span>bancada</span></a>';
    if ($logado && empty($o['sem_nav'])) {
        echo '<nav aria-label="Principal"><a href="' . BASE . '/admin/">Painel</a><a href="' . BASE . '/admin/galeria">Galeria</a>'
            . '<a href="' . BASE . '/admin/usuarios">Usuários</a><a href="' . BASE . '/" target="_blank" rel="noopener">Ver o site</a>'
            . '<form method="post" action="' . BASE . '/admin/sair" class="sair"><input type="hidden" name="_csrf" value="' . e(adm_csrf()) . '">'
            . '<button class="btn peq" type="submit">Sair (' . e($logado) . ')</button></form></nav>';
    }
    echo '</header><main id="conteudo" tabindex="-1">';
    $f = $logado || !empty($o['flash']) ? adm_flash() : null;
    if ($f) echo '<p class="aviso ' . ($f['t'] === 'erro' ? 'erro' : 'ok') . '" role="status">' . e($f['m']) . '</p>';
    echo $corpo . '</main><div id="aviso-vivo" class="vivo" role="status" aria-live="polite"></div>';
    if ($comJs) echo '<script src="' . e($js) . '" defer></script>';
    echo '</body></html>';
}

function adm_vista_erro(int $status, string $msg): void {
    adm_layout("Erro $status", '<section class="cartao estreito"><h1>Opa</h1><p>' . e($msg) . '</p><p><a class="btn" href="' . BASE . '/admin/">Voltar ao painel</a></p></section>', ['sem_nav' => true]);
}

function adm_campo_input(string $id, string $rotulo, string $nome, string $tipo = 'text', array $extra = []): string {
    $attrs = '';
    foreach ($extra as $k => $v) $attrs .= ' ' . e($k) . ($v === true ? '' : '="' . e((string) $v) . '"');
    return '<div class="campo"><label for="' . e($id) . '">' . e($rotulo) . '</label>'
        . '<input id="' . e($id) . '" name="' . e($nome) . '" type="' . e($tipo) . '"' . $attrs . '></div>';
}

function adm_vista_login(?string $erro = null, int $status = 200): void {
    http_response_code($status);
    if (!adm_usuarios()) {
        $aviso = adm_instalacao_disponivel()
            ? '<p><a class="btn" href="' . BASE . '/admin/instalar">Instalar o primeiro usuário</a></p>'
            : '<p>O painel ainda não foi instalado. Veja <code>docs/admin.md</code> para criar o arquivo <code>privado/INSTALAR</code>.</p>';
        adm_layout('Entrar', '<section class="cartao estreito"><h1>Entrar</h1>' . $aviso . '</section>', ['sem_nav' => true, 'flash' => true]);
        return;
    }
    $corpo = '<section class="cartao estreito"><h1>Entrar</h1>'
        . ($erro ? '<p class="aviso erro" role="alert">' . e($erro) . '</p>' : '')
        . '<form method="post" action="' . BASE . '/admin/entrar" autocomplete="on">'
        . '<input type="hidden" name="_csrf" value="' . e(adm_csrf()) . '">'
        . adm_campo_input('f-usuario', 'Usuário', 'usuario', 'text', ['autocomplete' => 'username', 'required' => true, 'autocapitalize' => 'none', 'spellcheck' => 'false', 'maxlength' => '64'])
        . adm_campo_input('f-senha', 'Senha', 'senha', 'password', ['autocomplete' => 'current-password', 'required' => true, 'maxlength' => (string) ADM_SENHA_MAX])
        . '<button class="btn forte" type="submit">Entrar</button></form></section>';
    adm_layout('Entrar', $corpo, ['sem_nav' => true, 'flash' => true]);
}

function adm_vista_instalar(?string $erro = null, int $status = 200): void {
    http_response_code($status);
    $corpo = '<section class="cartao estreito"><h1>Instalar</h1>'
        . '<p>Cria o primeiro usuário. O código está dentro do arquivo <code>privado/INSTALAR</code> no servidor.</p>'
        . ($erro ? '<p class="aviso erro" role="alert">' . e($erro) . '</p>' : '')
        . '<form method="post" action="' . BASE . '/admin/instalar" autocomplete="off">'
        . '<input type="hidden" name="_csrf" value="' . e(adm_csrf()) . '">'
        . adm_campo_input('f-token', 'Código de instalação', 'token', 'password', ['required' => true, 'autocomplete' => 'off', 'maxlength' => '200'])
        . adm_campo_input('f-usuario', 'Nome de usuário (letras minúsculas, números, ponto, traço)', 'usuario', 'text', ['required' => true, 'autocomplete' => 'username', 'autocapitalize' => 'none', 'pattern' => '[a-z0-9][a-z0-9._-]{2,31}', 'maxlength' => '32'])
        . adm_campo_input('f-senha', 'Senha (mínimo ' . ADM_SENHA_MIN . ' caracteres)', 'senha', 'password', ['required' => true, 'autocomplete' => 'new-password', 'minlength' => (string) ADM_SENHA_MIN, 'maxlength' => (string) ADM_SENHA_MAX])
        . adm_campo_input('f-senha2', 'Repita a senha', 'senha2', 'password', ['required' => true, 'autocomplete' => 'new-password', 'minlength' => (string) ADM_SENHA_MIN, 'maxlength' => (string) ADM_SENHA_MAX])
        . '<button class="btn forte" type="submit">Criar usuário</button></form></section>';
    adm_layout('Instalar', $corpo, ['sem_nav' => true]);
}

/** Lê privado/contagem.json (só leitura; pode não existir). */
function adm_contagem(): array {
    $f = DIR_PRIVADO . '/contagem.json';
    $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    $out = ['cliques' => [], 'buscas' => []];
    foreach (['cliques', 'buscas'] as $k) {
        if (!is_array($d[$k] ?? null)) continue;
        foreach ($d[$k] as $chave => $n) if (is_numeric($n)) $out[$k][(string) $chave] = (int) $n;
        arsort($out[$k]);
    }
    return $out;
}

function adm_vista_painel(): void {
    $cards = '';
    foreach (esquemas() as $nome => $esq) {
        $arq = DIR_CONTEUDO . "/$nome.json";
        $dados = ler_colecao($nome);
        $qtd = $esq['tipo'] === 'lista' ? count($dados) . ' itens' : 'texto único';
        $quando = is_file($arq) ? date('d/m/Y H:i', (int) filemtime($arq)) : 'nunca';
        $cards .= '<li><a class="cartao cartao-link" href="' . BASE . '/admin/colecao/' . e($nome) . '"><h2>' . e($esq['rotulo']) . '</h2>'
            . '<p>' . e($qtd) . '</p><p class="mudo">Alterado em ' . e($quando) . '</p></a></li>';
    }
    $c = adm_contagem();
    $buscas = '';
    foreach (array_slice($c['buscas'], 0, 15, true) as $termo => $n) {
        $buscas .= '<li><span>' . e((string) $termo) . '</span><b>' . $n . '</b></li>';
    }
    $titulos = array_column(ler_colecao('links'), 'titulo', 'id');
    $cliques = '';
    foreach (array_slice($c['cliques'], 0, 10, true) as $id => $n) {
        $cliques .= '<li><span>' . e((string) ($titulos[(string) $id] ?? $id)) . '</span><b>' . $n . '</b></li>';
    }
    $corpo = '<h1>Painel</h1><p>O que você quer editar?</p><ul class="grade">' . $cards . '</ul>'
        . '<div class="duas"><section class="cartao"><h2>Mais buscado na Linkoteca</h2>'
        . ($buscas ? '<ol class="ranking">' . $buscas . '</ol>' : '<p class="mudo">Ainda sem buscas registradas.</p>') . '</section>'
        . '<section class="cartao"><h2>Links mais abertos</h2>'
        . ($cliques ? '<ol class="ranking">' . $cliques . '</ol>' : '<p class="mudo">Ainda sem cliques registrados.</p>') . '</section></div>';
    adm_layout('Painel', $corpo);
}

function adm_max_padrao(array $c): int {
    return (int) ($c['max'] ?? match ($c['tipo']) { 'markdown' => 20000, 'textarea' => 3000, default => 300 });
}

function adm_esquema_publico(string $nome): array {
    $esq = esquemas()[$nome];
    foreach ($esq['campos'] as &$c) {
        $c['max'] = adm_max_padrao($c);
        if ($c['tipo'] === 'referencia') {
            $alvo = esquemas()[$c['colecao']];
            $c['ref'] = [];
            foreach (ler_colecao($c['colecao']) as $it) {
                $c['ref'][] = ['v' => (string) ($it[$c['campo']] ?? ''), 'r' => (string) ($it[$alvo['titulo_item']] ?? $it[$c['campo']] ?? '')];
            }
        }
    }
    unset($c);
    return $esq;
}

function adm_vista_editor(string $nome): void {
    $esq = adm_esquema_publico($nome);
    $dados = ler_colecao($nome);
    $pacote = ['nome' => $nome, 'esquema' => $esq, 'dados' => $dados, 'cidades' => CIDADES];
    $corpo = '<p class="migalha"><a href="' . BASE . '/admin/">Painel</a> / ' . e($esq['rotulo']) . '</p>'
        . '<div id="editor-app" class="editor"><p>Carregando o editor… (precisa de JavaScript)</p></div>'
        . '<script type="application/json" id="pacote">' . adm_json_str($pacote) . '</script>';
    adm_layout($esq['rotulo'], $corpo, ['js' => true]);
}

function adm_vista_galeria(): void {
    $corpo = '<h1>Galeria</h1><p>Imagens enviadas para o site. Só dá para excluir as que nenhum conteúdo usa.</p>'
        . '<div id="galeria-pagina"><p>Carregando… (precisa de JavaScript)</p></div>';
    adm_layout('Galeria', $corpo, ['js' => true]);
}

function adm_vista_usuarios(): void {
    $eu = (string) adm_logado();
    $lista = '';
    foreach (adm_usuarios() as $nome => $u) {
        $nome = (string) $nome;
        $lista .= '<li><span><b>' . e($nome) . '</b>' . ($nome === $eu ? ' (você)' : '') . '</span>';
        if ($nome !== $eu) {
            $lista .= '<form method="post" action="' . BASE . '/admin/usuarios/remover" data-confirmar="Remover o usuário ' . e($nome) . '? A pessoa perde o acesso na hora.">'
                . '<input type="hidden" name="_csrf" value="' . e(adm_csrf()) . '"><input type="hidden" name="usuario" value="' . e($nome) . '">'
                . '<button class="btn peq perigo" type="submit">Remover</button></form>';
        }
        $lista .= '</li>';
    }
    $csrf = '<input type="hidden" name="_csrf" value="' . e(adm_csrf()) . '">';
    $senhaAttrs = ['required' => true, 'minlength' => (string) ADM_SENHA_MIN, 'maxlength' => (string) ADM_SENHA_MAX];
    $corpo = '<h1>Usuários</h1><div class="duas">'
        . '<section class="cartao"><h2>Quem tem acesso</h2><ul class="lista-usuarios">' . $lista . '</ul></section>'
        . '<section class="cartao"><h2>Trocar minha senha</h2><form method="post" action="' . BASE . '/admin/usuarios/senha">' . $csrf
        . adm_campo_input('f-atual', 'Senha atual', 'atual', 'password', ['required' => true, 'autocomplete' => 'current-password', 'maxlength' => (string) ADM_SENHA_MAX])
        . adm_campo_input('f-nova', 'Nova senha (mínimo ' . ADM_SENHA_MIN . ' caracteres)', 'nova', 'password', $senhaAttrs + ['autocomplete' => 'new-password'])
        . adm_campo_input('f-nova2', 'Repita a nova senha', 'nova2', 'password', $senhaAttrs + ['autocomplete' => 'new-password'])
        . '<button class="btn forte" type="submit">Trocar senha</button></form></section></div>'
        . '<section class="cartao"><h2>Adicionar usuário</h2><form method="post" action="' . BASE . '/admin/usuarios/novo">' . $csrf
        . adm_campo_input('f-novo-nome', 'Nome de usuário (minúsculas, números, ponto, traço)', 'usuario', 'text', ['required' => true, 'autocomplete' => 'off', 'autocapitalize' => 'none', 'pattern' => '[a-z0-9][a-z0-9._-]{2,31}', 'maxlength' => '32'])
        . adm_campo_input('f-novo-senha', 'Senha inicial (mínimo ' . ADM_SENHA_MIN . ' caracteres)', 'senha', 'password', $senhaAttrs + ['autocomplete' => 'new-password'])
        . '<button class="btn forte" type="submit">Adicionar</button></form></section>';
    adm_layout('Usuários', $corpo, ['js' => true]);
}
