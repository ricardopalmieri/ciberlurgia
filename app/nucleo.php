<?php
/* Ciberlurgia: núcleo compartilhado (site público e painel).
   Tudo que é conteúdo mora em conteudo/*.json e passa por validar_colecao() antes de gravar. */
declare(strict_types=1);

const RAIZ = __DIR__ . '/..';
const DIR_CONTEUDO = RAIZ . '/conteudo';
const DIR_PRIVADO = RAIZ . '/privado';
const DIR_UPLOADS = RAIZ . '/uploads';
const CIDADES = ['Santo André', 'São Bernardo do Campo', 'São Caetano do Sul', 'Diadema', 'Mauá', 'Ribeirão Pires', 'Rio Grande da Serra', 'Grande ABC', 'Estado de SP', 'Brasil'];

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

/* ---------- Base do site (raiz ou subpasta) ----------
   BASE = '' quando o site está na raiz do domínio, ou '/pontodigital/cccd' numa subpasta (sem barra no fim).
   Os JSON e o painel guardam caminhos começando com "/" (ex.: /uploads/x.webp, /manifesto/), independentes da base;
   só a SAÍDA (HTML, redirects, cookies) passa por u(). Detecção, nesta ordem:
   1. privado/config.php retornando ['base' => '/x/y'] (override manual);
   2. pasta do site dentro da pasta pública do servidor (DOCUMENT_ROOT);
   3. pasta do SCRIPT_NAME. */
function base_valida(string $b): string {
    $b = '/' . trim(str_replace("\\", '/', $b), '/');
    return $b !== '/' && preg_match('#^(?:/[A-Za-z0-9._~-]+)+$#', $b) && !str_contains($b, '..') ? $b : '';
}

/** privado/config.php (opcional): ['base' => '', 'url' => 'https://...', 'email_contato' => '...', 'email_remetente' => '...']. */
function config(): array {
    static $c = null;
    if ($c !== null) return $c;
    $cfg = DIR_PRIVADO . '/config.php';
    $v = is_file($cfg) ? (static fn() => @include $cfg)() : null;
    return $c = is_array($v) ? $v : [];
}

