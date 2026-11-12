/* Ciberlurgia: mapa 3D (Oficina 3D), portado do manual de identidade (seção "Linguagem 3D").
   Quem chama: navegacao.js (único). Só é carregado no modo 3D; three não entra no modo 2D.
   Dados: o objeto do mapa embutido em #dados-mapa (ver app/views/mapa.php) e os rótulos HTML
   (<a>/<button>) montados por navegacao.js, que este módulo apenas posiciona sobre a cena.
   Visual do manual: gotas de metal (metaballs no shader), arame frio nas áreas em forja,
   soldas azuis com pulso de luz, chão em grade que ondula e some, selo estático no centro.
   API: criar(palco, op) -> { definir, voar, sair, clarear, focar, ativar, destruir }. */

import * as THREE from '../vendor/three/three.module.min.js';
import { disposicao } from './mapa2d.js';

/* pasta do site (vazia na raiz), vinda do PHP em <html data-base> */
const BASE = document.documentElement.dataset.base || '';

const RUIDO = [
  'float hs(vec3 p){p=fract(p*.3183099+.1);p*=17.;return fract(p.x*p.y*p.z*(p.x+p.y+p.z));}',
  'float n3(vec3 x){vec3 i=floor(x),f=fract(x);f=f*f*(3.-2.*f);',
  'return mix(mix(mix(hs(i),hs(i+vec3(1,0,0)),f.x),mix(hs(i+vec3(0,1,0)),hs(i+vec3(1,1,0)),f.x),f.y),',
  'mix(mix(hs(i+vec3(0,0,1)),hs(i+vec3(1,0,1)),f.x),mix(hs(i+vec3(0,1,1)),hs(i+vec3(1,1,1)),f.x),f.y),f.z);}',
].join('\n');
const RAMPA = 'vec3 rampa(float t){vec3 a=vec3(.431,0.,0.),b=vec3(.91,.094,.047),c=vec3(1.,.353,0.),d=vec3(1.,.769,0.),e=vec3(1.,.957,.812);t=clamp(t,0.,1.);if(t<.25)return mix(a,b,t/.25);if(t<.5)return mix(b,c,(t-.25)/.25);if(t<.75)return mix(c,d,(t-.5)/.25);return mix(d,e,(t-.75)/.25);}';

const VERT_GOTA = 'uniform float SZ;uniform vec4 B0,B1,B2,B3,B4;varying vec3 vV,vO,vC0,vC1,vC2,vC3,vC4;void main(){vec4 c=modelViewMatrix*vec4(0.,0.,0.,1.);vec3 p=c.xyz+vec3(position.xy*SZ,0.);vV=p;vO=c.xyz;mat3 M=mat3(modelViewMatrix);vC0=c.xyz+M*B0.xyz;vC1=c.xyz+M*B1.xyz;vC2=c.xyz+M*B2.xyz;vC3=c.xyz+M*B3.xyz;vC4=c.xyz+M*B4.xyz;gl_Position=projectionMatrix*vec4(p,1.);}';
const FRAG_GOTA = RUIDO + '\n' + RAMPA + '\nuniform float T,S,Bri,SZ,K;uniform vec4 B0,B1,B2,B3,B4;varying vec3 vV,vO,vC0,vC1,vC2,vC3,vC4;' +
  'float sm(float a,float b){float h=clamp(.5+.5*(b-a)/K,0.,1.);return mix(b,a,h)-K*h*(1.-h);}' +
  'float mapa(vec3 p){float d=length(p-vC0)-B0.w;d=sm(d,length(p-vC1)-B1.w);d=sm(d,length(p-vC2)-B2.w);d=sm(d,length(p-vC3)-B3.w);d=sm(d,length(p-vC4)-B4.w);return d;}' +
  'void main(){vec3 rd=normalize(vV);float dc=length(vO);float t=max(0.,dc-SZ*1.2);bool hit=false;' +
  'for(int i=0;i<56;i++){float d=mapa(rd*t);if(d<.0015){hit=true;break;}t+=d;if(t>dc+SZ*1.2)break;}' +
  'if(!hit)discard;vec3 p=rd*t;vec2 e=vec2(.003,-.003);' +
  'vec3 n=normalize(e.xyy*mapa(p+e.xyy)+e.yyx*mapa(p+e.yyx)+e.yxy*mapa(p+e.yxy)+e.xxx*mapa(p+e.xxx));' +
  'float l=dot(n,normalize(vec3(-.55,.65,.5)))*.5+.5;float aro=pow(1.-max(dot(n,-rd),0.),2.);vec3 c=rampa(l*(1.-aro*.7)+Bri);' +
  'vec3 q=(p-vO)/SZ;float v=abs(n3(q*4.+S)-.5);float veia=smoothstep(.035,.0,v);' +
  'float onda=pow(max(0.,sin(dot(q,vec3(.8,.5,.3))*7.-T*2.4+S)),36.);c+=vec3(1.,.95,.82)*veia*onda*1.7;' +
  'c+=(fract(sin(dot(gl_FragCoord.xy,vec2(12.9898,78.233)))*43758.5453)-.5)*.05;gl_FragColor=vec4(c,1.);}';

