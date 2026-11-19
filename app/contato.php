<?php
/* Página de contato: formulário que chega no e-mail do coletivo e fica guardado no painel (/admin/mensagens).
   Quem chama: index.php (rota /contato/) e app/admin/vistas.php (lista de mensagens).
   Antispam sem captcha de terceiros (nada de Google, nada de rastreio, funciona sem JS):
   1. pote de mel: campo escondido que só robô preenche;
   2. ficha assinada (HMAC) com a hora em que o formulário abriu: rápido demais (< 4 s) ou velho demais (> 2 h) não passa,
      e cada ficha vale uma vez só;
   3. limite por IP (3 a cada 10 min, 8 por dia) e teto geral (60 por dia);
   4. conteúdo: links demais, BBCode/HTML ou texto curto demais são recusados.
   Robô pego nos itens 1 e 2 recebe "enviado" de mentira, para não aprender o que deu errado. */
declare(strict_types=1);

require_once __DIR__ . '/admin/seguranca.php';   // adm_segredo(): mesmo segredo do painel, em privado/segredo.php

const CONTATO_MIN_SEG = 4;
const CONTATO_MAX_SEG = 7200;
const CONTATO_POR_IP_10MIN = 3;
const CONTATO_POR_IP_DIA = 8;
const CONTATO_GERAL_DIA = 60;
const CONTATO_MAX_LINKS = 3;
const CONTATO_GUARDAR = 300;   // mensagens guardadas no painel (as mais antigas saem)

function contato_dir(): string {
    $d = DIR_PRIVADO . '/mensagens';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return $d;
}

/* ---------- Ficha assinada ---------- */

function contato_ficha(): string {
    $base = time() . '.' . bin2hex(random_bytes(8));
    return $base . '.' . hash_hmac('sha256', 'contato|' . $base, adm_segredo());
}

/** 'ok', 'rapido', 'velho' ou 'invalida'. Marca a ficha como usada. */
function contato_conferir_ficha(string $f): string {
    $p = explode('.', $f);
    if (count($p) !== 3 || !ctype_digit($p[0]) || !preg_match('/^[0-9a-f]{16}$/D', $p[1]) || !preg_match('/^[0-9a-f]{64}$/D', $p[2])) return 'invalida';
    if (!hash_equals(hash_hmac('sha256', 'contato|' . $p[0] . '.' . $p[1], adm_segredo()), $p[2])) return 'invalida';
    $idade = time() - (int) $p[0];
    if ($idade < CONTATO_MIN_SEG) return 'rapido';
    if ($idade > CONTATO_MAX_SEG) return 'velho';
    $usada = contato_registro('usadas', function (array &$d, int $agora) use ($p) {
        foreach ($d as $k => $t) if ($t < $agora - CONTATO_MAX_SEG) unset($d[$k]);
        if (isset($d[$p[1]])) return true;
        $d[$p[1]] = $agora;
        return false;
    });
    return $usada ? 'invalida' : 'ok';
}

