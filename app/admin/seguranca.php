<?php
/* Painel admin: sessão, login, usuários, força bruta e CSRF.
   Carregado só por app/admin/roteador.php (que o index.php chama para /admin). */
declare(strict_types=1);

const ADM_INATIVIDADE = 1800;   // 30 min sem mexer
const ADM_ABSOLUTA = 28800;     // 8 h no máximo
const ADM_MAX_FALHAS = 5;
const ADM_BLOQUEIO = 900;       // 15 min
const ADM_SENHA_MIN = 12;
const ADM_SENHA_MAX = 200;

/* ---------- Respostas e cabeçalhos ---------- */

function adm_cabecalhos(): void {
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
}

function adm_json_flags(): int {
    return JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
}

function adm_json_str(mixed $d): string {
    return json_encode($d, adm_json_flags());
}

function adm_json(array $d, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo adm_json_str($d);
    exit;
}

function adm_eh_api(): bool {
    $c = caminho_requisicao();
    return str_starts_with($c, '/admin/api/');
}

function adm_redirecionar(string $para): never {
    header('Location: ' . u($para), true, 303);
    exit;
}

function adm_flash(?string $msg = null, string $tipo = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['m' => $msg, 't' => $tipo]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

/* ---------- Sessão ---------- */

/** Há cookie de sessão com id plausível e arquivo existente? (evita criar arquivo por GET anônimo ou cookie falso) */
function adm_tem_sessao(): bool {
    $id = $_COOKIE['cbl_adm'] ?? '';
    return is_string($id) && preg_match('/^[A-Za-z0-9,-]{22,128}$/D', $id) === 1
        && is_file(DIR_PRIVADO . '/sessoes/sess_' . $id);
}

function adm_iniciar_sessao(string $rota = '', bool $post = false): void {
    $dir = DIR_PRIVADO . '/sessoes';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) ADM_ABSOLUTA);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '50');
    session_save_path($dir);
    session_cache_limiter('');
    session_name('cbl_adm');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => BASE . '/admin', 'secure' => https(),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    $login = $post && ($rota === 'entrar' || $rota === 'instalar');
    if (!adm_tem_sessao() && !$login) {
        /* Sem sessão e sem login: nada de arquivo em disco. O CSRF da tela anônima vai por cookie assinado. */
        $_SESSION = [];
        adm_csrf_cookie();
        return;
    }
    session_start();
    $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['uid'])) {
        $agora = time();
        $velha = $agora - (int) ($_SESSION['ultimo'] ?? 0) > ADM_INATIVIDADE
            || $agora - (int) ($_SESSION['inicio'] ?? 0) > ADM_ABSOLUTA
            || !hash_equals((string) ($_SESSION['ua'] ?? ''), $ua)
            || !isset(adm_usuarios()[$_SESSION['uid']])
            || !hash_equals((string) ($_SESSION['ph'] ?? ''), adm_hash_da_senha((string) $_SESSION['uid']));
        if ($velha) {
            adm_encerrar_sessao();
            session_start();
        } else {
            $_SESSION['ultimo'] = $agora;
        }
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if (!isset($_SESSION['uid'])) adm_csrf_cookie();
}

/** sha256 do hash de senha atual do usuário: trocar a senha invalida as outras sessões. */
function adm_hash_da_senha(string $uid): string {
    return hash('sha256', (string) (adm_usuarios()[$uid]['hash'] ?? ''));
}

function adm_encerrar_sessao(): void {
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_destroy();
    unset($_COOKIE[session_name()]);
}

function adm_logado(): ?string {
    return isset($_SESSION['uid']) && is_string($_SESSION['uid']) ? $_SESSION['uid'] : null;
}

