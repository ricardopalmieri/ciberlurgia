/* Ciberlurgia: mapa leve (Leve 2D) em CSS + SVG (sem canvas), mesmo desenho do manual sem three.
   Quem chama: navegacao.js (modo 2D, sem WebGL, economia de dados ou movimento reduzido).
   Também exporta disposicao(), usada por mundo3d.js, para os dois mapas terem o mesmo desenho.
   Gotas = elementos CSS (border-radius animado, gradiente de fogo, sem contorno); áreas em forja = círculo
   tracejado frio; soldas finas em SVG com pulso (stroke-dasharray); selo estático no centro.
   Estilos em assets/css/mundo.css (.mapa2d, .m2d-*). Rótulos são <a>/<button> HTML (navegacao.js). */

/* pasta do site (vazia na raiz), vinda do PHP em <html data-base> */
const BASE = document.documentElement.dataset.base || '';

/* Posições do mapa: centro no meio, nós num anel (dois anéis se passar de 8).
   Devolve {itens:[{id,titulo,centro,selo,aberto,p:[x,y,z],r,q:[nx,ny]}], ligs:[[a,b]], raio} */
export function disposicao(dados) {
  const nos = dados.nos, n = nos.length;
  const YS = [0.7, -0.2, 0.45, -0.45, 0.25, -0.3], RA = [0.58, 0.62, 0.55, 0.6], RF = [0.5, 0.45, 0.48];
  const dois = n > 8;
  const R1 = n <= 6 ? 3.3 : 3.3 + (Math.min(n, 8) - 6) * 0.35;
  const raio = dois ? 5.4 : R1;
  const itens = [{ id: '_centro', titulo: dados.centro.titulo, centro: true, selo: !!dados.centro.selo, aberto: !!dados.pai, p: [0, 0, 0], r: 1, q: [0, 0] }];
  nos.forEach((no, i) => {
    const th = (2 * Math.PI * i) / n, R = dois ? (i % 2 ? 5.4 : 3.4) : R1;
    const x = Math.sin(th) * R, z = -Math.cos(th) * R, f = dois ? 0.85 : 1;
    itens.push({ id: no.id, titulo: no.titulo, centro: false, selo: false, aberto: !!no.aberto, p: [x, YS[i % 6], z], r: (no.aberto ? RA[i % 4] : RF[i % 3]) * f, q: [x / raio, z / raio] });
  });
  const ligs = [];
  for (let i = 1; i <= n; i++) ligs.push([0, i]);
  for (let i = 1; i < n; i += 2) ligs.push([i, i + 1]);
  return { itens, ligs, raio };
}

const SVGNS = 'http://www.w3.org/2000/svg';

