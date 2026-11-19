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
function adm_erro_senha(string $s, string $usuario = ''): ?string {
    if (mb_strlen($s) < ADM_SENHA_MIN) return 'A senha precisa ter pelo menos ' . ADM_SENHA_MIN . ' caracteres.';
    if (mb_strlen($s) > ADM_SENHA_MAX) return 'Senha longa demais (máximo ' . ADM_SENHA_MAX . ').';
    $min = mb_strtolower($s);
    if (count(array_unique(mb_str_split($min))) < 6) return 'Senha repetitiva demais. Misture mais letras, números ou palavras.';
    foreach (['ciberlurgia', 'cccd', 'senha', 'password', 'admin', '123456', 'qwerty', 'abcdef', 'santoandre', 'noisetupi'] as $ruim) {
        if (str_contains(preg_replace('/[^a-z0-9]/', '', $min) ?? '', $ruim)) return 'A senha não pode ter "' . $ruim . '" dentro. É a primeira coisa que um robô tenta.';
    }
    if ($usuario !== '' && mb_strlen($usuario) >= 3 && str_contains($min, mb_strtolower($usuario))) return 'A senha não pode conter o nome de usuário.';
    return null;
}

/* ---------- Registro de acessos e alerta de aparelho novo ---------- */

/** IP mascarado para o registro: 189.12.x.x (IPv4) ou os três primeiros grupos (IPv6). */
function adm_ip_mascarado(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '?');
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $p = explode('.', $ip); return "$p[0].$p[1].x.x"; }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return implode(':', array_slice(explode(':', $ip), 0, 3)) . ':…';
    return '?';
}

/** "Chrome no Android", "Firefox no Windows"... só para a pessoa reconhecer o aparelho. */
function adm_aparelho(): string {
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $nav = match (true) {
        str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
        str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari',
        default => 'Navegador desconhecido',
    };
    $so = match (true) {
        str_contains($ua, 'Android') => 'Android', (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iPhone/iPad',
        str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'CrOS') => 'Chromebook',
        str_contains($ua, 'Linux') => 'Linux', default => 'sistema desconhecido',
    };
    return "$nav no $so";
}

/** Lê/altera privado/acessos.json com trava: {eventos:[{t,u,ok,ip,ap}], aparelhos:{usuario:{hash:t}}}. */
function adm_acessos(?callable $fn = null): array {
    $h = fopen(DIR_PRIVADO . '/acessos.json', 'c+');
    if (!$h) return ['eventos' => [], 'aparelhos' => []];
    flock($h, $fn ? LOCK_EX : LOCK_SH);
    try {
        $d = json_decode((string) stream_get_contents($h), true);
        $d = is_array($d) ? $d + ['eventos' => [], 'aparelhos' => []] : ['eventos' => [], 'aparelhos' => []];
        if ($fn) {
            $d = $fn($d);
            /* robô errando senha não pode apagar o histórico de entradas certas: limites separados */
            $oks = array_keys(array_filter($d['eventos'], fn($e) => !empty($e['ok'])));
            $falhas = array_keys(array_filter($d['eventos'], fn($e) => empty($e['ok'])));
            $fica = array_flip(array_merge(array_slice($oks, -60), array_slice($falhas, -100)));
            $d['eventos'] = array_values(array_intersect_key($d['eventos'], $fica));
            rewind($h);
            ftruncate($h, 0);
            fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE));
            fflush($h);
        }
        return $d;
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Guarda a tentativa. Num login certo vindo de aparelho/rede nova, avisa por e-mail. */
function adm_registrar_acesso(string $usuario, bool $ok): void {
    $u = adm_nome_valido($usuario) ? $usuario : '(inválido)';
    $ev = ['t' => time(), 'u' => $u, 'ok' => $ok, 'ip' => adm_ip_mascarado(), 'ap' => adm_aparelho()];
    $novo = false;
    adm_acessos(function (array $d) use ($ev, $ok, $u, &$novo) {
        $d['eventos'][] = $ev;
        if ($ok) {
            $k = substr(hash('sha256', $u . '|' . $ev['ap'] . '|' . $ev['ip']), 0, 20);
            $novo = !isset($d['aparelhos'][$u][$k]);
            $d['aparelhos'][$u][$k] = $ev['t'];
            if (count($d['aparelhos'][$u]) > 30) { asort($d['aparelhos'][$u]); $d['aparelhos'][$u] = array_slice($d['aparelhos'][$u], -30, null, true); }
        }
        return $d;
    });
    $para = (string) (config()['email_alertas'] ?? EMAIL_CONTATO);
    if ($ok && $novo && $para !== '') {
        enviar_email($para, "[Painel] Entrada de $u em aparelho novo",
            "Alguém entrou no painel do site da Ciberlurgia com o usuário \"$u\".\n\n"
            . "Quando: " . date('d/m/Y H:i', $ev['t']) . "\nAparelho: {$ev['ap']}\nRede: {$ev['ip']}\n\n"
            . "Se foi você ou alguém do coletivo, pode ignorar.\n"
            . "Se ninguém reconhece, troque a senha agora em " . url_absoluta('/admin/usuarios') . " (isso derruba as outras sessões) e avise o grupo.\n");
    }
}

/** Último login certo do usuário antes do atual (para o painel mostrar). */
function adm_acesso_anterior(string $usuario): ?array {
    $oks = array_values(array_filter(adm_acessos()['eventos'], fn($e) => ($e['u'] ?? '') === $usuario && !empty($e['ok'])));
    return count($oks) >= 2 ? $oks[count($oks) - 2] : null;
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
