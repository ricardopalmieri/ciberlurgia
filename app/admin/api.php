<?php
/* Painel admin: endpoints JSON (salvar, prévia, upload, galeria, backups).
   Chamados por assets/admin/admin.js via fetch; o roteador já checou login e CSRF nos POST. */
declare(strict_types=1);

const ADM_UPLOAD_MAX = 5 * 1024 * 1024;
const ADM_IMG_LADO_MAX = 8000;
const ADM_IMG_PIXELS_MAX = 40000000;
const ADM_IMG_LARGURA = 1600;

function adm_corpo_json(): array {
    $bruto = (string) file_get_contents('php://input', false, null, 0, 6 * 1024 * 1024);
    $d = json_decode($bruto, true);
    if (!is_array($d)) adm_json(['ok' => false, 'erro' => 'Corpo da requisição inválido.'], 400);
    return $d;
}

function adm_colecao_valida(mixed $nome): string {
    if (!is_string($nome) || !preg_match('/^[a-z]{1,30}$/D', $nome) || !isset(esquemas()[$nome])) {
        adm_json(['ok' => false, 'erro' => 'Coleção desconhecida.'], 404);
    }
    return $nome;
}

/** Valida, confere referências e grava. Devolve [ok, erros, dados_limpos]. */
function adm_gravar_validado(string $nome, mixed $dados): array {
    [$limpos, $erros] = validar_colecao($nome, $dados);
    if (!$erros && $limpos !== null) {
        foreach (adm_referencias_quebradas($nome, $limpos) as $e) $erros[] = $e;
    }
    if ($erros || $limpos === null) return [false, $erros ?: ['Dados inválidos.'], null];
    gravar_colecao($nome, $limpos);
    return [true, [], $limpos];
}

/** Impede remover/renomear algo que outra coleção ainda usa (ex.: categoria com links). */
function adm_referencias_quebradas(string $nome, array $limpos): array {
    $esq = esquemas();
    if (($esq[$nome]['tipo'] ?? '') !== 'lista') return [];
    $erros = [];
    foreach ($esq as $outra => $e) {
        if ($e['tipo'] !== 'lista') continue;
        foreach ($e['campos'] as $c) {
            if ($c['tipo'] !== 'referencia' || $c['colecao'] !== $nome) continue;
            $validos = array_column($limpos, $c['campo']);
            $usados = [];
            foreach (ler_colecao($outra) as $item) {
                $v = (string) ($item[$c['nome']] ?? '');
                if ($v !== '' && !in_array($v, $validos, true)) $usados[$v] = true;
            }
            foreach (array_keys($usados) as $v) {
                $erros[] = 'Ainda há itens em "' . $e['rotulo'] . '" usando "' . $v . '". Troque a categoria deles antes de remover ou renomear.';
            }
        }
    }
    return $erros;
}

function api_salvar(string $nome): never {
    $nome = adm_colecao_valida($nome);
    $corpo = adm_corpo_json();
    [$ok, $erros, $limpos] = adm_gravar_validado($nome, $corpo['dados'] ?? null);
    if (!$ok) adm_json(['ok' => false, 'erros' => $erros], 422);
    adm_json(['ok' => true, 'dados' => $limpos, 'quando' => date('H:i:s')]);
}

function api_previa(): never {
    $corpo = adm_corpo_json();
    $t = mb_substr((string) ($corpo['texto'] ?? ''), 0, 20000);
    adm_json(['ok' => true, 'html' => md($t)]);
}

/* ---------- Upload ---------- */

function adm_falha_upload(string $msg, int $status = 422): never {
    adm_json(['ok' => false, 'erro' => $msg], $status);
}

