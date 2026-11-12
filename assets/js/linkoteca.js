/* Ciberlurgia: Linkoteca. O servidor já entrega a página filtrada (funciona sem JS);
   este módulo deixa busca e filtros instantâneos, sem recarregar.
   Dados do catálogo não vão para innerHTML: tudo é montado com DOM e textContent. */

/* pasta do site (vazia na raiz), vinda do PHP em <html data-base> */
const BASE = document.documentElement.dataset.base || '';

const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
const ESPERA_BUSCA = 120;
const ESPERA_REGISTRO = 1500;
const iniciadas = new WeakSet();
let ultimoRegistrado = '';

/* ---------- Texto ---------- */

const normalizar = (s) => String(s ?? '')
  .normalize('NFD').replace(/\p{M}+/gu, '').toLowerCase()
  .replace(/\s+/g, ' ').trim();

const normalizarCaractere = (c) => c.normalize('NFD').replace(/\p{M}+/gu, '').toLowerCase();

const palavras = (t) => t.split(/[^\p{L}\p{N}]+/u).filter(Boolean);

const termosDe = (q) => normalizar(q)
  .replace(/[^\p{L}\p{N}\s-]+/gu, ' ')
  .split(/\s+/)
  .filter((t) => /[\p{L}\p{N}]/u.test(t));

/* Levenshtein com corte: devolve limite + 1 quando a distância passa do limite. */
function distancia(a, b, limite) {
  if (Math.abs(a.length - b.length) > limite) return limite + 1;
  let ant = Array.from({ length: b.length + 1 }, (_, j) => j);
  for (let i = 1; i <= a.length; i++) {
    const atual = [i];
    let minimo = i;
    for (let j = 1; j <= b.length; j++) {
      const custo = a[i - 1] === b[j - 1] ? 0 : 1;
      atual[j] = Math.min(ant[j] + 1, atual[j - 1] + 1, ant[j - 1] + custo);
      if (atual[j] < minimo) minimo = atual[j];
    }
    if (minimo > limite) return limite + 1;
    ant = atual;
  }
  return ant[b.length];
}

/* Um termo casa se for substring do texto; termos com 5+ letras também aceitam
   uma palavra do texto a uma letra de distância (erro de digitação). */
function casa(texto, listaPalavras, termo) {
  if (texto.includes(termo)) return true;
  return [...termo].length >= 5 && listaPalavras.some((p) => distancia(p, termo, 1) <= 1);
}

function dominioDe(url) {
  try {
    return new URL(url).hostname.replace(/^www\./, '');
  } catch {
    return '';
  }
}

function dataBr(iso) {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
  if (!m) return '';
  const mes = Number(m[2]);
  if (mes < 1 || mes > 12) return '';
  return `${Number(m[3])} ${MESES[mes - 1]} ${m[1]}`;
}

/* ---------- Índice e busca ---------- */

function indexar(dados) {
  const cats = new Map((dados.categorias || []).map((c) => [c.slug, c]));
  return (dados.links || []).map((l, ordem) => {
    const cat = cats.get(l.categoria);
    const nomeCat = cat?.nome ?? '';
    const dominio = dominioDe(l.url);
    const tags = Array.isArray(l.tags) ? l.tags : [];
    const tituloNorm = normalizar(l.titulo);
    const texto = normalizar([l.titulo, l.descricao, l.cidade, nomeCat, tags.join(' '), dominio].join(' '));
    return {
      l,
      ordem,
      nomeCat,
      cor: cat?.cor ?? 'forja',
      dominio,
      texto,
      palavras: palavras(texto),
      tituloNorm,
      palavrasTitulo: palavras(tituloNorm),
    };
  });
}

function buscar(indice, { termos, cat, cidade }) {
  const achados = [];
  for (const it of indice) {
    if (cat && it.l.categoria !== cat) continue;
    if (cidade && (it.l.cidade || '') !== cidade) continue;
    if (!termos.every((t) => casa(it.texto, it.palavras, t))) continue;
    const pontos = termos.filter((t) => casa(it.tituloNorm, it.palavrasTitulo, t)).length;
    achados.push({ it, pontos });
  }
  if (termos.length) achados.sort((a, b) => b.pontos - a.pontos || a.it.ordem - b.it.ordem);
  return achados.map((a) => a.it);
}