const VERT_GRADE = RUIDO + '\nuniform float T;varying float vD;varying float vH;void main(){vec3 p=position;float w=(n3(vec3(p.xz*.22,T*.12))-.5)*1.1+sin(p.x*.3+T*.35)*cos(p.z*.27+T*.28)*.22;p.y+=w;vH=w;vD=length(p.xz);gl_Position=projectionMatrix*modelViewMatrix*vec4(p,1.);}';
const FRAG_GRADE = 'varying float vD;varying float vH;void main(){float a=(1.-smoothstep(4.,22.,vD))*.55;vec3 c=mix(vec3(.27,.27,.26),vec3(.55,.36,.25),clamp(vH*1.4+.3,0.,1.));gl_FragColor=vec4(c,a);}';

const suave = (a, b, x) => { const t = Math.min(1, Math.max(0, (x - a) / (b - a))); return t * t * (3 - 2 * t); };
const dif = (a, b) => { let d = (b - a) % (Math.PI * 2); if (d > Math.PI) d -= Math.PI * 2; if (d < -Math.PI) d += Math.PI * 2; return d; };

export function criar(palco, op) {
  const reduz = matchMedia('(prefers-reduced-motion: reduce)');
  const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
  const rd = new THREE.WebGLRenderer({ antialias: dpr < 1.5, alpha: true, powerPreference: 'low-power' });
  if (!rd.getContext()) throw new Error('sem WebGL');
  rd.setPixelRatio(dpr);
  rd.setClearColor(0x000000, 0);
  const canvas = rd.domElement;
  canvas.setAttribute('aria-hidden', 'true');
  canvas.className = 'mapa__canvas';
  palco.prepend(canvas);

  const scene = new THREE.Scene();
  const cam = new THREE.PerspectiveCamera(42, 1, 0.1, 120);
  const grupo = new THREE.Group();
  scene.add(grupo);
  const U = { T: { value: 0 } };
  const descartar = [];                                   /* tudo que precisa de dispose */
  const guardar = (x) => { descartar.push(x); return x; };

  /* Chão: grade sem borda, ondula com ruído lento e some na distância */
  const pos = [];
  for (let a = -30; a <= 30; a += 1) for (let b = -30; b < 30; b += 0.5) { pos.push(a, 0, b, a, 0, b + 0.5); pos.push(b, 0, a, b + 0.5, 0, a); }
  const gg = guardar(new THREE.BufferGeometry());
  gg.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  const gm = guardar(new THREE.ShaderMaterial({ uniforms: U, transparent: true, depthWrite: false, vertexShader: VERT_GRADE, fragmentShader: FRAG_GRADE }));
  const grade = new THREE.LineSegments(gg, gm);
  grade.position.y = -2.1;
  scene.add(grade);

  const quad = guardar(new THREE.PlaneGeometry(2, 2));
  const frio = guardar(new THREE.LineDashedMaterial({ color: 0x8A8984, dashSize: 0.09, gapSize: 0.07 }));
  const solda = guardar(new THREE.LineBasicMaterial({ color: 0x7C82FF, transparent: true }));
  const soldaF = guardar(new THREE.LineDashedMaterial({ color: 0x7C82FF, dashSize: 0.16, gapSize: 0.12, transparent: true, opacity: 0.6 }));
  const prumo = guardar(new THREE.LineDashedMaterial({ color: 0x4A4A47, dashSize: 0.06, gapSize: 0.08, transparent: true }));
  const alvoM = guardar(new THREE.MeshBasicMaterial({ transparent: true, opacity: 0, depthWrite: false }));
  const wire = guardar(new THREE.WireframeGeometry(new THREE.IcosahedronGeometry(1, 1)));
  const geoA = guardar(new THREE.SphereGeometry(1.15, 10, 8));
  const bc = document.createElement('canvas');
  bc.width = bc.height = 64;
  const bx = bc.getContext('2d');
  const bgr = bx.createRadialGradient(32, 32, 0, 32, 32, 32);
  bgr.addColorStop(0, 'rgba(255,255,245,1)'); bgr.addColorStop(0.25, 'rgba(255,230,160,.85)'); bgr.addColorStop(1, 'rgba(255,150,40,0)');
  bx.fillStyle = bgr; bx.fillRect(0, 0, 64, 64);
  const texPulso = guardar(new THREE.CanvasTexture(bc));
  texPulso.colorSpace = THREE.SRGBColorSpace;
  const matPulso = guardar(new THREE.PointsMaterial({ map: texPulso, color: 0xFFFFFF, size: 0.42, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false }));
  let texSelo = null, matSelo = null;

  /* ---------- estado ---------- */
  let objs = {}, alvos = [], segs = [], fase = [], pp = null, pg = null, raizMapa = null, mapaDescartar = [];
  let geo = null;                                          /* disposicao() do mapa atual */
  let rotulos = new Map(), larg = new Map();
  let rotY = -0.35, rotX = 0, alvoY = null, pausa = 0, inercia = 0;   /* inercia: rad/s do último arraste */
  let sujo = true, visivel = true, ligado = true, W = 1, H = 1, raf = 0, morto = false;
  let sel = null;
  let voo = null, nasce = null, saida = null;
  const base = { pos: new THREE.Vector3(), look: new THREE.Vector3(0, -0.3, 0) };
  const olhar = new THREE.Vector3(), tmp = new THREE.Vector3(), tmp2 = new THREE.Vector3();
  const t0 = performance.now();
  let ult = t0;
  const animar = () => !reduz.matches && !window.cblParado;

  function medir() {
    const r = palco.getBoundingClientRect();
    W = Math.max(1, r.width); H = Math.max(1, r.height);
    rd.setSize(W, H, false);
    cam.aspect = W / H;
    const raio = geo ? geo.raio : 3.3;
    const d = Math.max(9.2, Math.min(24, (raio + 0.8) / (0.384 * Math.max(cam.aspect, 0.8))));
    base.pos.set(0, 3.2 * (d / 9.2), d);
    base.look.set(0, cam.aspect < 0.9 ? -1.5 : -0.3, 0);
    cam.position.copy(base.pos); cam.lookAt(base.look);
    cam.updateProjectionMatrix();
    sujo = true;
  }

  function gotaMat(semente, R) {
    const u = { T: U.T, S: { value: semente }, Bri: { value: 0 }, SZ: { value: R * 1.5 }, K: { value: R * 0.38 } };
    for (let k = 0; k < 5; k++) u['B' + k] = { value: new THREE.Vector4(0, 0, 0, R * 0.3) };
    return new THREE.ShaderMaterial({ uniforms: u, vertexShader: VERT_GOTA, fragmentShader: FRAG_GOTA });
  }

  /* ---------- montar um mapa ---------- */
  function limparMapa() {
    if (raizMapa) { grupo.remove(raizMapa); }
    mapaDescartar.forEach((d) => d.dispose());
    mapaDescartar = []; objs = {}; alvos = []; segs = []; raizMapa = null; pg = null; pp = null;
  }

  function montar(dados) {
    limparMapa();
    geo = disposicao(dados);
    raizMapa = new THREE.Group();
    grupo.add(raizMapa);
    const rg = (() => { let s = 7; return () => { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; }; })();
    geo.itens.forEach((it, i) => {
      const no = new THREE.Group();
      no.position.set(it.p[0], it.p[1], it.p[2]);
      let corpo, bolas = null;
      if (!it.centro && !it.aberto) {
        corpo = new THREE.LineSegments(wire, frio);
        corpo.computeLineDistances();
        corpo.scale.setScalar(it.r);
      } else {
        const m = gotaMat(i * 3.7 + 1.3, it.r);
        mapaDescartar.push(m);
        corpo = new THREE.Mesh(quad, m);
        corpo.frustumCulled = false;
        bolas = [{ f: rg() * 6.28 }];
        for (let j = 0; j < 4; j++) bolas.push({ w: 0.22 + rg() * 0.28, w2: 0.3 + rg() * 0.35, f: rg() * 6.28, o: 0.5 + rg() * 0.25, r: 0.24 + rg() * 0.1 });
      }
      no.add(corpo);
      let selo = null;
      if (it.selo) {
        if (!texSelo) {
          texSelo = new THREE.TextureLoader().load(BASE + '/assets/img/mini-256.webp', () => { sujo = true; agenda(); });
          texSelo.colorSpace = THREE.SRGBColorSpace;
          matSelo = new THREE.SpriteMaterial({ map: texSelo, depthTest: false, transparent: true });
        }
        selo = new THREE.Sprite(matSelo);        /* selo: sempre estático, sem brilho nem sombra */
        selo.scale.setScalar(1.05);
        selo.renderOrder = 10;
        no.add(selo);
      }
      const al = new THREE.Mesh(geoA, alvoM);
      al.scale.setScalar(it.r);
      al.userData.id = it.id;
      no.add(al); alvos.push(al);
      const pgeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, -it.r, 0), new THREE.Vector3(0, -2.1 - it.p[1], 0)]);
      mapaDescartar.push(pgeo);
      const pr = new THREE.Line(pgeo, prumo);
      pr.computeLineDistances();
      no.add(pr);
      raizMapa.add(no);
      objs[it.id] = { g: no, corpo, it, bolas, sel: false, selo, atraso: i === 0 ? 0 : 0.25 + (i - 1) * 0.09, nasc: 1 };
    });
    segs = [];
    geo.ligs.forEach(([a, b]) => {
      const A = geo.itens[a], B = geo.itens[b];
      const f = !A.aberto && !A.centro || !B.aberto && !B.centro;
      const va = new THREE.Vector3().fromArray(A.p), vb = new THREE.Vector3().fromArray(B.p);
      const g = new THREE.BufferGeometry().setFromPoints([va, vb]);
      mapaDescartar.push(g);
      const ln = new THREE.Line(g, f ? soldaF : solda);
      if (f) ln.computeLineDistances();
      raizMapa.add(ln);
      segs.push([va, vb]);
    });
    pp = new Float32Array(segs.length * 3);
    pg = new THREE.BufferGeometry();
    pg.setAttribute('position', new THREE.BufferAttribute(pp, 3));
    mapaDescartar.push(pg);
    const pulsos = new THREE.Points(pg, matPulso);
    pulsos.frustumCulled = false;
    raizMapa.add(pulsos);
    fase = segs.map((_, i) => i * 0.37);
    moverGotas(4); moverPulsos(4);
  }

  function moverGotas(t) {
    for (const k in objs) {
      const o = objs[k];
      if (!o.bolas) continue;
      const u = o.corpo.material.uniforms, R = o.it.r * (o.sel ? 1.12 : 1) * Math.max(0.001, o.nasc);
      o.corpo.visible = o.nasc > 0.01;
      o.bolas.forEach((b, j) => {
        if (j === 0) { u.B0.value.set(Math.sin(t * 0.4 + b.f) * 0.05 * R, Math.cos(t * 0.33 + b.f) * 0.05 * R, 0, 0.62 * R); return; }
        const a = t * b.w + b.f, rr = (b.o + 0.22 * Math.sin(t * b.w2 + b.f)) * R;
        u['B' + j].value.set(Math.cos(a) * rr, Math.sin(a * 1.3 + b.f) * rr * 0.8, Math.sin(a) * rr, b.r * R);
      });
      u.SZ.value = R * 1.5; u.K.value = R * 0.38;
    }
  }
  function moverPulsos(t) {
    segs.forEach((s, i) => {
      const k = (((t * 0.45 + fase[i]) % 1) + 1) % 1, u = i % 2 ? 1 - k : k;
      pp[i * 3] = s[0].x + (s[1].x - s[0].x) * u; pp[i * 3 + 1] = s[0].y + (s[1].y - s[0].y) * u; pp[i * 3 + 2] = s[0].z + (s[1].z - s[0].z) * u;
    });
    if (pg) pg.attributes.position.needsUpdate = true;
  }

  /* ---------- rótulos HTML sobre a cena ---------- */
  function rotular() {
    const L = [];
    for (const id in objs) {
      const o = objs[id], b = rotulos.get(id);
      if (!b) continue;
      o.g.getWorldPosition(tmp);
      /* centro: etiqueta colada no meio da gota (logo abaixo do selo); nós: bem rente à gota */
      const k = Math.max(0.2, o.nasc);
      if (o.it.centro) tmp.y -= o.it.selo ? 0.62 : 0.1;
      else tmp.y -= o.it.r * k * (o.it.aberto ? 0.78 : 0.95) + 0.1;
      tmp.project(cam);
      let w = larg.get(id); if (!w) { w = b.offsetWidth || 80; larg.set(id, w); }
      const h = b.offsetHeight || 32;
      const x = Math.round(Math.min(W - w / 2 - 6, Math.max(w / 2 + 6, (tmp.x * 0.5 + 0.5) * W)));
      const y = Math.round(o.it.centro ? (-tmp.y * 0.5 + 0.5) * H - h / 2 : Math.min(H - 40, (-tmp.y * 0.5 + 0.5) * H));
      b.style.transform = `translate(${x}px,${y}px) translate(-50%,0)`;
      b.style.zIndex = o.it.centro ? '2000' : String(Math.max(1, Math.round((1 - tmp.z) * 1000)));
      L.push({ b, x, y, w, h, z: o.it.centro ? -9 : tmp.z, vis: o.nasc > 0.4, centro: o.it.centro, esc: false });
    }
    /* colisão na tela: o mais distante da câmera apaga (o centro nunca some) */
    for (let i = 0; i < L.length; i++) for (let j = i + 1; j < L.length; j++) {
      const a = L[i], c = L[j];
      if (Math.abs(a.x - c.x) < (a.w + c.w) / 2 + 2 && Math.abs(a.y - c.y) < (a.h + c.h) / 2) {
        const longe = a.z > c.z ? a : c;
        if (!longe.centro) longe.esc = true;
      }
    }
    for (const l of L) {
      const foco = l.esc && (l.b.matches(':hover') || l.b === document.activeElement);
      l.b.style.opacity = !l.vis ? '0' : (l.esc && !foco ? '0.16' : '1');
    }
  }

  /* ---------- quadro ---------- */
  function quadro(t) {
    raf = 0;
    const dt = Math.min(0.05, (t - ult) / 1000); ult = t;
    const an = animar();
    if (an) { U.T.value = (t - t0) / 1000; moverPulsos(U.T.value); }
    /* nascimento do mapa: gotas se formam em sequência; câmera sai de dentro da gota */
    if (nasce) {
      const k = (t - nasce.t0) / nasce.dur;
      let acabou = k >= 1;
      for (const id in objs) { const o = objs[id]; o.nasc = suave(0, 1, (k * nasce.dur / 1000 - o.atraso) / 0.9); }
      const e = 1 - Math.pow(1 - Math.min(1, k), 3);
      cam.position.lerpVectors(nasce.de, base.pos, e);
      olhar.lerpVectors(nasce.deOlhar, base.look, e);
      cam.lookAt(olhar);
      const so = suave(0.1, 0.8, k);
      solda.opacity = so; soldaF.opacity = 0.6 * so; prumo.opacity = so;
      if (acabou) fimNasce();
    }
    if (voo) {
      const k = Math.min(1, (t - voo.t0) / voo.dur), e = Math.pow(k, 2.3);
      cam.position.lerpVectors(voo.de, voo.para, e);
      olhar.lerpVectors(voo.deOlhar, voo.alvo, Math.min(1, e * 1.7));
      cam.lookAt(olhar);
      op.clarao && (op.clarao.style.opacity = String(suave(0.6, 0.98, k)));
      if (k >= 1) fimVoo();
    }
    if (saida) {
      const k = Math.min(1, (t - saida.t0) / saida.dur), e = k * k;
      cam.position.lerpVectors(saida.de, saida.para, e);
      cam.lookAt(base.look);
      for (const id in objs) objs[id].nasc = 1 - suave(0, 1, k * 1.1);
      op.clarao && (op.clarao.style.opacity = String(suave(0.55, 0.98, k)));
      if (k >= 1) fimSaida();
    }
    if (an) moverGotas(U.T.value);
    else if (nasce || saida) moverGotas(U.T.value);
    if (alvoY !== null) {
      const d = dif(rotY, alvoY);
      if (!an || Math.abs(d) < 0.002) { rotY += d; alvoY = null; } else rotY += d * Math.min(1, dt * 5);
    } else if (inercia && !arr) {
      if (!an || voo || saida) inercia = 0;
      else { rotY += inercia * dt; inercia *= Math.exp(-dt * 5); if (Math.abs(inercia) < 0.01) inercia = 0; pausa = performance.now() + 2500; }
    } else if (an && t > pausa && !voo && !saida) rotY += dt * 0.08;
    if (an || sujo || voo || nasce || saida) {
      grupo.rotation.set(rotX, rotY, 0);
      rd.render(scene, cam);
      rotular();
      sujo = false;
    }
    agenda();
  }
  function precisa() { return (animar() || sujo || alvoY !== null || inercia || voo || nasce || saida); }
  function agenda() {
    if (!raf && !morto && visivel && ligado && !document.hidden && precisa()) raf = requestAnimationFrame(quadro);
  }
  function cutuca() { sujo = true; agenda(); }
  const aoMovimento = () => { ult = performance.now(); cutuca(); };
  window.addEventListener('cbl-movimento', aoMovimento);

  /* ---------- voo para dentro da gota ---------- */
  function fimVoo() {
    if (!voo) return;
    clearTimeout(voo.timer);
    const v = voo; voo = null;
    if (op.clarao) op.clarao.style.opacity = '1';
    v.res();
  }
  function voar(id) {
    return new Promise((res) => {
      const o = objs[id];
      if (!o || reduz.matches || window.cblParado || document.hidden || !ligado) { if (op.clarao) op.clarao.style.opacity = '1'; res(); return; }
      fimVoo();
      grupo.updateMatrixWorld(true);
      o.g.getWorldPosition(tmp);
      const alvo = tmp.clone();
      tmp2.copy(cam.position).sub(alvo).normalize();
      const para = alvo.clone().add(tmp2.multiplyScalar(o.it.r * 0.95));
      if (op.clarao) op.clarao.style.transition = 'none';
      voo = { t0: performance.now(), dur: 1000, de: cam.position.clone(), para, deOlhar: base.look.clone(), alvo, res, timer: 0 };
      voo.timer = setTimeout(fimVoo, voo.dur + 500);       /* rAF parado (aba oculta): a navegação não trava */
      alvoY = null;
      cutuca();
    });
  }

  /* voltar: a câmera recua e o clarão cobre; o mapa do pai nasce a partir da gota de onde viemos */
  function fimSaida() {
    if (!saida) return;
    clearTimeout(saida.timer);
    const s = saida; saida = null;
    if (op.clarao) op.clarao.style.opacity = '1';
    s.res();
  }
  function sair() {
    return new Promise((res) => {
      if (reduz.matches || window.cblParado || document.hidden || !ligado) { if (op.clarao) op.clarao.style.opacity = '1'; res(); return; }
      fimSaida();
      const para = cam.position.clone().sub(base.look).multiplyScalar(1.9).add(base.look);
      if (op.clarao) op.clarao.style.transition = 'none';
      saida = { t0: performance.now(), dur: 650, de: cam.position.clone(), para, res, timer: 0 };
      saida.timer = setTimeout(fimSaida, saida.dur + 500);
      cutuca();
    });
  }

  function fimNasce() {
    nasce = null;
    for (const id in objs) objs[id].nasc = 1;
    solda.opacity = 1; soldaF.opacity = 0.6; prumo.opacity = 1;
    cam.position.copy(base.pos); cam.lookAt(base.look);
    sujo = true;
  }

  /* definir(dados, rot, entrada, de): monta o mapa e faz as gotas nascerem.
     entrada: 'dentro' (veio de um nó: sai do centro) | 'fora' (voltou: sai da gota `de`) | 'nenhuma' */
  function definir(dados, rot, entrada, de) {
    rotulos = rot; larg = new Map();
    sel = null; saida = null; voo = null;
    montar(dados);
    medir();
    rotY = -0.35; alvoY = null; inercia = 0; pausa = performance.now() + 2500;
    grupo.rotation.set(rotX, rotY, 0);
    grupo.updateMatrixWorld(true);
    const quer = entrada !== 'nenhuma' && !reduz.matches && !window.cblParado;
    if (quer) {
      const qual = (entrada === 'fora' && de && objs[de]) ? objs[de] : objs._centro;
      qual.g.getWorldPosition(tmp);
      const alvo = tmp.clone();
      const dir = base.pos.clone().sub(alvo).normalize();
      const de3 = alvo.clone().add(dir.multiplyScalar(qual.it.r * 0.95));
      nasce = { t0: performance.now(), dur: 1900, de: de3, deOlhar: alvo };
      for (const id in objs) objs[id].nasc = 0;
      solda.opacity = 0; soldaF.opacity = 0; prumo.opacity = 0;
      cam.position.copy(de3); cam.lookAt(alvo);
      moverGotas(U.T.value);
    } else {
      nasce = null;
      solda.opacity = 1; soldaF.opacity = 0.6; prumo.opacity = 1;
    }
    if (op.clarao) requestAnimationFrame(() => { op.clarao.style.transition = 'opacity .9s ease-out'; op.clarao.style.opacity = '0'; });
    if (reduz.matches) { U.T.value = 4; moverPulsos(4); moverGotas(4); }
    cutuca();
  }

  function clarear() { if (op.clarao) op.clarao.style.opacity = '0'; }

  /* ---------- seleção ---------- */
  function realcar(id) {
    sel = id;
    for (const k in objs) {
      const o = objs[k];
      if (!o.bolas) continue;
      const s = k === id && !o.it.centro;
      o.corpo.material.uniforms.Bri.value = s ? 0.18 : 0;
      o.sel = s;
    }
    moverGotas(U.T.value); cutuca();
  }
  function focar(id) {
    realcar(id);
    const o = objs[id];
    if (o && !o.it.centro) { inercia = 0; alvoY = Math.atan2(-o.it.p[0], o.it.p[2]); pausa = performance.now() + 8000; }
    cutuca();
  }

  /* ---------- interação: arrastar gira, tocar abre ---------- */
  const ray = new THREE.Raycaster(), ptr = new THREE.Vector2();
  let arr = null, passando = null, amostras = [];
  function acertar(e) {
    const r = canvas.getBoundingClientRect();
    ptr.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(ptr, cam);
    const h = ray.intersectObjects(alvos, false)[0];
    return h ? h.object.userData.id : null;
  }
  const onDown = (e) => { arr = { x: e.clientX, y: e.clientY, rx: rotX, ry: rotY, mov: 0, id: e.pointerId }; pausa = performance.now() + 6000; alvoY = null; inercia = 0; amostras = [{ t: performance.now(), r: rotY }]; };
  const onMove = (e) => {
    if (!arr) {
      if (e.target !== canvas || voo || saida) return;
      const id = acertar(e);
      if (id !== passando) { passando = id; canvas.style.cursor = id ? 'pointer' : ''; op.aoPassar && op.aoPassar(id); }
      return;
    }
    if (e.pointerId !== arr.id) return;
    const dx = e.clientX - arr.x, dy = e.clientY - arr.y;
    arr.mov = Math.max(arr.mov, Math.abs(dx) + Math.abs(dy));
    rotY = arr.ry + dx * 0.008; rotX = Math.max(-0.3, Math.min(0.3, arr.rx + dy * 0.004));
    const agora = performance.now();
    amostras.push({ t: agora, r: rotY }); if (amostras.length > 8) amostras.shift();
    pausa = agora + 6000; cutuca();
  };
  const onUp = (e) => {
    if (!arr || e.pointerId !== arr.id) return;
    const mov = arr.mov; arr = null;
    /* inércia: velocidade do gesto nos últimos ~100 ms (parado antes de soltar = sem inércia) */
    const fim = performance.now(), ini = amostras.find((a) => fim - a.t <= 100);
    if (mov >= 8 && ini && !reduz.matches && !window.cblParado && fim - amostras[amostras.length - 1].t < 80) {
      const dtv = Math.max(0.016, (amostras[amostras.length - 1].t - ini.t) / 1000);
      inercia = Math.max(-4, Math.min(4, 0.6 * (amostras[amostras.length - 1].r - ini.r) / dtv));
      if (Math.abs(inercia) < 0.15) inercia = 0;
      cutuca();
    }
    if (mov < 8 && !voo && !saida) { const id = acertar(e); if (id) op.aoClicar(id); }
  };
  const onCancel = () => { arr = null; inercia = 0; };
  canvas.addEventListener('pointerdown', onDown);
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  window.addEventListener('pointercancel', onCancel);
  const onSai = () => { if (passando) { passando = null; canvas.style.cursor = ''; op.aoPassar && op.aoPassar(null); } };
  canvas.addEventListener('pointerleave', onSai);

  const io = 'IntersectionObserver' in window ? new IntersectionObserver((es) => { visivel = es[0].isIntersecting; ult = performance.now(); agenda(); }) : null;
  io && io.observe(palco);
  const aoOcultar = () => { ult = performance.now(); agenda(); };
  document.addEventListener('visibilitychange', aoOcultar);
  const ro = 'ResizeObserver' in window ? new ResizeObserver(() => { larg = new Map(); medir(); cutuca(); }) : null;
  ro && ro.observe(palco);
  const aoReduzir = () => cutuca();
  reduz.addEventListener ? reduz.addEventListener('change', aoReduzir) : reduz.addListener(aoReduzir);

  function ativar(on) {
    ligado = on;
    canvas.hidden = !on;
    if (on) { ult = performance.now(); medir(); cutuca(); }
  }

  function destruir() {
    morto = true;
    cancelAnimationFrame(raf);
    clearTimeout(voo && voo.timer); clearTimeout(saida && saida.timer);
    window.removeEventListener('cbl-movimento', aoMovimento);
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('pointercancel', onCancel);
    document.removeEventListener('visibilitychange', aoOcultar);
    reduz.removeEventListener ? reduz.removeEventListener('change', aoReduzir) : reduz.removeListener(aoReduzir);
    io && io.disconnect(); ro && ro.disconnect();
    limparMapa();
    descartar.forEach((d) => d.dispose && d.dispose());
    if (texSelo) texSelo.dispose();
    if (matSelo) matSelo.dispose();
    rd.dispose();
    try { rd.forceContextLoss(); } catch (_) { /* nada */ }
    canvas.remove();
  }

  return { canvas, definir, voar, sair, clarear, focar, realcar, ativar, destruir, tipo: '3d' };
}