/** Arquivo JSON em privado/ com trava; $fn mexe nos dados por referência. */
function contato_registro(string $nome, callable $fn): mixed {
    $h = fopen(DIR_PRIVADO . "/contato-$nome.json", 'c+');
    if (!$h) return null;
    flock($h, LOCK_EX);
    try {
        $d = json_decode((string) stream_get_contents($h), true);
        if (!is_array($d)) $d = [];
        $r = $fn($d, time());
        rewind($h);
        ftruncate($h, 0);
        fwrite($h, json_encode($d));
        fflush($h);
        return $r;
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Conta o envio no limite por IP e no teto geral. Devolve null (pode) ou a mensagem de espera. */
function contato_ritmo(): ?string {
    $ip = ip_resumido();
    return contato_registro('ritmo', function (array &$d, int $agora) use ($ip) {
        foreach ($d as $k => $ts) {
            $d[$k] = array_values(array_filter((array) $ts, fn($t) => is_int($t) && $t > $agora - 86400));
            if (!$d[$k]) unset($d[$k]);
        }
        $meus = $d[$ip] ?? [];
        $geral = array_sum(array_map('count', $d));
        if (count(array_filter($meus, fn($t) => $t > $agora - 600)) >= CONTATO_POR_IP_10MIN) return 'Você mandou várias mensagens seguidas. Espere uns minutos e tente de novo.';
        if (count($meus) >= CONTATO_POR_IP_DIA) return 'Chegamos no limite de mensagens por hoje vindas daqui. Tente amanhã ou escreva direto para ' . EMAIL_CONTATO . '.';
        if ($geral >= CONTATO_GERAL_DIA) return 'O formulário está fechado por hoje. Escreva direto para ' . EMAIL_CONTATO . '.';
        $meus[] = $agora;
        $d[$ip] = $meus;
        return null;
    });
}

/* ---------- Processamento ---------- */

/** Devolve [erros, valores, enviado]. */
function contato_processar(): array {
    $v = [
        'nome' => trim((string) ($_POST['nome'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'assunto' => trim((string) ($_POST['assunto'] ?? '')),
        'mensagem' => trim(str_replace("\r\n", "\n", (string) ($_POST['mensagem'] ?? ''))),
    ];
    foreach ($v as $k => $x) {
        $x = mb_check_encoding($x, 'UTF-8') ? $x : mb_convert_encoding($x, 'UTF-8', 'Windows-1252');   // texto que não veio em UTF-8 não some
        $v[$k] = mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $x) ?? '', 0, $k === 'mensagem' ? 5000 : 200);
    }

    // Origem: se o navegador disse de onde veio, tem que ser daqui.
    $origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origem !== '' && strtolower((string) parse_url($origem, PHP_URL_HOST)) !== strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''))) {
        return [[], $v, true];
    }
    // 1. Pote de mel e 2. ficha
    if (trim((string) ($_POST['site'] ?? '')) !== '') return [[], $v, true];
    $ficha = contato_conferir_ficha((string) ($_POST['ficha'] ?? ''));
    if ($ficha === 'invalida' || $ficha === 'rapido') return [[], $v, true];

    $erros = [];
    if ($ficha === 'velho') $erros['geral'] = 'O formulário ficou aberto muito tempo. Confira o texto e envie de novo.';
    if (mb_strlen($v['nome']) < 2) $erros['nome'] = 'Diga seu nome.';
    elseif (mb_strlen($v['nome']) > 80) $erros['nome'] = 'Nome longo demais (até 80 letras).';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 120) $erros['email'] = 'Confira o e-mail: é por ele que a gente responde.';
    if (mb_strlen($v['assunto']) > 120) $erros['assunto'] = 'Assunto longo demais (até 120 letras).';
    $n = mb_strlen($v['mensagem']);
    if ($n < 10) $erros['mensagem'] = 'Escreva um pouco mais (pelo menos 10 letras).';
    elseif ($n > 4000) $erros['mensagem'] = 'Mensagem longa demais (até 4.000 letras).';
    // 4. Conteúdo
    $tudo = $v['nome'] . ' ' . $v['assunto'] . ' ' . $v['mensagem'];
    if (preg_match_all('#https?://|www\.#i', $tudo) > CONTATO_MAX_LINKS) $erros['mensagem'] = 'Muitos links na mensagem. Mande no máximo ' . CONTATO_MAX_LINKS . '.';
    if (preg_match('#\[/?url|<\s*a\s|</\s*a\s*>|\[link=#i', $tudo)) $erros['mensagem'] = 'Tire os códigos de link (HTML ou [url]) e mande só o endereço.';
    if (preg_match('#https?://|www\.#i', $v['nome'])) $erros['nome'] = 'O nome não pode ter link.';
    if ($erros) return [$erros, $v, false];

    // 3. Ritmo (só conta quem passou nas outras regras)
    $espera = contato_ritmo();
    if ($espera !== null) return [['geral' => $espera], $v, false];

    contato_guardar($v);
    return [[], $v, true];
}

