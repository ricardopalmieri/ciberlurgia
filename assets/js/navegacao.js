/* Ciberlurgia: navegação pelo mapa.
   Carregado por base.js (único chamador). Não existe outro roteador.
   Cada página com opções é um mapa (3D em mundo3d.js ou leve em mapa2d.js); página sem opções (folha)
   aparece como página comum com "voltar ao mapa". Troca só #conteudo; se algo falhar, vale o link normal.
   Dados do mapa: <script id="dados-mapa"> (app/views/mapa.php).
   Pedido do coletivo: a câmera voa para dentro da gota, clarão de têmpera, o submapa nasce. */

const html = document.documentElement;
const BASE = html.dataset.base || '';   /* pasta do site (vazia na raiz) */
const main = document.getElementById('conteudo');
const mapaEl = document.getElementById('mapa');
const palco = document.getElementById('palco3d');
const rotulosEl = document.getElementById('rotulos');
const clarao = document.getElementById('clarao');
const reduzido = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

let ocupado = false;
let motor = null;               /* mundo3d ou mapa2d, conforme o modo */
let criando = null;
let dados = lerDados(document);
let atual = location.pathname + location.search;      /* página que está no DOM */
let rotulos = new Map();
let pendente = null;            /* mudança de modo durante um carregamento */

if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
history.replaceState({ y: 0 }, '');
if (clarao) document.body.appendChild(clarao);        /* o clarão sobrevive à troca para página folha */

function lerDados(doc) {
  try { return JSON.parse(doc.getElementById('dados-mapa')?.textContent || 'null') || { mapa: null, pai: null }; }
  catch (e) { return { mapa: null, pai: null }; }
}

/* altura do topo para o mapa caber logo abaixo dele */
const topo = document.querySelector('.topo');
function medirTopo() { if (topo) html.style.setProperty('--topo-h', topo.offsetHeight + 'px'); }
medirTopo();
if (topo && 'ResizeObserver' in window) new ResizeObserver(medirTopo).observe(topo);

/* ---------- Rótulos: botões de verdade, alcançáveis pelo Tab ---------- */
function infoDe(id) {
  const m = dados.mapa;
  if (!m) return null;
  if (id === '_centro') {
    return { titulo: m.centro.titulo, estado: 'centro', rotulo: 'Centro', desc: m.pai ? `Você está aqui. Toque no centro para voltar para ${m.pai.titulo}.` : 'O centro do mapa. Daqui saem as ligações para cada área do coletivo.' };
  }
  const n = m.nos.find((x) => x.id === id);
  if (!n) return null;
  if (!n.aberto) return { titulo: n.titulo, estado: 'forja', rotulo: 'Em forja', desc: n.desc || 'Ainda vai abrir.' };
  return { titulo: n.titulo, estado: 'aberto', rotulo: n.externo ? 'Abre em nova aba' : n.ancora ? 'Nesta página' : 'Aberta', desc: n.desc };
}

function mostrarInfo(id) {
  const painel = document.getElementById('mapa-info');
  const i = infoDe(id);
  if (!painel || !i) return;
  painel.hidden = false;
  /* log curto: "> ABERTA · TÍTULO — descrição…" (as pontuações vêm do CSS) */
  const est = document.getElementById('info-estado');
  est.textContent = i.rotulo; est.dataset.e = i.estado;
  document.getElementById('info-nome').textContent = i.titulo;
  const d = document.getElementById('info-desc');
  d.textContent = i.desc || '';
  d.hidden = !i.desc;
}