function api_upload(): never {
    $f = $_FILES['arquivo'] ?? null;
    if (!is_array($f) || is_array($f['error'] ?? null)) adm_falha_upload('Nenhum arquivo recebido.');
    $erroUp = (int) $f['error'];
    if ($erroUp === UPLOAD_ERR_INI_SIZE || $erroUp === UPLOAD_ERR_FORM_SIZE) adm_falha_upload('Arquivo grande demais (máximo 5 MB).');
    if ($erroUp !== UPLOAD_ERR_OK) adm_falha_upload('O envio falhou. Tente de novo.');
    $tmp = (string) $f['tmp_name'];
    if (!is_uploaded_file($tmp)) adm_falha_upload('Envio inválido.');
    if ((int) $f['size'] > ADM_UPLOAD_MAX || filesize($tmp) > ADM_UPLOAD_MAX) adm_falha_upload('Arquivo grande demais (máximo 5 MB).');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $tipos = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
    if (!isset($tipos[$mime])) adm_falha_upload('Formato não aceito. Use JPG, PNG ou WebP.');
    $info = @getimagesize($tmp);
    if (!$info || $info[2] !== $tipos[$mime]) adm_falha_upload('O arquivo não parece uma imagem válida.');
    [$w, $h] = $info;
    if ($w < 1 || $h < 1) adm_falha_upload('Imagem inválida.');
    if ($w > ADM_IMG_LADO_MAX || $h > ADM_IMG_LADO_MAX) adm_falha_upload('Imagem grande demais (máximo ' . ADM_IMG_LADO_MAX . ' px por lado).');
    if ($w * $h > ADM_IMG_PIXELS_MAX) adm_falha_upload('Imagem com pixels demais (máximo 40 megapixels).');
    if (!function_exists('imagewebp')) adm_falha_upload('O servidor não tem suporte a WebP.', 500);

    @ini_set('memory_limit', '512M');
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png' => @imagecreatefrompng($tmp),
        default => @imagecreatefromwebp($tmp),
    };
    if (!$img) adm_falha_upload('Não consegui ler a imagem.');

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $giro = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($giro) { $r = imagerotate($img, $giro, 0); if ($r) $img = $r; }
    }
    if (!imageistruecolor($img)) imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    if (imagesx($img) > ADM_IMG_LARGURA) {
        $r = imagescale($img, ADM_IMG_LARGURA, -1, IMG_BICUBIC);
        if ($r) { imagesavealpha($r, true); $img = $r; }
    }

    if (!is_dir(DIR_UPLOADS)) @mkdir(DIR_UPLOADS, 0755, true);
    $nome = bin2hex(random_bytes(8)) . '.webp';
    $destino = DIR_UPLOADS . '/' . $nome;
    $ok = imagewebp($img, $destino, 82);
    if (!$ok || !is_file($destino) || filesize($destino) < 50) {
        @unlink($destino);
        adm_falha_upload('Não consegui converter a imagem.', 500);
    }
    @chmod($destino, 0644);
    adm_json(['ok' => true, 'url' => '/uploads/' . $nome]);
}

/* ---------- Galeria ---------- */

function adm_texto_conteudo(): string {
    $t = '';
    foreach (glob(DIR_CONTEUDO . '/*.json') ?: [] as $a) $t .= (string) file_get_contents($a);
    return $t;
}

function api_galeria(): never {
    $texto = adm_texto_conteudo();
    $itens = [];
    foreach (glob(DIR_UPLOADS . '/*.webp') ?: [] as $a) {
        $n = basename($a);
        $itens[] = [
            'url' => '/uploads/' . $n, 'bytes' => (int) filesize($a), 'data' => (int) filemtime($a),
            'usada' => str_contains($texto, '"/uploads/' . $n . '"'),
        ];
    }
    usort($itens, fn($a, $b) => $b['data'] <=> $a['data']);
    adm_json(['ok' => true, 'itens' => $itens]);
}

function api_imagem_excluir(): never {
    $corpo = adm_corpo_json();
    $url = (string) ($corpo['url'] ?? '');
    if (!preg_match('#^/uploads/([a-z0-9][a-z0-9_-]{0,80}\.webp)\z#D', $url, $m)) adm_json(['ok' => false, 'erro' => 'Imagem inválida.'], 422);
    $arq = DIR_UPLOADS . '/' . $m[1];
    if (!is_file($arq)) adm_json(['ok' => false, 'erro' => 'Imagem não encontrada.'], 404);
    if (str_contains(adm_texto_conteudo(), '"/uploads/' . $m[1] . '"')) adm_json(['ok' => false, 'erro' => 'Essa imagem está em uso. Tire ela do conteúdo e salve antes de excluir.'], 409);
    unlink($arq);
    adm_json(['ok' => true]);
}

/* ---------- Backups ---------- */

function adm_lista_backups(string $nome): array {
    $out = [];
    foreach (glob(DIR_PRIVADO . '/backups/' . $nome . '-*.json') ?: [] as $a) {
        $b = basename($a);
        if (!preg_match('/^' . preg_quote($nome, '/') . '-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-[0-9a-f]{4}\.json$/D', $b, $m)) continue;
        $d = json_decode((string) file_get_contents($a), true);
        $out[] = [
            'arquivo' => $b,
            'quando' => "$m[3]/$m[2]/$m[1] $m[4]:$m[5]:$m[6]",
            'itens' => is_array($d) ? count($d) : 0,
            'bytes' => (int) filesize($a),
        ];
    }
    rsort($out);
    return array_slice($out, 0, 20);
}

function api_backups(string $nome): never {
    $nome = adm_colecao_valida($nome);
    adm_json(['ok' => true, 'itens' => adm_lista_backups($nome)]);
}

function api_restaurar(): never {
    $corpo = adm_corpo_json();
    $nome = adm_colecao_valida($corpo['colecao'] ?? null);
    $arq = (string) ($corpo['arquivo'] ?? '');
    if (!preg_match('/^' . preg_quote($nome, '/') . '-\d{8}-\d{6}-[0-9a-f]{4}\.json$/D', $arq)) adm_json(['ok' => false, 'erro' => 'Backup inválido.'], 422);
    $caminho = DIR_PRIVADO . '/backups/' . $arq;
    if (!is_file($caminho)) adm_json(['ok' => false, 'erro' => 'Backup não encontrado.'], 404);
    $dados = json_decode((string) file_get_contents($caminho), true);
    [$ok, $erros, $limpos] = adm_gravar_validado($nome, $dados);
    if (!$ok) adm_json(['ok' => false, 'erros' => $erros], 422);
    adm_json(['ok' => true, 'dados' => $limpos]);
}
