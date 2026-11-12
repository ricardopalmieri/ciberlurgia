<?php
/* Painel admin: roteador. O index.php chama este arquivo para /admin e /admin/*
   (depois de cabecalhos_seguranca()). Nada aqui é acessível direto pela web. */
declare(strict_types=1);

require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/vistas.php';
require_once __DIR__ . '/api.php';

adm_cabecalhos();

$adm_caminho = caminho_requisicao();
$adm_rota = trim(substr($adm_caminho, strlen('/admin')), '/');
$adm_metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($adm_metodo, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, POST');
    adm_negar(405, 'Método não permitido.');
}
if (!is_dir(DIR_PRIVADO)) @mkdir(DIR_PRIVADO, 0750, true);
$adm_post = $adm_metodo === 'POST';
adm_iniciar_sessao($adm_rota, $adm_post);
adm_cabecalhos();

/** Exige método certo; senão 405. */
function adm_so(string $metodo, bool $post): void {
    if ($metodo === 'POST' && !$post) { header('Allow: POST'); adm_negar(405, 'Use POST.'); }
    if ($metodo === 'GET' && $post) { header('Allow: GET'); adm_negar(405, 'Use GET.'); }
}

/* ---------- Rotas abertas: entrar e instalar ---------- */

if ($adm_rota === 'entrar') {
    if (adm_logado()) adm_redirecionar('/admin/');
    if (!$adm_post) { adm_vista_login(); exit; }
    adm_exigir_post_seguro();
    $usuario = adm_normalizar_nome((string) ($_POST['usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $espera = adm_reservar_tentativa($usuario);
    if ($espera > 0) {
        adm_vista_login('Muitas tentativas. Espere ' . (int) ceil($espera / 60) . ' minuto(s) e tente de novo.', 429);
        exit;
    }
    $todos = adm_usuarios();
    $existe = $usuario !== '' && isset($todos[$usuario]) && is_array($todos[$usuario]);
    $hash = $existe ? (string) ($todos[$usuario]['hash'] ?? '') : adm_hash_falso();
    if ($hash === '') $hash = adm_hash_falso();
    $senhaOk = password_verify(mb_substr($senha, 0, ADM_SENHA_MAX), $hash);
    if (!$existe || !$senhaOk) {
        adm_vista_login('Usuário ou senha incorretos.', 401);
        exit;
    }
    adm_liberar_tentativa($usuario);
    if (password_needs_rehash($hash, adm_algoritmo())) {
        $novo = adm_hash_senha($senha);
        adm_alterar_usuarios(function (array $u) use ($usuario, $novo) {
            if (isset($u[$usuario])) $u[$usuario]['hash'] = $novo;
            return $u;
        });
    }
    adm_entrar_na_sessao($usuario);
    adm_redirecionar('/admin/');
}

if ($adm_rota === 'instalar') {
    if (!adm_instalacao_disponivel()) adm_negar(404, 'Não encontrado.');
    if (!$adm_post) { adm_vista_instalar(); exit; }
    adm_exigir_post_seguro();
    $espera = adm_reservar_tentativa('~instalar');
    if ($espera > 0) { adm_vista_instalar('Muitas tentativas. Espere alguns minutos.', 429); exit; }
    $token = trim((string) @file_get_contents(DIR_PRIVADO . '/INSTALAR'));
    $dado = trim((string) ($_POST['token'] ?? ''));
    if (strlen($token) < 16 || !hash_equals($token, $dado)) {
        adm_vista_instalar(strlen($token) < 16 ? 'O código em privado/INSTALAR precisa ter pelo menos 16 caracteres.' : 'Código de instalação incorreto.', 403);
        exit;
    }
    adm_liberar_tentativa('~instalar');
    $usuario = adm_normalizar_nome((string) ($_POST['usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $erro = !adm_nome_valido($usuario) ? 'Usuário inválido: use 3 a 32 caracteres (minúsculas, números, ponto, traço).'
        : (adm_erro_senha($senha) ?? ($senha !== (string) ($_POST['senha2'] ?? '') ? 'As senhas não são iguais.' : null));
    if ($erro) { adm_vista_instalar($erro, 422); exit; }
    adm_alterar_usuarios(function (array $u) use ($usuario, $senha) {
        if ($u) throw new RuntimeException('Já instalado');
        return [$usuario => ['hash' => adm_hash_senha($senha), 'criado' => date('c')]];
    });
    @unlink(DIR_PRIVADO . '/INSTALAR');
    adm_flash(is_file(DIR_PRIVADO . '/INSTALAR')
        ? 'Usuário criado, mas não consegui apagar privado/INSTALAR. Apague esse arquivo à mão.'
        : 'Pronto! Usuário criado. Agora é só entrar.', is_file(DIR_PRIVADO . '/INSTALAR') ? 'erro' : 'ok');
    adm_redirecionar('/admin/entrar');
}

/* ---------- Daqui para baixo, só logado ---------- */

if (!adm_logado()) {
    if (adm_eh_api()) adm_json(['ok' => false, 'erro' => 'Sessão expirada. Entre de novo.'], 401);
    if ($adm_rota === '' || $adm_rota === 'sair') adm_redirecionar('/admin/entrar');
    adm_redirecionar('/admin/entrar');
}
if ($adm_post) adm_exigir_post_seguro();

if ($adm_rota === 'sair') {
    adm_so('POST', $adm_post);
    adm_encerrar_sessao();
    adm_redirecionar('/admin/entrar');
}

if ($adm_rota === '') { adm_so('GET', $adm_post); adm_vista_painel(); exit; }
if ($adm_rota === 'galeria') { adm_so('GET', $adm_post); adm_vista_galeria(); exit; }
if ($adm_rota === 'usuarios') { adm_so('GET', $adm_post); adm_vista_usuarios(); exit; }

if (preg_match('#^colecao/([a-z]{1,30})$#', $adm_rota, $m) && isset(esquemas()[$m[1]])) {
    adm_so('GET', $adm_post);
    adm_vista_editor($m[1]);
    exit;
}

/* Ações de usuários (formulários comuns, respondem com redirecionamento) */
if (str_starts_with($adm_rota, 'usuarios/')) {
    adm_so('POST', $adm_post);
    $eu = (string) adm_logado();
    switch ($adm_rota) {
        case 'usuarios/senha':
            $todos = adm_usuarios();
            $atual = (string) ($_POST['atual'] ?? '');
            $nova = (string) ($_POST['nova'] ?? '');
            if (!password_verify(mb_substr($atual, 0, ADM_SENHA_MAX), (string) ($todos[$eu]['hash'] ?? adm_hash_falso()))) {
                adm_flash('A senha atual não confere.', 'erro');
            } elseif ($erro = adm_erro_senha($nova)) {
                adm_flash($erro, 'erro');
            } elseif ($nova !== (string) ($_POST['nova2'] ?? '')) {
                adm_flash('As senhas novas não são iguais.', 'erro');
            } else {
                $h = adm_hash_senha($nova);
                adm_alterar_usuarios(function (array $u) use ($eu, $h) { if (isset($u[$eu])) $u[$eu]['hash'] = $h; return $u; });
                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_SESSION['ph'] = hash('sha256', $h);
                adm_flash('Senha trocada.');
            }
            break;
        case 'usuarios/novo':
            $nome = adm_normalizar_nome((string) ($_POST['usuario'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');
            if (!adm_nome_valido($nome)) {
                adm_flash('Usuário inválido: use 3 a 32 caracteres (minúsculas, números, ponto, traço).', 'erro');
            } elseif (isset(adm_usuarios()[$nome])) {
                adm_flash('Esse usuário já existe.', 'erro');
            } elseif ($erro = adm_erro_senha($senha)) {
                adm_flash($erro, 'erro');
            } else {
                $h = adm_hash_senha($senha);
                adm_alterar_usuarios(function (array $u) use ($nome, $h) { $u[$nome] = ['hash' => $h, 'criado' => date('c')]; return $u; });
                adm_flash("Usuário $nome criado.");
            }
            break;
        case 'usuarios/remover':
            $nome = adm_normalizar_nome((string) ($_POST['usuario'] ?? ''));
            $todos = adm_usuarios();
            if ($nome === $eu) {
                adm_flash('Você não pode remover a si mesmo.', 'erro');
            } elseif (!isset($todos[$nome])) {
                adm_flash('Usuário não encontrado.', 'erro');
            } elseif (count($todos) <= 1) {
                adm_flash('Não dá para remover o último usuário.', 'erro');
            } else {
                adm_alterar_usuarios(function (array $u) use ($nome) { unset($u[$nome]); return $u; });
                adm_flash("Usuário $nome removido.");
            }
            break;
        default:
            adm_negar(404, 'Não encontrado.');
    }
    adm_redirecionar('/admin/usuarios');
}

/* API JSON */
if (str_starts_with($adm_rota, 'api/')) {
    $api = substr($adm_rota, 4);
    if (preg_match('#^salvar/([a-z]{1,30})$#', $api, $m)) { adm_so('POST', $adm_post); api_salvar($m[1]); }
    if (preg_match('#^backups/([a-z]{1,30})$#', $api, $m)) { adm_so('GET', $adm_post); api_backups($m[1]); }
    switch ($api) {
        case 'previa': adm_so('POST', $adm_post); api_previa();
        case 'upload': adm_so('POST', $adm_post); api_upload();
        case 'galeria': adm_so('GET', $adm_post); api_galeria();
        case 'imagem-excluir': adm_so('POST', $adm_post); api_imagem_excluir();
        case 'restaurar': adm_so('POST', $adm_post); api_restaurar();
    }
}

adm_negar(404, 'Página não encontrada.');