function adm_entrar_na_sessao(string $usuario): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $usuario;
    $_SESSION['inicio'] = $_SESSION['ultimo'] = time();
    $_SESSION['ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['ph'] = adm_hash_da_senha($usuario);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------- CSRF ---------- */

/** Segredo do HMAC em privado/segredo.php (criado com random_bytes na primeira vez). */
function adm_segredo(): string {
    $f = DIR_PRIVADO . '/segredo.php';
    for ($i = 0; $i < 3; $i++) {
        if (is_file($f)) {
            $v = (static function (string $f) { return include $f; })($f);
            if (is_string($v) && strlen($v) >= 32) return $v;
        }
        $h = @fopen($f, 'x');
        if ($h !== false) {
            fwrite($h, "<?php return '" . bin2hex(random_bytes(32)) . "';
");
            fclose($h);
            @chmod($f, 0600);
            if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
        } else {
            usleep(50000);
        }
    }
    throw new RuntimeException('Sem segredo');
}

function adm_csrf_valido_cookie(string $v): bool {
    $p = explode('.', $v);
    return count($p) === 2 && preg_match('/^[0-9a-f]{64}$/D', $p[0]) === 1
        && hash_equals(hash_hmac('sha256', $p[0], adm_segredo()), $p[1]);
}

/** Telas anônimas: cookie CSRF separado e assinado (double-submit), sem sessão em disco. */
function adm_csrf_cookie(): string {
    $v = $_COOKIE['cbl_csrf'] ?? '';
    if (is_string($v) && adm_csrf_valido_cookie($v)) return $v;
    $r = bin2hex(random_bytes(32));
    $v = $r . '.' . hash_hmac('sha256', $r, adm_segredo());
    $_COOKIE['cbl_csrf'] = $v;
    if (!headers_sent()) {
        setcookie('cbl_csrf', $v, [
            'expires' => 0, 'path' => BASE . '/admin', 'secure' => https(),
            'httponly' => true, 'samesite' => 'Strict',
        ]);
    }
    return $v;
}

function adm_csrf(): string {
    if (adm_logado()) return (string) ($_SESSION['csrf'] ?? '');
    return adm_csrf_cookie();
}

function adm_negar(int $status, string $msg): never {
    if (adm_eh_api()) adm_json(['ok' => false, 'erro' => $msg], $status);
    http_response_code($status);
    adm_vista_erro($status, $msg);
    exit;
}

/** Todo POST passa por aqui: Origin/Referer do mesmo host + token da sessão. */
function adm_exigir_post_seguro(): void {
    $fonte = ($_SERVER['HTTP_ORIGIN'] ?? '') !== '' ? $_SERVER['HTTP_ORIGIN'] : ($_SERVER['HTTP_REFERER'] ?? '');
    $partes = $fonte !== '' ? parse_url($fonte) : false;
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $deles = is_array($partes) && isset($partes['host'])
        ? strtolower($partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '')) : '';
    if ($deles === '' || $host === '' || $deles !== $host) adm_negar(403, 'Origem da requisição não confere.');
    $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $esperado = adm_logado() ? (string) ($_SESSION['csrf'] ?? '')
        : (adm_csrf_valido_cookie((string) ($_COOKIE['cbl_csrf'] ?? '')) ? (string) $_COOKIE['cbl_csrf'] : '');
    if ($token === '' || $esperado === '' || !hash_equals($esperado, $token)) adm_negar(403, 'Token de segurança inválido. Recarregue a página e tente de novo.');
}

/* ---------- Usuários ---------- */

function adm_arquivo_usuarios(): string {
    return DIR_PRIVADO . '/usuarios.php';
}

function adm_usuarios(): array {
    $f = adm_arquivo_usuarios();
    if (!is_file($f)) return [];
    $u = (static function (string $f) { return include $f; })($f);
    return is_array($u) ? $u : [];
}

function adm_algoritmo(): string|int {
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function adm_hash_senha(string $senha): string {
    return password_hash($senha, adm_algoritmo());
}

/** Lê, deixa $fn alterar e grava de forma atômica. $fn devolve o novo array de usuários. */
function adm_alterar_usuarios(callable $fn): void {
    $trava = fopen(DIR_PRIVADO . '/.trava-usuarios', 'c');
    flock($trava, LOCK_EX);
    try {
        $novo = $fn(adm_usuarios());
        $f = adm_arquivo_usuarios();
        $tmp = $f . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $src = "<?php\n// Gerado pelo painel. Não edite à mão.\nreturn " . var_export($novo, true) . ";\n";
        file_put_contents($tmp, $src);
        @chmod($tmp, 0600);
        rename($tmp, $f);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
    } finally {
        flock($trava, LOCK_UN);
        fclose($trava);
    }
}

function adm_nome_valido(string $n): bool {
    return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $n);
}

function adm_normalizar_nome(string $n): string {
    return mb_substr(mb_strtolower(trim($n)), 0, 64);
}

/** Devolve mensagem de erro ou null se a senha serve. */
function adm_erro_senha(string $s): ?string {
    if (mb_strlen($s) < ADM_SENHA_MIN) return 'A senha precisa ter pelo menos ' . ADM_SENHA_MIN . ' caracteres.';
    if (mb_strlen($s) > ADM_SENHA_MAX) return 'Senha longa demais (máximo ' . ADM_SENHA_MAX . ').';
    return null;
}

/** Hash de mentira para gastar o mesmo tempo quando o usuário não existe. */
function adm_hash_falso(): string {
    $f = DIR_PRIVADO . '/hash-falso.txt';
    $h = is_file($f) ? trim((string) file_get_contents($f)) : '';
    if ($h === '' || password_needs_rehash($h, adm_algoritmo())) {
        $h = adm_hash_senha(bin2hex(random_bytes(16)));
        @file_put_contents($f, $h . "\n");
    }
    return $h;
}

/* ---------- Instalação ---------- */

function adm_instalacao_disponivel(): bool {
    return !is_file(adm_arquivo_usuarios()) && is_file(DIR_PRIVADO . '/INSTALAR');
}

/* ---------- Força bruta ---------- */

function adm_chave_ip(): string {
    return 'i' . substr(hash('sha256', 'cbl|' . ($_SERVER['REMOTE_ADDR'] ?? '?')), 0, 24);
}

/** Chave usuário+IP: quem erra a senha de "admin" de outro IP não trava o admin de verdade. */
function adm_chave_usuario(string $u): string {
    return 'u' . substr(hash('sha256', 'cbl|' . ($_SERVER['REMOTE_ADDR'] ?? '?') . '|' . adm_normalizar_nome($u)), 0, 24);
}

/** Só para o atraso progressivo por usuário (nunca bloqueia). */
function adm_chave_atraso(string $u): string {
    return 'p' . substr(hash('sha256', 'cbl|' . adm_normalizar_nome($u)), 0, 24);
}

/** Abre privado/tentativas.json com flock, limpa entradas velhas e deixa $fn mexer (por referência). */
function adm_tentativas(callable $fn): mixed {
    $f = fopen(DIR_PRIVADO . '/tentativas.json', 'c+');
    flock($f, LOCK_EX);
    try {
        $d = json_decode((string) stream_get_contents($f), true);
        if (!is_array($d)) $d = [];
        $agora = time();
        foreach ($d as $k => $v) {
            if (!is_array($v) || (($v['ate'] ?? 0) < $agora && ($v['t'] ?? 0) < $agora - ADM_BLOQUEIO)) unset($d[$k]);
        }
        if (count($d) > 5000) { uasort($d, fn($a, $b) => ($b['t'] ?? 0) <=> ($a['t'] ?? 0)); $d = array_slice($d, 0, 3000, true); }
        $r = $fn($d, $agora);
        rewind($f);
        ftruncate($f, 0);
        fwrite($f, json_encode($d));
        fflush($f);
        return $r;
    } finally {
        flock($f, LOCK_UN);
        fclose($f);
    }
}

/** Reserva uma tentativa (+1) ANTES de conferir a senha, dentro do flock, para que requisições
    paralelas não passem todas. Devolve os segundos de bloqueio (0 = pode tentar). Se liberado,
    aplica o atraso progressivo leve (até 2 s) por usuário. */
function adm_reservar_tentativa(string $usuario): int {
    $chaves = [adm_chave_ip(), adm_chave_usuario($usuario)];
    $kp = adm_chave_atraso($usuario);
    [$espera, $atrasoUs] = adm_tentativas(function (array &$d, int $agora) use ($chaves, $kp) {
        foreach ($chaves as $k) {
            $e = $d[$k] ?? ['n' => 0, 't' => 0];
            if (($e['t'] ?? 0) >= $agora - ADM_BLOQUEIO && ($e['n'] ?? 0) >= ADM_MAX_FALHAS) {
                return [$e['t'] + ADM_BLOQUEIO - $agora, 0];
            }
        }
        foreach ($chaves as $k) {
            $e = $d[$k] ?? ['n' => 0, 't' => 0];
            if (($e['t'] ?? 0) < $agora - ADM_BLOQUEIO) $e['n'] = 0;
            $e['n'] = (int) $e['n'] + 1;
            $e['t'] = $agora;
            $d[$k] = $e;
        }
        $p = $d[$kp] ?? ['n' => 0, 't' => 0];
        if (($p['t'] ?? 0) < $agora - ADM_BLOQUEIO) $p['n'] = 0;
        $atraso = min(2000000, max(0, (int) $p['n'] - 1) * 250000);
        $p['n'] = (int) $p['n'] + 1;
        $p['t'] = $agora;
        $d[$kp] = $p;
        return [0, $atraso];
    });
    if ($espera <= 0 && $atrasoUs > 0) usleep($atrasoUs);
    return max(0, (int) $espera);
}

/** Senha certa: zera a chave usuário+IP e o atraso do usuário e devolve a reserva feita no IP. */
function adm_liberar_tentativa(string $usuario): void {
    $ki = adm_chave_ip();
    $ku = adm_chave_usuario($usuario);
    $kp = adm_chave_atraso($usuario);
    adm_tentativas(function (array &$d) use ($ki, $ku, $kp) {
        unset($d[$ku], $d[$kp]);
        if (isset($d[$ki]) && ($d[$ki]['n'] ?? 0) > 0) $d[$ki]['n']--;
    });
}