/* ---------- Destaque no título ---------- */

function ocorrencias(chars, tc) {
  const r = [];
  const m = tc.length;
  for (let i = 0; i + m <= chars.length; i++) {
    let ok = true;
    for (let j = 0; j < m; j++) {
      if (chars[i + j] !== tc[j]) { ok = false; break; }
    }
    if (ok) { r.push([i, i + m]); i += m - 1; }
  }
  return r;
}

/* Palavra do título a uma letra de distância do termo (usado só sem ocorrência exata). */
function palavrasProximas(chars, termo) {
  const achados = [];
  let ini = -1;
  for (let i = 0; i <= chars.length; i++) {
    const dentro = i < chars.length && /[\p{L}\p{N}]/u.test(chars[i]);
    if (dentro && ini < 0) ini = i;
    else if (!dentro && ini >= 0) {
      if (distancia(chars.slice(ini, i).join(''), termo, 1) <= 1) achados.push([ini, i]);
      ini = -1;
    }
  }
  return achados;
}

/* Faixas [início, fim) em índices de code point do título original, sem sobreposição. */
function destaques(cps, termos) {
  const chars = [];
  const origem = [];
  cps.forEach((c, k) => {
    for (const ch of normalizarCaractere(c)) { chars.push(ch); origem.push(k); }
  });
  const faixas = [];
  for (const t of termos) {
    const tc = Array.from(t);
    let achados = ocorrencias(chars, tc);
    if (!achados.length && tc.length >= 5) achados = palavrasProximas(chars, t);
    for (const [i, j] of achados) faixas.push([origem[i], origem[j - 1] + 1]);
  }
  faixas.sort((x, y) => x[0] - y[0]);
  const uniao = [];
  for (const [a, b] of faixas) {
    const ult = uniao[uniao.length - 1];
    if (ult && a <= ult[1]) ult[1] = Math.max(ult[1], b);
    else uniao.push([a, b]);
  }
  return uniao;
}

function preencherTitulo(a, titulo, termos) {
  const cps = Array.from(titulo);
  let pos = 0;
  for (const [ini, fim] of destaques(cps, termos)) {
    if (ini > pos) a.append(cps.slice(pos, ini).join(''));
    const mark = document.createElement('mark');
    mark.textContent = cps.slice(ini, fim).join('');
    a.append(mark);
    pos = fim;
  }
  if (pos < cps.length) a.append(cps.slice(pos).join(''));
}

/* ---------- Renderização (mesmo HTML de item_link) ---------- */

function elem(tag, texto, classe) {
  const el = document.createElement(tag);
  if (classe) el.className = classe;
  if (texto !== undefined) el.textContent = texto;
  return el;
}

function montarItem(it, termos) {
  const { l } = it;
  const li = elem('li', undefined, 'link-item');
  li.dataset.id = String(l.id ?? '');

  const a = elem('a', undefined, 'link-item__titulo');
  a.href = l.url;
  a.rel = 'noopener';
  a.target = '_blank';
  a.dataset.contar = String(l.id ?? '');
  preencherTitulo(a, l.titulo ?? '', termos);
  a.append(' ');
  const seta = elem('span', '↗');
  seta.setAttribute('aria-hidden', 'true');
  a.append(seta);
  li.append(a);

  if (l.descricao) li.append(elem('p', l.descricao));

  const meta = elem('p', undefined, 'link-item__meta cota');
  meta.append(elem('span', it.nomeCat, `selo-cat selo-cat--${it.cor}`));
  if (l.cidade) meta.append(elem('span', l.cidade));
  meta.append(elem('span', it.dominio));
  const data = dataBr(l.verificado);
  if (data) meta.append(elem('span', `verificado em ${data}`));
  li.append(meta);
  return li;
}

/* ---------- Controle da página ---------- */

function lerDados() {
  try {
    return JSON.parse(document.getElementById('dados-links').textContent);
  } catch {
    return null;
  }
}

function adiar(fn, ms) {
  let t = 0;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}