function contato_guardar(array $v): void {
    $quando = date('c');
    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $corpo = "Mensagem enviada pelo formulário de " . url_absoluta('/contato/') . "\n\n"
        . "Nome: {$v['nome']}\nE-mail: {$v['email']}\n" . ($v['assunto'] !== '' ? "Assunto: {$v['assunto']}\n" : '')
        . "Data: " . date('d/m/Y H:i') . "\n\n" . $v['mensagem'] . "\n\n"
        . "-- \nPara responder, é só responder este e-mail. A mensagem também fica guardada no painel: " . url_absoluta('/admin/mensagens') . "\n";
    $assunto = '[Site] ' . ($v['assunto'] !== '' ? $v['assunto'] : 'Mensagem de ' . $v['nome']);
    $ok = enviar_email(EMAIL_CONTATO, mb_substr($assunto, 0, 140), $corpo, $v['email'], $v['nome']);
    $reg = ['id' => $id, 'data' => $quando] + $v + ['ip' => ip_resumido(), 'email_enviado' => $ok];
    $dir = contato_dir();
    file_put_contents("$dir/$id.json", json_encode($reg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $todos = glob("$dir/*.json") ?: [];
    sort($todos);
    foreach (array_slice($todos, 0, max(0, count($todos) - CONTATO_GUARDAR)) as $velho) @unlink($velho);
}

/** Mensagens guardadas, da mais nova para a mais velha. */
function contato_mensagens(): array {
    $out = [];
    foreach (glob(contato_dir() . '/*.json') ?: [] as $f) {
        $m = json_decode((string) file_get_contents($f), true);
        if (is_array($m) && isset($m['id'])) $out[] = $m;
    }
    usort($out, fn($a, $b) => strcmp((string) $b['id'], (string) $a['id']));
    return $out;
}

function contato_apagar(string $id): bool {
    if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/D', $id)) return false;
    $f = contato_dir() . "/$id.json";
    return is_file($f) && unlink($f);
}

/* ---------- Página ---------- */

function vista_contato(): void {
    $erros = []; $v = ['nome' => '', 'email' => '', 'assunto' => '', 'mensagem' => ''];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        [$erros, $v, $enviado] = contato_processar();
        if ($enviado) { header('Location: ' . u('/contato/?enviado=1'), true, 303); exit; }
    }
    $enviado = !$erros && isset($_GET['enviado']);
    $email = email_publico();
    $campo = function (string $id, string $rotulo, string $html, string $dica = '') use ($erros): string {
        $err = $erros[$id] ?? '';
        return '<div class="form__campo' . ($err ? ' form__campo--erro' : '') . '">'
            . '<label for="c-' . $id . '">' . $rotulo . '</label>'
            . ($dica !== '' ? '<p class="form__dica" id="c-' . $id . '-dica">' . $dica . '</p>' : '')
            . $html
            . ($err ? '<p class="form__erro" id="c-' . $id . '-erro">' . e($err) . '</p>' : '')
            . '</div>';
    };
    $desc = fn(string $id, bool $dica = false) => ($dica || isset($erros[$id]))
        ? ' aria-describedby="' . trim(($dica ? "c-$id-dica " : '') . (isset($erros[$id]) ? "c-$id-erro" : '')) . '"' . (isset($erros[$id]) ? ' aria-invalid="true"' : '') : '';
    ob_start();
    echo cabeca_pagina('Contato', 'Fale com a gente', 'Proposta de oficina, parceria, convite, dúvida sobre a Linkoteca ou só um oi. A mensagem chega direto na caixa do coletivo.', 'arco'); ?>
<section class="faixa faixa-giz">
  <div class="wrap contato">
    <div class="contato__form">
      <?php if ($enviado): ?>
      <div class="aviso aviso--ok" role="status">
        <p class="titulo-secao">Mensagem enviada. Valeu!</p>
        <p>A gente lê tudo e responde no e-mail que você deixou, normalmente em poucos dias.</p>
        <p><a class="botao" href="<?= e(u('/contato/')) ?>">Escrever outra mensagem</a></p>
      </div>
      <?php else: ?>
      <?php if (isset($erros['geral'])): ?><p class="aviso aviso--erro" role="alert"><?= e($erros['geral']) ?></p>
      <?php elseif ($erros): ?><p class="aviso aviso--erro" role="alert">Faltou ajustar <?= count($erros) === 1 ? 'um campo' : count($erros) . ' campos' ?>. Veja as marcações abaixo.</p><?php endif ?>
      <form class="form" action="<?= e(u('/contato/')) ?>" method="post" novalidate>
        <input type="hidden" name="ficha" value="<?= e(contato_ficha()) ?>">
        <div class="form__mel" aria-hidden="true">
          <label for="c-site">Não preencha este campo</label>
          <input id="c-site" name="site" type="text" tabindex="-1" autocomplete="off">
        </div>
        <?= $campo('nome', 'Seu nome', '<input class="campo" id="c-nome" name="nome" type="text" required maxlength="80" autocomplete="name" value="' . e($v['nome']) . '"' . $desc('nome') . '>') ?>
        <?= $campo('email', 'Seu e-mail', '<input class="campo" id="c-email" name="email" type="email" required maxlength="120" autocomplete="email" inputmode="email" value="' . e($v['email']) . '"' . $desc('email', true) . '>', 'É para onde vai a resposta. Não entra em lista nenhuma.') ?>
        <?= $campo('assunto', 'Assunto <span class="form__opc">(opcional)</span>', '<input class="campo" id="c-assunto" name="assunto" type="text" maxlength="120" value="' . e($v['assunto']) . '"' . $desc('assunto') . '>') ?>
        <?= $campo('mensagem', 'Mensagem', '<textarea class="campo" id="c-mensagem" name="mensagem" rows="7" required minlength="10" maxlength="4000"' . $desc('mensagem') . '>' . e($v['mensagem']) . '</textarea>') ?>
        <p class="form__acoes"><button class="botao botao--fogo" type="submit">Enviar mensagem</button></p>
        <p class="form__nota cota">Seus dados servem só para responder você.</p>
      </form>
      <?php endif ?>
    </div>
    <aside class="contato__lado" aria-label="Outros jeitos de falar com a gente">
      <span class="gota gota--arco" aria-hidden="true"></span>
      <h2 class="titulo-secao">Prefere e-mail?</h2>
      <p><a class="contato__email" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
      <p>Santo André, ABC Paulista. A bancada é itinerante: a gente vai até pontos de cultura, escolas e bibliotecas.</p>
    </aside>
  </div>
</section>
<?php
    layout('Contato', 'contato', (string) ob_get_clean(), [
        'descricao' => 'Fale com a Ciberlurgia: proposta de oficina, parceria, convite ou dúvida.',
        'status' => $erros ? 422 : 0,
    ]);
}