function detectar_base(): string {
    if (isset(config()['base'])) return base_valida((string) config()['base']);
    $docroot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $raizReal = realpath(RAIZ);
    $docReal = $docroot !== '' ? realpath($docroot) : false;
    if ($raizReal !== false && $docReal !== false) {
        $r = rtrim(str_replace("\\", '/', $raizReal), '/');
        $d = rtrim(str_replace("\\", '/', $docReal), '/');
        $win = DIRECTORY_SEPARATOR === chr(92);
        if (($win ? strcasecmp($r, $d) : strcmp($r, $d)) === 0) return '';
        if (($win ? strncasecmp($r, $d . '/', strlen($d) + 1) : strncmp($r, $d . '/', strlen($d) + 1)) === 0) {
            return base_valida(substr($r, strlen($d)));
        }
    }
    $dir = dirname(str_replace("\\", '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    return $dir === '/' || $dir === '.' ? '' : base_valida($dir);
}

define('BASE', detectar_base());

/* Endereço oficial e e-mail do coletivo (dá para trocar em privado/config.php sem mexer no código). */
define('URL_SITE', rtrim((string) (config()['url'] ?? 'https://ciberlurgia.org'), '/'));
define('EMAIL_CONTATO', (string) (config()['email_contato'] ?? 'contato@ciberlurgia.org'));

/** Endereço absoluto no domínio oficial (para og:image, canonical, sitemap). */
function url_absoluta(string $caminho): string {
    return URL_SITE . $caminho;
}

/** Envia e-mail em texto puro pelo mail() do servidor (Hostinger). Remetente do próprio domínio, para não cair no spam.
    Nada do visitante entra em cabeçalho sem passar por limpeza (sem quebra de linha: evita injeção de cabeçalho). */
function enviar_email(string $para, string $assunto, string $corpo, ?string $responderPara = null, string $nomeResp = ''): bool {
    if (!function_exists('mail') || !filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    $limpa = fn(string $s): string => trim(preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', $s));
    $remetente = (string) (config()['email_remetente'] ?? EMAIL_CONTATO);
    if (!filter_var($remetente, FILTER_VALIDATE_EMAIL)) return false;
    $h = [
        'From: ' . mb_encode_mimeheader('Site Ciberlurgia', 'UTF-8') . " <$remetente>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: Ciberlurgia',
    ];
    if ($responderPara !== null && filter_var($limpa($responderPara), FILTER_VALIDATE_EMAIL)) {
        $n = $limpa(str_replace(['"', '<', '>'], '', $nomeResp));
        $h[] = 'Reply-To: ' . ($n !== '' ? mb_encode_mimeheader($n, 'UTF-8') . ' ' : '') . '<' . $limpa($responderPara) . '>';
    }
    $assunto = mb_encode_mimeheader($limpa($assunto), 'UTF-8');
    $corpo = str_replace("\r\n", "\n", $corpo);
    return @mail($para, $assunto, $corpo, implode("\r\n", $h), '-f' . $remetente);
}

/** IP do visitante resumido (para limite de envio e registro, sem guardar o IP inteiro). */
function ip_resumido(): string {
    return substr(hash('sha256', 'cbl-ip|' . ($_SERVER['REMOTE_ADDR'] ?? '?')), 0, 16);
}

/** E-mail público: o do painel (Site) ou, se vazio, o oficial. */
function email_publico(): string {
    $e = trim((string) (ler_colecao('site')['email'] ?? ''));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : EMAIL_CONTATO;
}

/** Caminho interno ("/algo") -> com a base. http(s), mailto, "//", "#âncora" e relativos ficam como estão. */
function u(string $caminho): string {
    return ($caminho !== '' && $caminho[0] === '/' && !str_starts_with($caminho, '//')) ? BASE . $caminho : $caminho;
}

/** Caminho pedido, sem a base e decodificado ("/linkoteca/"). */
function caminho_requisicao(): string {
    $c = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (BASE !== '' && ($c === BASE || str_starts_with($c, BASE . '/'))) $c = substr($c, strlen(BASE));
    return $c === '' ? '/' : $c;
}

function esquemas(): array {
    static $e = null;
    return $e ??= require __DIR__ . '/esquemas.php';
}

/** Escapa para HTML (texto e atributo). */
function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function cabecalhos_seguranca(): void {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (https()) header('Strict-Transport-Security: max-age=31536000');
}

/** Só aceita http(s) absoluto ou caminho interno "/algo" (nunca "//", javascript:, data:). */
function url_segura(string $u): bool {
    $u = trim($u);
    if ($u === '') return false;
    if ($u[0] === '/') return !str_starts_with($u, '//') && !preg_match('/[\s\\\\]/', $u);
    if (!preg_match('#^https?://#i', $u)) return false;
    return filter_var($u, FILTER_VALIDATE_URL) !== false;
}

function imagem_segura(string $u): bool {
    return $u === ''
        || (bool) preg_match('#^/uploads/[a-z0-9][a-z0-9_-]{0,80}\.(webp|jpg|jpeg|png)$#', $u)
        || (bool) preg_match('#^/assets/img/[a-z0-9][a-z0-9_/-]{0,120}\.(webp|jpg|jpeg|png|svg)$#', $u);
}

/* ---------- Leitura e gravação ---------- */

function caminho_colecao(string $nome): string {
    if (!isset(esquemas()[$nome])) throw new InvalidArgumentException('Coleção desconhecida');
    return DIR_CONTEUDO . "/$nome.json";
}

function ler_colecao(string $nome): array {
    static $cache = [];
    if (isset($cache[$nome])) return $cache[$nome];
    $arq = caminho_colecao($nome);
    if (!is_file($arq)) return $cache[$nome] = [];
    $d = json_decode((string) file_get_contents($arq), true);
    return $cache[$nome] = is_array($d) ? $d : [];
}

/** Grava de forma atômica e guarda as últimas 30 versões em privado/backups. */
function gravar_colecao(string $nome, array $dados): void {
    $arq = caminho_colecao($nome);
    $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $dirb = DIR_PRIVADO . '/backups';
    if (!is_dir($dirb)) mkdir($dirb, 0750, true);
    $trava = fopen(DIR_PRIVADO . '/.trava-conteudo', 'c');
    flock($trava, LOCK_EX);
    try {
        if (is_file($arq)) copy($arq, "$dirb/$nome-" . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.json');
        $tmp = "$arq." . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($tmp, $json . "\n");
        rename($tmp, $arq);
        $velhos = glob("$dirb/$nome-*.json") ?: [];
        sort($velhos);
        foreach (array_slice($velhos, 0, max(0, count($velhos) - 30)) as $v) unlink($v);
    } finally {
        flock($trava, LOCK_UN);
        fclose($trava);
    }
}

/* ---------- Validação pelo esquema ---------- */

/**
 * Valida e normaliza os dados de uma coleção. Devolve [dados_limpos, erros].
 * Campos fora do esquema são descartados.
 */
function validar_colecao(string $nome, mixed $dados): array {
    $esq = esquemas()[$nome] ?? null;
    if (!$esq) return [null, ['Coleção desconhecida.']];
    $erros = [];
    if ($esq['tipo'] === 'objeto') {
        if (!is_array($dados) || array_is_list($dados) && $dados !== []) return [null, ['Formato inválido.']];
        return [validar_item($esq['campos'], $dados, '', $erros), $erros];
    }
    if (!is_array($dados) || !array_is_list($dados)) return [null, ['A coleção precisa ser uma lista.']];
    if (count($dados) > ($esq['max_itens'] ?? 2000)) return [null, ['Itens demais.']];
    $limpos = [];
    $chaves = [];
    foreach ($dados as $i => $item) {
        if (!is_array($item)) { $erros[] = "Item " . ($i + 1) . ": formato inválido."; continue; }
        $l = validar_item($esq['campos'], $item, 'Item ' . ($i + 1) . ': ', $erros);
        $k = $l[$esq['chave']] ?? '';
        if (isset($chaves[$k])) $erros[] = "Item " . ($i + 1) . ": identificador \"$k\" repetido.";
        $chaves[$k] = true;
        $limpos[] = $l;
    }
    return [$limpos, $erros];
}

function validar_item(array $campos, array $item, string $pref, array &$erros): array {
    $out = [];
    foreach ($campos as $c) {
        $n = $c['nome'];
        $rot = $pref . $c['rotulo'];
        $v = $item[$n] ?? null;
        $max = $c['max'] ?? match ($c['tipo']) { 'markdown' => 20000, 'textarea' => 3000, default => 300 };
        switch ($c['tipo']) {
            case 'booleano':
                $out[$n] = (bool) $v;
                continue 2;
            case 'numero':
                $out[$n] = is_numeric($v) ? (int) $v : 0;
                continue 2;
            case 'tags':
                $lista = is_array($v) ? $v : preg_split('/\s*,\s*/', (string) $v);
                $lista = array_values(array_unique(array_filter(array_map(
                    fn($t) => mb_substr(trim(mb_strtolower(strip_tags((string) $t))), 0, 40), $lista))));
                $out[$n] = array_slice($lista, 0, 20);
                continue 2;
            case 'links':
                $lista = [];
                foreach (is_array($v) ? $v : [] as $par) {
                    $r = trim((string) ($par['rotulo'] ?? ''));
                    $u = trim((string) ($par['url'] ?? ''));
                    if ($r === '' && $u === '') continue;
                    if (!url_segura($u) && !preg_match('/^mailto:[^\s@<>"]+@[^\s@<>"]+$/i', $u)) { $erros[] = "$rot: link \"$u\" inválido."; continue; }
                    $lista[] = ['rotulo' => mb_substr($r, 0, 60), 'url' => mb_substr($u, 0, 500)];
                }
                $out[$n] = array_slice($lista, 0, 12);
                continue 2;
        }
        $v = is_scalar($v) ? trim((string) $v) : '';
        $v = str_replace("\r\n", "\n", $v);
        if ($v === '' && !empty($c['obrigatorio'])) $erros[] = "$rot é obrigatório.";
        if (mb_strlen($v) > $max) { $erros[] = "$rot passou de $max caracteres."; $v = mb_substr($v, 0, $max); }
        if ($v !== '') {
            $ok = match ($c['tipo']) {
                'slug' => (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $v) && strlen($v) <= 80,
                'url' => url_segura($v),
                'email' => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
                'imagem' => imagem_segura($v),
                'data' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false,
                'selecao' => in_array($v, $c['opcoes'], true),
                'referencia' => in_array($v, array_column(ler_colecao($c['colecao']), $c['campo']), true),
                default => true,
            };
            if (!$ok) $erros[] = "$rot: valor \"" . mb_substr($v, 0, 60) . "\" inválido.";
        }
        $out[$n] = $v;
    }
    return $out;
}

/* ---------- Texto ---------- */

/** Markdown enxuto: ## e ### títulos, - listas, > citação, **negrito**, *itálico*, [texto](url). Escapa tudo antes. */
function md(?string $t): string {
    $t = trim(str_replace("\r\n", "\n", (string) $t));
    if ($t === '') return '';
    $html = '';
    foreach (preg_split("/\n{2,}/", $t) as $b) {
        $linhas = explode("\n", $b);
        if (preg_match('/^(#{2,3}) (.+)$/s', $b, $m)) {
            $h = strlen($m[1]);
            $html .= "<h$h>" . md_inline($m[2]) . "</h$h>\n";
        } elseif (count(array_filter($linhas, fn($l) => preg_match('/^[-*] /', $l))) === count($linhas)) {
            $html .= '<ul>' . implode('', array_map(fn($l) => '<li>' . md_inline(substr($l, 2)) . '</li>', $linhas)) . "</ul>\n";
        } elseif (str_starts_with($b, '> ')) {
            /* inline no bloco inteiro: negrito/itálico podem atravessar quebras de linha */
            $html .= '<blockquote><p>' . str_replace("\n", '<br>', md_inline(implode("\n", array_map(fn($l) => preg_replace('/^> ?/', '', $l), $linhas)))) . "</p></blockquote>\n";
        } else {
            $html .= '<p>' . str_replace("\n", '<br>', md_inline($b)) . "</p>\n";
        }
    }
    return $html;
}

function md_inline(string $s): string {
    $s = e($s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $u = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!url_segura($u)) return $m[1];
        $ext = str_starts_with($u, 'http') ? ' rel="noopener" target="_blank"' : '';
        return '<a href="' . e(u($u)) . '"' . $ext . '>' . $m[1] . '</a>';
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    return preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s);
}

function data_br(string $iso): string {
    $m = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $t = strtotime($iso);
    return $t ? date('j', $t) . ' ' . $m[(int) date('n', $t)] . ' ' . date('Y', $t) : '';
}

function versao_asset(string $caminho): string {
    $arq = RAIZ . $caminho;
    return u($caminho) . (is_file($arq) ? '?v=' . substr(md5((string) filemtime($arq)), 0, 8) : '');
}
