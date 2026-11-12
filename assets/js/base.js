/* Ciberlurgia: comportamento comum.
   Eventos do site (em document):
   - 'cbl:modo'   detail {modo: '2d'|'3d'}   quando o botão troca o modo
   - 'cbl:pagina' detail {pagina, raiz}      depois de cada carga ou troca de página */

const html = document.documentElement;
/* Pasta do site (vazia na raiz), vinda do PHP em <html data-base>. */
const BASE = document.documentElement.dataset.base || '';

/* ---------- Botão 2D/3D ---------- */
function marcarBotao() {
  const b = document.getElementById('alternar-modo');
  if (!b) return;
  const tres = html.dataset.modo === '3d';
  b.setAttribute('aria-pressed', String(tres));
  b.setAttribute('aria-label', tres ? 'Navegação 3D ligada. Trocar para 2D' : 'Navegação 2D ligada. Trocar para 3D');
  if (html.dataset.webgl === '0') {
    b.disabled = true;
    b.title = 'Este aparelho não roda o 3D';
  }
}

document.getElementById('alternar-modo')?.addEventListener('click', () => {
  const modo = html.dataset.modo === '3d' ? '2d' : '3d';
  html.dataset.modo = modo;
  try { localStorage.setItem('cbl-modo', modo); } catch (e) {}
  marcarBotao();
  document.dispatchEvent(new CustomEvent('cbl:modo', { detail: { modo } }));
});
marcarBotao();
document.addEventListener('cbl:modo', marcarBotao);

/* ---------- Contagem de cliques (sem cookie, sem dado pessoal) ---------- */
document.addEventListener('click', (ev) => {
  const a = ev.target.closest?.('[data-contar]');
  if (!a) return;
  const corpo = JSON.stringify({ tipo: 'clique', id: a.dataset.contar });
  try {
    if (!navigator.sendBeacon?.(BASE + '/api/contagem', new Blob([corpo], { type: 'application/json' }))) {
      fetch(BASE + '/api/contagem', { method: 'POST', body: corpo, headers: { 'Content-Type': 'application/json' }, keepalive: true });
    }
  } catch (e) {}
});

/* ---------- Carrossel (rolagem nativa com snap; botões só com JS) ---------- */
function iniciarCarrossel(raiz) {
  const trilho = raiz.querySelector('[data-carrossel]');
  const botoes = raiz.querySelector('[data-carrossel-botoes]');
  if (!trilho || !botoes || trilho.dataset.pronto) return;
  trilho.dataset.pronto = '1';
  botoes.hidden = false;
  const passo = () => (trilho.querySelector('li')?.getBoundingClientRect().width || 300) + 24;
  const reduzido = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ir = (dir) => trilho.scrollBy({ left: dir * passo(), behavior: reduzido ? 'auto' : 'smooth' });
  botoes.querySelector('[data-carrossel-ant]').addEventListener('click', () => ir(-1));
  botoes.querySelector('[data-carrossel-prox]').addEventListener('click', () => ir(1));
  trilho.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); ir(1); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); ir(-1); }
  });
}

function aoMudarPagina(raiz) {
  iniciarCarrossel(raiz);
}

document.addEventListener('cbl:pagina', (e) => aoMudarPagina(e.detail.raiz));

/* Primeira carga: carrega os módulos da página e avisa todo mundo. */
const main = document.getElementById('conteudo');
const scripts = (main?.dataset.scripts || '').split(' ').filter(Boolean);
Promise.allSettled(scripts.map((s) => import(s))).then(() => {
  document.dispatchEvent(new CustomEvent('cbl:pagina', { detail: { pagina: main?.dataset.pagina, raiz: main } }));
});

/* Transições 2D/3D: módulo à parte. Se falhar, o site segue com links normais. */
import('./navegacao.js').catch(() => {});