function montarRotulos() {
  rotulos = new Map();
  rotulosEl.textContent = '';
  const m = dados.mapa;
  const novo = (id, titulo, estado, atrib) => {
    let el;
    if (atrib.href) { el = document.createElement('a'); el.href = atrib.href; if (atrib.externo) { el.target = '_blank'; el.rel = 'noopener'; } }
    else { el = document.createElement('button'); el.type = 'button'; }
    el.className = 'rotulo-no';
    el.dataset.no = id; el.dataset.estado = estado;
    if (atrib.cor) el.dataset.cor = atrib.cor;
    el.textContent = titulo;
    el.setAttribute('aria-label', atrib.aria);
    rotulosEl.appendChild(el); rotulos.set(id, el);
  };
  novo('_centro', m.centro.titulo, 'centro', m.pai
    ? { href: m.pai.url, aria: `${m.centro.titulo}, centro do mapa. Voltar para ${m.pai.titulo}` }
    : { aria: `${m.centro.titulo}, centro do mapa` });
  m.nos.forEach((n) => {
    if (!n.aberto) novo(n.id, n.titulo, 'forja', { aria: `${n.titulo}, em forja, ainda não abriu` });
    else novo(n.id, n.titulo, 'aberto', { href: n.url, externo: n.externo, cor: n.cor, aria: `${n.titulo}${n.externo ? ', abre em nova aba' : ''}` });
  });
}

rotulosEl?.addEventListener('focusin', (e) => {
  const id = e.target.dataset?.no;
  if (!id) return;
  mostrarInfo(id); motor?.focar(id);
});
rotulosEl?.addEventListener('mouseover', (e) => {
  const id = e.target.closest?.('[data-no]')?.dataset.no;
  if (id) { mostrarInfo(id); motor?.realcar(id); }
});

/* ---------- Motor (3D ou leve) ---------- */
const opcoes = {
  clarao,
  rotulosEl,
  aoClicar: (id) => { rotulos.get(id)?.click(); },
  aoPassar: (id) => { if (id) { mostrarInfo(id); motor?.realcar(id); } else motor?.realcar(null); },
};

async function criarMotor(modo) {
  if (modo === '3d' && html.dataset.webgl !== '0') {
    try {
      const m = await import('./mundo3d.js');
      return m.criar(palco, opcoes);
    } catch (e) {
      console.warn('3D indisponível, voltando ao mapa leve', e);
      html.dataset.modo = '2d';
      try { localStorage.setItem('cbl-modo', '2d'); } catch (_) { /* sem armazenamento */ }
      document.dispatchEvent(new CustomEvent('cbl:modo', { detail: { modo: '2d', origem: 'queda' } }));
    }
  }
  const m2 = await import('./mapa2d.js');
  return m2.criar(palco, opcoes);
}

function descartarMotor() {
  if (motor) { try { motor.destruir(); } catch (e) { /* já foi */ } motor = null; }
}

/* Mostra o mapa da página atual (ou esconde, se for folha). entrada: 'dentro' | 'fora' | 'nenhuma' */
async function mostrarMapa(entrada, de) {
  atualizarVoltar();
  if (!dados.mapa) {
    mapaEl.hidden = true;
    delete html.dataset.mapa;
    motor?.ativar(false);
    clarear();
    return;
  }
  if (criando) await criando;
  if (!motor) {
    criando = criarMotor(html.dataset.modo);
    try { motor = await criando; } finally { criando = null; }
  }
  mapaEl.hidden = false;
  html.dataset.mapa = motor.tipo;
  motor.ativar(true);
  montarRotulos();
  mostrarInfo('_centro');
  const v = document.getElementById('mapa-voltar');
  if (dados.mapa.pai) { v.hidden = false; v.href = dados.mapa.pai.url; v.textContent = `← ${dados.mapa.pai.titulo}`; v.setAttribute('aria-label', `Voltar para ${dados.mapa.pai.titulo}`); }
  else v.hidden = true;
  document.getElementById('mapa-dica').hidden = false;
  motor.definir(dados.mapa, rotulos, entrada, de);
  if (entrada === 'nenhuma') clarear();
}

function clarear() { if (clarao) { clarao.style.transition = 'opacity .9s ease-out'; clarao.style.opacity = '0'; } motor?.clarear?.(); }