function iniciar(raiz) {
  const sec = raiz?.querySelector?.('[data-linkoteca]');
  if (!sec || iniciadas.has(sec)) return;
  const dados = lerDados();
  if (!dados) return;
  iniciadas.add(sec);

  const campo = sec.querySelector('#busca-q');
  const select = sec.querySelector('select[name="cidade"]');
  const chips = [...sec.querySelectorAll('.chip[data-categoria]')];
  const lista = sec.querySelector('[data-resultados]');
  const total = sec.querySelector('[data-total]');
  const vazio = sec.querySelector('[data-vazio]');
  const procurados = sec.querySelector('[data-procurados]');
  const indice = indexar(dados);
  const estado = {
    q: campo?.value ?? '',
    cat: chips.find((c) => c.classList.contains('chip--ativo'))?.dataset.categoria ?? '',
    cidade: select?.value ?? '',
  };
  let timerRegistro = 0;

  function atualizarUrl() {
    try {
      const url = new URL(location.href);
      url.search = '';
      const p = url.searchParams;
      if (estado.q.trim()) p.set('q', estado.q.trim());
      if (estado.cat) p.set('cat', estado.cat);
      if (estado.cidade) p.set('cidade', estado.cidade);
      history.replaceState(history.state, '', url.pathname + url.search + url.hash);
    } catch {}
  }

  /* Conta a busca só depois de 1,5 s sem digitar, com 3+ letras e resultado. */
  function agendarRegistro(temResultado) {
    clearTimeout(timerRegistro);
    const termo = estado.q.trim().replace(/\s+/g, ' ');
    const letras = (termo.match(/[\p{L}\p{N}]/gu) || []).length;
    if (!temResultado || letras < 3) return;
    timerRegistro = setTimeout(() => {
      if (termo === ultimoRegistrado) return;
      ultimoRegistrado = termo;
      try {
        navigator.sendBeacon?.(BASE + '/api/contagem', new Blob([JSON.stringify({ tipo: 'busca', termo })], { type: 'application/json' }));
      } catch {}
    }, ESPERA_REGISTRO);
  }

  function atualizar(origem) {
    if (campo) estado.q = campo.value;
    const termos = termosDe(estado.q);
    const res = buscar(indice, { termos, cat: estado.cat, cidade: estado.cidade });
    const filtrando = estado.q.trim() !== '' || estado.cat !== '' || estado.cidade !== '';

    lista?.replaceChildren(...res.map((it) => montarItem(it, termos)));
    if (total) total.textContent = `${res.length} ${res.length === 1 ? 'link' : 'links'}`;
    if (vazio) vazio.hidden = res.length > 0;
    if (procurados) procurados.hidden = filtrando;
    atualizarUrl();
    if (origem === 'digitar') agendarRegistro(res.length > 0);
  }

  chips.forEach((c) => c.setAttribute('aria-pressed', String(c.classList.contains('chip--ativo'))));
  chips.forEach((chip) => chip.addEventListener('click', (ev) => {
    ev.preventDefault();
    estado.cat = chip.dataset.categoria || '';
    for (const c of chips) {
      const on = c === chip;
      c.classList.toggle('chip--ativo', on);
      c.setAttribute('aria-pressed', String(on));
    }
    atualizar('filtro');
  }));

  select?.addEventListener('change', () => {
    estado.cidade = select.value;
    atualizar('filtro');
  });

  campo?.addEventListener('input', adiar(() => atualizar('digitar'), ESPERA_BUSCA));

  sec.querySelector('form.busca')?.addEventListener('submit', (ev) => {
    ev.preventDefault();
    atualizar('digitar');
  });
}

/* Registrado uma vez: base.js dispara 'cbl:pagina' na primeira carga e a cada troca de página. */
document.addEventListener('cbl:pagina', (ev) => {
  if (ev.detail?.pagina === 'linkoteca') iniciar(ev.detail.raiz);
});

/* Tecla "/" foca a busca, desde que o foco não esteja em outro campo. */
document.addEventListener('keydown', (ev) => {
  if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey) return;
  const ativo = document.activeElement;
  if (ativo && (ativo.matches?.('input, textarea, select') || ativo.isContentEditable)) return;
  const campo = document.getElementById('busca-q');
  if (!campo) return;
  ev.preventDefault();
  campo.focus();
});
