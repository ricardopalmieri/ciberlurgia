<?php
/* Contagem anônima de cliques e buscas da Linkoteca. Sem cookie; o IP nunca é gravado,
   só um hash com sal diário. Incluído por index.php em /api/contagem (sem declare: o index já declarou).
   Responde 204 sem corpo; 405 se não for POST. */

function contagem_ler(string $arq): array {
    $d = is_file($arq) ? json_decode((string) file_get_contents($arq), true) : null;
    return is_array($d) ? $d : [];
}

/* Gravação atômica: arquivo temporário e rename. Quem chama mantém a trava. */
function contagem_gravar(string $arq, array $dados): void {
    $json = json_encode($dados, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $tmp = $arq . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $json . "\n") === false) return;
    if (!rename($tmp, $arq)) @unlink($tmp);
}

/* Letras, números, espaço e hífen; minúsculas; espaços simples; máximo 60 caracteres. */
function contagem_termo(string $t): string {
    $t = mb_strtolower(trim($t), 'UTF-8');
    $t = (string) preg_replace('/[^\p{L}\p{N} -]+/u', '', $t);
    $t = trim((string) preg_replace('/ +/', ' ', $t));
    return mb_substr($t, 0, 60, 'UTF-8');
}

function contagem_atender(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        return;
    }
    http_response_code(204);

    /* Só aceita quem vem de uma página do próprio site (Origin ou Referer do mesmo host). */
    $fonte = ($_SERVER['HTTP_ORIGIN'] ?? '') !== '' ? $_SERVER['HTTP_ORIGIN'] : ($_SERVER['HTTP_REFERER'] ?? '');
    $partes = $fonte !== '' ? parse_url($fonte) : false;
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $deles = is_array($partes) && isset($partes['host'])
        ? strtolower($partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '')) : '';
    if ($deles === '' || $host === '' || $deles !== $host) return;

    $corpo = (string) file_get_contents('php://input', false, null, 0, 2049);
    if ($corpo === '' || strlen($corpo) > 2048) return;
    $d = json_decode($corpo, true);
    if (!is_array($d)) return;

    $ids = array_merge(array_column(ler_colecao('links'), 'id'), array_column(ler_colecao('ferramentas'), 'id'));
    $validos = array_flip(array_map('strval', $ids));

    $tipo = $d['tipo'] ?? '';
    if ($tipo === 'clique') {
        $id = $d['id'] ?? null;
        if (!is_string($id) && !is_int($id)) return;
        $id = (string) $id;
        if (!isset($validos[$id])) return;
        $alvo = null;
    } elseif ($tipo === 'busca') {
        $termo = contagem_termo((string) ($d['termo'] ?? ''));
        if ($termo === '') return;
        $alvo = $termo;
    } else {
        return;
    }

    $ritmoArq = DIR_PRIVADO . '/contagem-ritmo.json';
    $contArq = DIR_PRIVADO . '/contagem.json';
    $trava = fopen(DIR_PRIVADO . '/.trava-contagem', 'c');
    if ($trava === false) return;
    flock($trava, LOCK_EX);
    try {
        /* Limite por IP: 30 requisições por minuto. Guarda só hash(IP + dia) e o minuto atual.
           IPv6 usa o prefixo /64. O mesmo arquivo (diário) guarda o teto de cliques por link. */
        $minuto = date('YmdHi');
        $dia = date('Y-m-d');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (str_contains($ip, ':')) {
            $bin = @inet_pton($ip);
            if ($bin !== false && strlen($bin) === 16) $ip = bin2hex(substr($bin, 0, 8)) . '/64';
        }
        $chave = hash('sha256', $ip . '|' . $dia);
        $todo = contagem_ler($ritmoArq);
        $teto = (isset($todo['_dia']) && $todo['_dia'] === $dia && is_array($todo['_cl'] ?? null)) ? $todo['_cl'] : [];
        $ritmo = array_filter($todo, fn($r, $k) => $k !== '_dia' && $k !== '_cl' && is_array($r) && ($r['m'] ?? '') === $minuto, ARRAY_FILTER_USE_BOTH);
        $n = (int) ($ritmo[$chave]['n'] ?? 0) + 1;
        if ($n > 30) return; /* já passou do limite: não regrava o arquivo */
        $ritmo[$chave] = ['m' => $minuto, 'n' => $n];
        if ($alvo === null) {
            if ((int) ($teto[$id] ?? 0) >= 50) return; /* teto diário por link */
            $teto[$id] = (int) ($teto[$id] ?? 0) + 1;
        }
        $ritmo['_dia'] = $dia;
        $ritmo['_cl'] = $teto;
        contagem_gravar($ritmoArq, $ritmo);

        $dados = contagem_ler($contArq);
        $cliques = is_array($dados['cliques'] ?? null) ? $dados['cliques'] : [];
        $buscas = is_array($dados['buscas'] ?? null) ? $dados['buscas'] : [];
        if ($alvo === null) {
            $cliques[$id] = (int) ($cliques[$id] ?? 0) + 1;
        } else {
            $buscas[$alvo] = (int) ($buscas[$alvo] ?? 0) + 1;
            if (count($buscas) > 500) {
                arsort($buscas);
                $buscas = array_slice($buscas, 0, 500, true);
            }
        }
        contagem_gravar($contArq, ['cliques' => $cliques, 'buscas' => $buscas]);
    } finally {
        flock($trava, LOCK_UN);
        fclose($trava);
    }
}

contagem_atender();