function atualizarVoltar() {
  const nav = document.getElementById('voltar-mapa');
  if (!nav) return;
  if (dados.mapa || !dados.pai) { nav.hidden = true; nav.textContent = ''; return; }
  nav.hidden = false;
  nav.textContent = '';
  const a = document.createElement('a');
  a.className = 'voltar-mapa__link'; a.href = dados.pai.url; a.dataset.transicao = '';
  a.textContent = `← Voltar ao mapa: ${dados.pai.titulo}`;
  nav.appendChild(a);
}

document.addEventListener('cbl:modo', async (e) => {
  if (e.detail?.origem === 'queda') return;
  if (ocupado) { pendente = e.detail.modo; return; }
  await trocarModo();
});

async function trocarModo() {
  if (criando) await criando.catch(() => {});
  descartarMotor();
  await mostrarMapa('nenhuma');
}

/* ---------- Âncoras na mesma página (ex.: pessoas do coletivo) ---------- */
function alvoAncora(hash) {
  const id = decodeURIComponent(hash.replace(/^#/, ''));
  if (!id) return null;
  if (id === 'sobre') return main.querySelector('.texto-longo')?.closest('section') || main;
  return main.querySelector(`[data-gota="${CSS.escape(id)}"]`) || document.getElementById(id);
}
function irAncora(hash, empurrar) {
  const el = alvoAncora(hash);
  if (!el) return false;
  if (empurrar) { history.replaceState({ y: scrollY }, ''); history.pushState({ y: 0 }, '', location.pathname + location.search + hash); }
  el.scrollIntoView({ behavior: reduzido() ? 'auto' : 'smooth', block: 'center', inline: 'center' });
  if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1');
  el.focus({ preventScroll: true });
  return true;
}

/* ---------- Quais links entram ---------- */
function rotaInterna(a) {
  if (!a || !a.href) return null;
  if (a.target && a.target !== '_self') return null;
  if (a.hasAttribute('download') || a.hasAttribute('data-contar') || a.hasAttribute('data-categoria')) return null;
  let u;
  try { u = new URL(a.href, location.href); } catch (e) { return null; }
  if (u.origin !== location.origin) return null;
  /* caminho sem a pasta do site: as regras abaixo valem na raiz ou em subpasta */
  if (BASE && u.pathname !== BASE && !u.pathname.startsWith(BASE + '/')) return null;   /* fora da pasta do site */
  const p = u.pathname.slice(BASE.length) || '/';
  if (p === '/admin' || p.startsWith('/admin/')) return null;
  if (p.startsWith('/identidade/') && p !== '/identidade/' && !p.startsWith('/identidade/padroes/')) return null;   /* só o enquadramento do manual entra; o manual em si abre direto */
  if (p.startsWith('/api/') || p.startsWith('/uploads/')) return null;
  if (/\.[a-z0-9]{2,5}$/i.test(p.split('/').pop())) return null;      /* arquivos: pdf, xml, zip... */
  return u;
}

document.addEventListener('click', (ev) => {
  if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
  const forja = ev.target.closest?.('button.rotulo-no');
  if (forja) { mostrarInfo(forja.dataset.no); motor?.focar(forja.dataset.no); return; }
  const a = ev.target.closest?.('a[href]');
  if (!a || a.classList.contains('pular')) return;
  const u = rotaInterna(a);
  if (!u) return;
  if (ocupado) return;                                  /* navegação normal, sem truque */
  const chave = u.pathname + u.search;
  if (chave === atual && u.hash) {                      /* âncora na página que já está aberta */
    if (alvoAncora(u.hash)) { ev.preventDefault(); irAncora(u.hash, true); }
    return;
  }
  ev.preventDefault();
  if (chave === atual) { scrollTo(0, 0); return; }
  ir(u, { push: true });
});

addEventListener('popstate', (ev) => {
  const u = new URL(location.href);
  if (ocupado) { location.reload(); return; }
  if (u.pathname + u.search === atual) {
    if (u.hash && alvoAncora(u.hash)) irAncora(u.hash, false); else scrollTo(0, ev.state?.y || 0);
    return;
  }
  ir(u, { push: false, y: ev.state?.y || 0 });
});

/* ---------- Troca de página ---------- */
async function buscar(u) {
  const r = await fetch(u.href, { headers: { 'X-Requested-With': 'fetch', Accept: 'text/html' }, credentials: 'same-origin' });
  const tipo = r.headers.get('content-type') || '';
  if (!tipo.includes('text/html')) throw new Error('não é html');
  const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
  if (!doc.getElementById('conteudo')) throw new Error('sem #conteudo');
  return doc;
}

function trocar(doc) {
  const novo = doc.getElementById('conteudo');
  main.innerHTML = novo.innerHTML;
  for (const k of ['pagina', 'titulo', 'scripts']) {
    if (novo.dataset[k] !== undefined) main.dataset[k] = novo.dataset[k]; else delete main.dataset[k];
  }
  if (doc.documentElement.dataset.pagina) html.dataset.pagina = doc.documentElement.dataset.pagina;
  document.title = doc.title || main.dataset.titulo || document.title;
  const desc = doc.querySelector('meta[name="description"]')?.content;
  const md = document.querySelector('meta[name="description"]');
  if (desc && md) md.content = desc;
  const atuais = new Map([...doc.querySelectorAll('.menu a')].map((l) => [l.getAttribute('href'), l.hasAttribute('aria-current')]));
  document.querySelectorAll('.menu a').forEach((l) => {
    if (atuais.get(l.getAttribute('href'))) l.setAttribute('aria-current', 'page'); else l.removeAttribute('aria-current');
  });
  const js = document.getElementById('dados-mapa');
  const novoJs = doc.getElementById('dados-mapa');
  if (js && novoJs) js.textContent = novoJs.textContent;
  dados = lerDados(doc);
}

async function concluir(u, opts) {
  const doc = await opts.doc;                            /* falhou? cai no catch e vai de navegação normal */
  const anterior = atual;
  if (opts.push) {
    history.replaceState({ y: scrollY }, '');
    history.pushState({ y: 0 }, '', u.href);
  }
  main.classList.remove('entrando');
  trocar(doc);
  atual = location.pathname + location.search;
  /* de onde viemos: se o mapa novo tem um nó para a página anterior, voltamos "para fora" dele */
  const veio = dados.mapa?.nos.find((n) => n.url === anterior);
  await mostrarMapa(veio ? 'fora' : 'dentro', veio?.id);
  const alvoHash = u.hash && alvoAncora(u.hash);
  if (alvoHash) alvoHash.scrollIntoView(); else scrollTo(0, opts.push ? 0 : opts.y || 0);
  main.focus({ preventScroll: true });
  void main.offsetWidth;
  main.classList.add('entrando');
  const lista = (main.dataset.scripts || '').split(' ').filter(Boolean);
  await Promise.allSettled(lista.map((s) => import(s)));
  document.dispatchEvent(new CustomEvent('cbl:pagina', { detail: { pagina: main.dataset.pagina, raiz: main } }));
}

async function ir(u, opts) {
  ocupado = true;
  const seguranca = setTimeout(() => { location.href = u.href; }, 15000);
  try {
    opts.doc = buscar(u);
    opts.doc.catch(() => {});
    /* saída: voa para dentro do nó clicado; se não for um nó (menu, voltar), a câmera recua */
    if (motor && dados.mapa && !mapaEl.hidden) {
      const chave = u.pathname + u.search;
      const no = dados.mapa.nos.find((n) => n.aberto && !n.externo && !n.ancora && n.url === chave);
      if (no) await motor.voar(no.id); else await motor.sair();
    }
    await opts.doc;
    await concluir(u, opts);
  } catch (e) {
    console.warn('navegação normal', e);
    location.href = u.href;
    return;
  } finally {
    clearTimeout(seguranca);
    ocupado = false;
    if (pendente) { pendente = null; trocarModo(); }
  }
}

/* ---------- Primeira carga ---------- */
(async () => {
  try { await mostrarMapa('dentro'); } catch (e) { console.warn('mapa indisponível', e); mapaEl.hidden = true; }
  if (location.hash && alvoAncora(location.hash)) setTimeout(() => irAncora(location.hash, false), 400);
})();