export function criar(palco, op) {
  const reduz = matchMedia('(prefers-reduced-motion: reduce)');
  const raiz = document.createElement('div');
  raiz.className = 'mapa__canvas mapa2d';
  raiz.setAttribute('aria-hidden', 'true');
  const svg = document.createElementNS(SVGNS, 'svg');
  svg.setAttribute('class', 'm2d-fios');
  raiz.appendChild(svg);
  palco.prepend(raiz);
  const canvas = raiz;                                  /* nome mantido: o motor 2D e o 3D têm a mesma forma */
  const alvosAnim = () => [palco, op.rotulosEl].filter(Boolean);

  let geo = null, rotulos = new Map(), larg = new Map(), nos = [], fios = [];
  let W = 1, H = 1, passando = null, visivel = true, tAntes = 0;

  const animar = () => !reduz.matches && !window.cblParado;
  const parar = () => { if (animar() && visivel && !document.hidden) delete raiz.dataset.parado; else raiz.dataset.parado = ''; };

  function rng(s) { return () => { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; }; }

  function medir() {
    const r = palco.getBoundingClientRect();
    W = Math.max(1, Math.round(r.width)); H = Math.max(1, Math.round(r.height));
    svg.setAttribute('width', W); svg.setAttribute('height', H); svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    posicionar();
  }

  function posicionar() {
    if (!geo) return;
    const estreito = W < 520;
    const mx = estreito ? 62 : 110, topo = estreito ? 90 : 78, baixo = estreito ? 185 : 118;
    const sx = W / 2 - mx, sy = (H - topo - baixo) / 2, cy = topo + sy;
    const u = Math.min(sx, sy) / geo.raio * 0.8;
    nos.forEach((n) => {
      n.x = W / 2 + n.it.q[0] * sx; n.y = cy + n.it.q[1] * sy;
      const R = n.it.r * u * (n.it.centro ? 1.1 : 1);
      const forja = !n.it.aberto && !n.it.centro;
      n.D = forja ? Math.max(28, R * 1.35) : Math.max(34, R * 1.9);
      n.el.style.setProperty('--x', n.x.toFixed(1) + 'px'); n.el.style.setProperty('--y', n.y.toFixed(1) + 'px');
      n.el.style.width = n.el.style.height = n.D.toFixed(1) + 'px';
    });
    fios.forEach((f) => {
      const A = nos[f.a], B = nos[f.b];
      for (const l of [f.fio, f.pulso]) { l.setAttribute('x1', A.x.toFixed(1)); l.setAttribute('y1', A.y.toFixed(1)); l.setAttribute('x2', B.x.toFixed(1)); l.setAttribute('y2', B.y.toFixed(1)); }
    });
    desenharRotulos();
  }

  /* etiquetas rente à gota; a do centro fica colada no meio, logo abaixo do selo, acima das outras */
  function desenharRotulos() {
    nos.forEach((n) => {
      const b = rotulos.get(n.it.id);
      if (!b) return;
      let w = larg.get(n.it.id); if (!w) { w = b.offsetWidth || 80; larg.set(n.it.id, w); }
      const x = Math.min(W - w / 2 - 6, Math.max(w / 2 + 6, n.x));
      const y = n.it.centro ? n.y + (n.it.selo ? n.D * 0.31 + 2 : -(b.offsetHeight || 32) / 2) : Math.min(H - 40, n.y + n.D / 2 - 2);
      b.style.transform = `translate(${Math.round(x)}px,${Math.round(y)}px) translate(-50%,0)`;
      b.style.zIndex = n.it.centro ? '20' : '5';
      b.style.opacity = n.visivel ? '1' : '0';
    });
  }

  function montarDom() {
    nos.forEach((n) => n.el.remove());
    while (svg.firstChild) svg.removeChild(svg.firstChild);
    const r = rng(11);
    nos = geo.itens.map((it, i) => {
      const forja = !it.aberto && !it.centro;
      const atraso = i === 0 ? 0 : 0.2 + (i - 1) * 0.07;
      const el = document.createElement('div');
      el.className = 'm2d-no'; el.dataset.sem = String(Math.floor(r() * 4));
      el.style.transitionDelay = atraso.toFixed(2) + 's';
      const g = document.createElement('div');
      g.className = forja ? 'm2d-forja' : 'm2d-gota';
      g.dataset.id = it.id;
      el.appendChild(g);
      if (it.selo) {
        const img = new Image();
        img.className = 'm2d-selo'; img.alt = ''; img.src = BASE + '/assets/img/mini-256.webp'; img.draggable = false;
        g.appendChild(img);
      }
      g.addEventListener('click', () => { if (!tAntes) op.aoClicar(it.id); });
      g.addEventListener('pointerenter', () => { if (passando !== it.id) { passando = it.id; op.aoPassar && op.aoPassar(it.id); } });
      g.addEventListener('pointerleave', () => { if (passando === it.id) { passando = null; op.aoPassar && op.aoPassar(null); } });
      raiz.appendChild(el);
      return { it, el, g, x: 0, y: 0, D: 40, visivel: true };
    });
    fios = geo.ligs.map(([a, b], i) => {
      const A = nos[a].it, B = nos[b].it;
      const fio = document.createElementNS(SVGNS, 'line'), pulso = document.createElementNS(SVGNS, 'line');
      fio.setAttribute('class', 'm2d-fio');
      if ((!A.aberto && !A.centro) || (!B.aberto && !B.centro)) fio.dataset.fria = '';
      pulso.setAttribute('class', 'm2d-pulso'); pulso.setAttribute('pathLength', '100');
      pulso.style.animationDelay = (-i * 0.37).toFixed(2) + 's';
      if (i % 2) pulso.dataset.inv = '';
      svg.appendChild(fio); svg.appendChild(pulso);
      return { a, b, fio, pulso };
    });
  }

  function definir(dados, rot, entrada) {
    rotulos = rot; larg = new Map(); passando = null;
    geo = disposicao(dados);
    montarDom();
    alvosAnim().forEach((el) => { el.style.transition = 'none'; el.style.transform = ''; el.style.opacity = ''; });
    medir();
    parar();
    const quer = entrada !== 'nenhuma' && !reduz.matches && !window.cblParado;
    clearTimeout(tAntes); tAntes = 0;
    if (quer) {
      raiz.dataset.antes = '';
      nos.forEach((n) => { n.visivel = false; });
      desenharRotulos();
      void raiz.offsetWidth;
      requestAnimationFrame(() => requestAnimationFrame(() => {
        delete raiz.dataset.antes;
        nos.forEach((n) => { n.visivel = true; });
        desenharRotulos();
        tAntes = setTimeout(() => { tAntes = 0; }, 1100);
      }));
    } else { delete raiz.dataset.antes; desenharRotulos(); }
  }

  /* zoom no nó + fade (a próxima página entra por fade) */
  function voar(id) {
    return new Promise((res) => {
      const n = nos.find((x) => x.it.id === id);
      if (!n || reduz.matches || window.cblParado || document.hidden) { res(); return; }
      const els = alvosAnim();
      let feito = false;
      const fim = () => { if (feito) return; feito = true; clearTimeout(tm); res(); };
      const tm = setTimeout(fim, 900);
      els.forEach((el) => {
        el.style.transformOrigin = `${n.x}px ${n.y}px`;
        el.style.transition = 'transform .5s cubic-bezier(.6,0,.9,.4), opacity .5s ease-in';
      });
      requestAnimationFrame(() => els.forEach((el) => { el.style.transform = 'scale(6)'; el.style.opacity = '0'; }));
      setTimeout(fim, 520);
    });
  }
  function sair() {
    return new Promise((res) => {
      if (reduz.matches || window.cblParado || document.hidden) { res(); return; }
      const els = alvosAnim();
      els.forEach((el) => { el.style.transformOrigin = '50% 50%'; el.style.transition = 'transform .3s ease-in, opacity .3s ease-in'; });
      requestAnimationFrame(() => els.forEach((el) => { el.style.transform = 'scale(.82)'; el.style.opacity = '0'; }));
      setTimeout(res, 320);
    });
  }
  function clarear() { /* o fade-in já é do definir() */ }

  function realcar(id) {
    nos.forEach((n) => { if (n.it.id === id && !n.it.centro) n.g.dataset.sel = ''; else delete n.g.dataset.sel; });
  }
  function focar(id) { realcar(id); }

  const io = 'IntersectionObserver' in window ? new IntersectionObserver((es) => { visivel = es[0].isIntersecting; parar(); }) : null;
  io && io.observe(palco);
  const aoMudar = () => parar();
  document.addEventListener('visibilitychange', aoMudar);
  window.addEventListener('cbl-movimento', aoMudar);
  const ro = 'ResizeObserver' in window ? new ResizeObserver(() => { larg = new Map(); medir(); }) : null;
  ro && ro.observe(palco);
  reduz.addEventListener ? reduz.addEventListener('change', aoMudar) : reduz.addListener(aoMudar);

  function ativar(on) { raiz.hidden = !on; if (on) { medir(); parar(); } }
  function destruir() {
    clearTimeout(tAntes);
    document.removeEventListener('visibilitychange', aoMudar);
    window.removeEventListener('cbl-movimento', aoMudar);
    reduz.removeEventListener ? reduz.removeEventListener('change', aoMudar) : reduz.removeListener(aoMudar);
    io && io.disconnect(); ro && ro.disconnect();
    alvosAnim().forEach((el) => { el.style.transition = ''; el.style.transform = ''; el.style.opacity = ''; el.style.transformOrigin = ''; });
    nos = []; raiz.remove();
  }

  return { canvas, definir, voar, sair, clarear, focar, realcar, ativar, destruir, tipo: '2d' };
}
