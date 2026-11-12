/* Ciberlurgia: painel admin. Monta o editor a partir do esquema servido em JSON.
   Sem bibliotecas e sem innerHTML com dados: tudo via DOM. */
(() => {
  'use strict';

  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  /* Pasta do site (vazia na raiz). Os dados guardam /uploads/x.webp sem base: só a exibição ganha a base. */
  const BASE = document.documentElement.dataset.base || '';
  const ver = (u) => (typeof u === 'string' && u.startsWith('/') && !u.startsWith('//') ? BASE + u : u);

  /* ---------- Utilidades ---------- */

  function h(tag, attrs, ...filhos) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (v === true) el.setAttribute(k, '');
      else el.setAttribute(k, String(v));
    }
    for (const f of filhos.flat()) {
      if (f === null || f === undefined || f === false) continue;
      el.append(f.nodeType ? f : document.createTextNode(String(f)));
    }
    return el;
  }

  let seq = 0;
  const novoId = (p) => `${p}-${++seq}`;

  function anunciar(msg) {
    const v = document.getElementById('aviso-vivo');
    if (v) { v.textContent = ''; setTimeout(() => { v.textContent = msg; }, 30); }
  }

  function slugify(t) {
    return String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80).replace(/-+$/g, '');
  }

  function tamanho(b) {
    return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
  }

  async function api(caminho, { metodo = 'GET', corpo = null, form = null } = {}) {
    const opc = { method: metodo, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } };
    if (corpo !== null) { opc.headers['Content-Type'] = 'application/json'; opc.body = JSON.stringify(corpo); }
    if (form) opc.body = form;
    let r;
    try { r = await fetch(BASE + '/admin/api/' + caminho, opc); }
    catch (e) { return { status: 0, dados: { ok: false, erro: 'Sem conexão com o servidor.' } }; }
    if (r.status === 401) { location.href = BASE + '/admin/entrar'; return { status: 401, dados: { ok: false, erro: 'Sessão expirada.' } }; }
    let dados;
    try { dados = await r.json(); } catch (e) { dados = { ok: false, erro: 'Resposta inesperada do servidor.' }; }
    return { status: r.status, dados };
  }

  /* ---------- Modal e confirmação ---------- */

  function modal(titulo, corpo) {
    const id = novoId('modal');
    const dlg = h('dialog', { class: 'modal', 'aria-labelledby': id },
      h('div', { class: 'modal-cab' },
        h('h2', { id }, titulo),
        h('button', { type: 'button', class: 'btn peq', onclick: () => dlg.close() }, 'Fechar')),
      h('div', { class: 'modal-corpo' }, corpo));
    dlg.addEventListener('close', () => dlg.remove());
    document.body.append(dlg);
    dlg.showModal();
    return dlg;
  }

  function confirmar(mensagem, rotuloOk = 'Confirmar', perigo = true) {
    return new Promise((resolve) => {
      let resp = false;
      const dlg = modal('Confirmar', [
        h('p', {}, mensagem),
        h('div', { class: 'modal-rodape' },
          h('button', { type: 'button', class: 'btn', onclick: () => dlg.close() }, 'Cancelar'),
          h('button', { type: 'button', class: 'btn ' + (perigo ? 'perigo' : 'forte'), onclick: () => { resp = true; dlg.close(); } }, rotuloOk)),
      ]);
      dlg.addEventListener('close', () => resolve(resp));
    });
  }

  /* Formulários comuns com data-confirmar (ex.: remover usuário) */
  document.querySelectorAll('form[data-confirmar]').forEach((f) => {
    f.addEventListener('submit', async (ev) => {
      if (f.dataset.ok) return;
      ev.preventDefault();
      if (await confirmar(f.dataset.confirmar, 'Remover')) { f.dataset.ok = '1'; f.submit(); }
    });
  });

  /* ---------- Galeria ---------- */

  async function montarGaleria(alvo, { aoEscolher = null, dlg = null } = {}) {
    alvo.replaceChildren(h('p', {}, 'Carregando…'));
    const { dados } = await api('galeria');
    if (!dados.ok) { alvo.replaceChildren(h('p', { class: 'aviso erro', role: 'alert' }, dados.erro)); return; }
    if (!dados.itens.length) { alvo.replaceChildren(h('p', { class: 'mudo' }, 'Nenhuma imagem enviada ainda.')); return; }
    const ul = h('ul', { class: 'galeria' });
    for (const it of dados.itens) {
      const nome = it.url.split('/').pop();
      const li = h('li', {},
        h('img', { src: ver(it.url), alt: '', loading: 'lazy' }),
        h('span', { class: 'nome' }, nome, ' · ', tamanho(it.bytes)),
        it.usada ? h('span', { class: 'usada' }, 'Em uso no site') : null,
        aoEscolher ? h('button', { type: 'button', class: 'btn peq forte', 'aria-label': 'Usar a imagem ' + nome, onclick: () => { aoEscolher(it.url); dlg?.close(); } }, 'Usar') : null,
        it.usada ? null : h('button', {
          type: 'button', class: 'btn peq perigo', 'aria-label': 'Excluir a imagem ' + nome,
          onclick: async () => {
            if (!await confirmar('Excluir ' + nome + ' para sempre? Não dá para desfazer.', 'Excluir')) return;
            const r = await api('imagem-excluir', { metodo: 'POST', corpo: { url: it.url } });
            if (r.dados.ok) { anunciar('Imagem excluída.'); li.remove(); } else alert(r.dados.erro);
          },
        }, 'Excluir'));
      ul.append(li);
    }
    alvo.replaceChildren(ul);
  }

  async function enviarImagem(arquivo) {
    const form = new FormData();
    form.append('arquivo', arquivo);
    return api('upload', { metodo: 'POST', form });
  }

  const pagGaleria = document.getElementById('galeria-pagina');
  if (pagGaleria) {
    const lista = h('div', {});
    const status = h('p', { role: 'status' });
    const entrada = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', id: 'f-upload-galeria', class: 'oculto', hidden: true });
    entrada.addEventListener('change', async () => {
      if (!entrada.files[0]) return;
      status.textContent = 'Enviando…';
      const r = await enviarImagem(entrada.files[0]);
      entrada.value = '';
      status.textContent = r.dados.ok ? 'Enviada: ' + r.dados.url : r.dados.erro;
      if (r.dados.ok) montarGaleria(lista);
    });
    pagGaleria.replaceChildren(
      h('div', { class: 'botoes' }, h('button', { type: 'button', class: 'btn forte', onclick: () => entrada.click() }, 'Enviar imagem'), entrada),
      h('p', { class: 'dica' }, 'JPG, PNG ou WebP, até 5 MB. O site converte para WebP (largura máxima 1600 px) e tira os metadados.'),
      status, lista);
    montarGaleria(lista);
  }

  /* ---------- Editor de coleção ---------- */

  const appEl = document.getElementById('editor-app');
  if (appEl) iniciarEditor(appEl, JSON.parse(document.getElementById('pacote').textContent));

  function iniciarEditor(raiz, pacote) {
    const { nome, esquema, cidades } = pacote;
    const ehLista = esquema.tipo === 'lista';
    const campos = esquema.campos;
    const hoje = new Date().toISOString().slice(0, 10);

    const estado = { itens: [], sel: ehLista ? null : 0, filtro: '', sujo: false, erros: [], errosCampo: {}, errosItem: {} };

    function valorInicial(c) {
      switch (c.tipo) {
        case 'booleano': return false;
        case 'links': return [];
        case 'numero': return 0;
        case 'data': return c.obrigatorio ? hoje : '';
        default: return '';
      }
    }
    function normalizar(item) {
      const o = {};
      for (const c of campos) {
        let v = item ? item[c.nome] : undefined;
        if (v === undefined || v === null) v = valorInicial(c);
        if (c.tipo === 'tags' && Array.isArray(v)) v = v.join(', ');
        if (c.tipo === 'links') v = (Array.isArray(v) ? v : []).map((p) => ({ rotulo: p.rotulo || '', url: p.url || '' }));
        o[c.nome] = v;
      }
      return o;
    }
    function carregar(dados) {
      if (ehLista) estado.itens = (Array.isArray(dados) ? dados : []).map(normalizar);
      else estado.itens = [normalizar(Array.isArray(dados) ? {} : dados)];
    }
    carregar(pacote.dados);

    const campoTitulo = ehLista ? campos.find((c) => c.nome === esquema.titulo_item) : null;
    const campoChave = ehLista ? campos.find((c) => c.nome === esquema.chave) : null;
    const tituloDe = (it) => String(it[esquema.titulo_item] || '').trim() || '(sem título)';

    function marcarSujo() {
      if (!estado.sujo) { estado.sujo = true; atualizarBarra(); }
    }
    window.addEventListener('beforeunload', (ev) => {
      if (estado.sujo) { ev.preventDefault(); ev.returnValue = ''; }
    });

    /* Estrutura fixa da página */
    const btnSalvar = h('button', { type: 'button', class: 'btn forte', onclick: salvar }, 'Salvar');
    const marca = h('span', { class: 'sujo-marca', hidden: true }, 'Alterações não salvas');
    const btnBackups = h('button', { type: 'button', class: 'btn', onclick: abrirBackups }, 'Versões antigas');
    const resumo = h('div', { tabindex: '-1' });
    const colLista = h('section', { class: 'cartao coluna-lista', 'aria-label': 'Itens' });
    const colForm = h('section', { class: 'cartao form-item', 'aria-label': 'Edição' });

    raiz.replaceChildren(
      h('div', { class: 'editor-topo' },
        h('h1', {}, esquema.rotulo),
        h('div', { class: 'editor-acoes' }, marca, btnBackups, btnSalvar)),
      resumo,
      ehLista ? h('div', { class: 'painel2' }, colLista, colForm) : colForm);

    function atualizarBarra() { marca.hidden = !estado.sujo; }

    document.addEventListener('keydown', (ev) => {
      if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') { ev.preventDefault(); salvar(); }
    });

    /* ----- Lista ----- */

    const busca = h('input', { type: 'search', id: 'f-busca', autocomplete: 'off' });
    busca.addEventListener('input', () => { estado.filtro = busca.value.trim().toLowerCase(); desenharLista(); });
    const ulItens = h('ul', { class: 'lista-itens' });
    const contagem = h('p', { class: 'mudo', role: 'status' });

    if (ehLista) {
      colLista.append(
        h('button', { type: 'button', class: 'btn forte', onclick: adicionar }, '+ Adicionar'),
        h('div', { class: 'campo', style: 'margin-top:12px' }, h('label', { for: 'f-busca' }, 'Buscar'), busca),
        contagem, ulItens);
    }

    function combina(it) {
      if (!estado.filtro) return true;
      return JSON.stringify(Object.values(it)).toLowerCase().includes(estado.filtro);
    }

    function desenharLista() {
      if (!ehLista) return;
      ulItens.replaceChildren();
      const filtrando = !!estado.filtro;
      let vis = 0;
      estado.itens.forEach((it, i) => {
        if (!combina(it)) return;
        vis++;
        const t = tituloDe(it);
        const li = h('li', { class: (i === estado.sel ? 'ativo ' : '') + (estado.errosItem[i] || estado.errosCampo[i] ? 'com-erro' : '') },
          h('button', { type: 'button', class: 'item-abrir', 'aria-current': i === estado.sel ? 'true' : null, onclick: () => selecionar(i, true) },
            t, h('small', {}, String(it[esquema.chave] || ''))),
          h('div', { class: 'item-acoes' },
            h('button', { type: 'button', class: 'btn', 'aria-label': 'Subir ' + t, title: filtrando ? 'Limpe a busca para reordenar' : null, disabled: filtrando || i === 0, onclick: () => mover(i, -1) }, '▲'),
            h('button', { type: 'button', class: 'btn', 'aria-label': 'Descer ' + t, title: filtrando ? 'Limpe a busca para reordenar' : null, disabled: filtrando || i === estado.itens.length - 1, onclick: () => mover(i, 1) }, '▼'),
            h('button', { type: 'button', class: 'btn', 'aria-label': 'Duplicar ' + t, onclick: () => duplicar(i) }, 'Duplicar'),
            h('button', { type: 'button', class: 'btn perigo', 'aria-label': 'Excluir ' + t, onclick: () => excluir(i) }, 'Excluir')));
        ulItens.append(li);
      });
      if (!vis) ulItens.append(h('li', { class: 'vazio' }, estado.itens.length ? 'Nada encontrado.' : 'Ainda não tem nada. Clique em Adicionar.'));
      contagem.textContent = vis + ' de ' + estado.itens.length + ' itens';
    }

    function selecionar(i, rolar) {
      estado.sel = i;
      desenharLista();
      desenharForm();
      if (rolar && window.matchMedia('(max-width:900px)').matches) colForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function adicionar() {
      if (estado.filtro) { busca.value = ''; estado.filtro = ''; }
      estado.itens.push(normalizar(null));
      marcarSujo();
      selecionar(estado.itens.length - 1, true);
      colForm.querySelector('input:not([type=hidden]),select,textarea')?.focus();
      anunciar('Novo item adicionado.');
    }
    function chaveLivre(base) {
      const usados = new Set(estado.itens.map((x) => x[esquema.chave]));
      let k = base, n = 2;
      while (usados.has(k)) k = base.replace(/-\d+$/, '') + '-' + (n++);
      return k;
    }
    function duplicar(i) {
      const c = JSON.parse(JSON.stringify(estado.itens[i]));
      if (c[esquema.chave]) c[esquema.chave] = chaveLivre(String(c[esquema.chave]).slice(0, 70) + '-copia');
      if (campoTitulo && c[campoTitulo.nome]) c[campoTitulo.nome] = String(c[campoTitulo.nome]).slice(0, Math.max(0, campoTitulo.max - 9)) + ' (cópia)';
      estado.itens.splice(i + 1, 0, c);
      marcarSujo();
      reindexarErros();
      selecionar(i + 1, true);
      anunciar('Item duplicado.');
    }
    async function excluir(i) {
      if (!await confirmar('Excluir "' + tituloDe(estado.itens[i]) + '"? Só vale de verdade quando você salvar.', 'Excluir')) return;
      estado.itens.splice(i, 1);
      if (estado.sel === i) estado.sel = null;
      else if (estado.sel > i) estado.sel--;
      marcarSujo();
      reindexarErros();
      desenharLista();
      desenharForm();
      anunciar('Item excluído.');
    }
    function mover(i, d) {
      const j = i + d;
      if (j < 0 || j >= estado.itens.length) return;
      [estado.itens[i], estado.itens[j]] = [estado.itens[j], estado.itens[i]];
      if (estado.sel === i) estado.sel = j; else if (estado.sel === j) estado.sel = i;
      marcarSujo();
      reindexarErros();
      desenharLista();
      anunciar('Item movido para a posição ' + (j + 1) + '.');
    }
    function reindexarErros() {
      // As posições mudaram: os avisos antigos deixam de valer até o próximo salvar.
      estado.erros = []; estado.errosCampo = {}; estado.errosItem = {};
      desenharResumo();
    }

    /* ----- Formulário do item ----- */

    function itemAtual() { return ehLista ? estado.itens[estado.sel] : estado.itens[0]; }
    function idxAtual() { return ehLista ? estado.sel : 0; }

    function desenharForm() {
      colForm.replaceChildren();
      const it = itemAtual();
      if (!it) {
        colForm.append(h('p', { class: 'vazio' }, 'Escolha um item na lista ou adicione um novo.'));
        return;
      }
      const idx = idxAtual();
      if (ehLista) colForm.append(h('h2', {}, tituloDe(it), h('span', { class: 'badge' }, 'item ' + (idx + 1) + ' de ' + estado.itens.length)));
      for (const c of campos) colForm.append(criarCampo(c, it, idx));
    }

    function errosDo(idx, c) { return (estado.errosCampo[idx] || {})[c.nome] || []; }

    function criarCampo(c, it, idx) {
      const id = novoId('f-' + c.nome);
      const erros = errosDo(idx, c);
      const caixaErro = h('div', { class: 'erro-campo', id: id + '-erro', role: erros.length ? 'alert' : null }, erros.join(' '));
      const wrap = h('div', { class: 'campo' + (erros.length ? ' tem-erro' : '') + (c.tipo === 'booleano' ? ' campo-bool' : ''), 'data-campo': c.nome });
      const limpar = () => { wrap.classList.remove('tem-erro'); caixaErro.textContent = ''; };
      const set = (v) => { it[c.nome] = v; marcarSujo(); limpar(); };
      const descr = erros.length ? id + '-erro' : null;
      const rotulo = h('label', { for: id }, c.rotulo, c.obrigatorio ? h('span', { class: 'obrig', 'aria-hidden': 'true' }, ' *') : null);
      const comum = { id, 'aria-describedby': descr, 'aria-invalid': erros.length ? 'true' : null };
      let ctl;

      switch (c.tipo) {
        case 'booleano': {
          const cb = h('input', { type: 'checkbox', ...comum });
          cb.checked = !!it[c.nome];
          cb.addEventListener('change', () => set(cb.checked));
          wrap.append(h('label', { for: id }, cb, c.rotulo), caixaErro);
          return wrap;
        }
        case 'textarea':
        case 'markdown': {
          ctl = h('textarea', { ...comum, rows: c.tipo === 'markdown' ? 14 : 4, maxlength: c.max });
          ctl.value = it[c.nome] ?? '';
          const cont = h('div', { class: 'contador' });
          const atualizar = () => { cont.textContent = ctl.value.length + ' / ' + c.max; };
          atualizar();
          ctl.addEventListener('input', () => { set(ctl.value); atualizar(); });
          wrap.append(rotulo, ctl, cont);
          if (c.tipo === 'markdown') {
            wrap.append(
              h('span', { class: 'dica' }, 'Formato: ## título, ### subtítulo, - lista, > citação, **negrito**, *itálico*, [texto](endereço). Separe parágrafos com linha em branco.'),
              h('div', { class: 'botoes' }, h('button', { type: 'button', class: 'btn peq', onclick: () => previa(ctl.value) }, 'Prévia')));
          }
          break;
        }
        case 'selecao':
        case 'referencia': {
          ctl = h('select', { ...comum });
          const ops = c.tipo === 'selecao' ? c.opcoes.map((o) => ({ v: o, r: o })) : c.ref;
          ctl.append(h('option', { value: '' }, '— escolher —'));
          const atual = it[c.nome] ?? '';
          let achou = atual === '';
          for (const o of ops) { ctl.append(h('option', { value: o.v }, o.r + (c.tipo === 'referencia' && o.r !== o.v ? ' (' + o.v + ')' : ''))); if (o.v === atual) achou = true; }
          if (!achou) ctl.append(h('option', { value: atual }, atual + ' (não existe mais)'));
          ctl.value = atual;
          ctl.addEventListener('change', () => set(ctl.value));
          wrap.append(rotulo, ctl);
          break;
        }
        case 'links': {
          wrap.append(h('span', { class: 'rotulo', id }, c.rotulo));
          const linhas = h('div', { class: 'linhas-links' });
          const desenhar = () => {
            linhas.replaceChildren();
            (it[c.nome]).forEach((par, k) => {
              const i1 = novoId('lr'), i2 = novoId('lu');
              const r = h('input', { type: 'text', id: i1, 'aria-label': c.rotulo + ': rótulo ' + (k + 1), placeholder: 'Rótulo (ex.: Instagram)', maxlength: 60, value: par.rotulo });
              const u = h('input', { type: 'text', id: i2, 'aria-label': c.rotulo + ': endereço ' + (k + 1), placeholder: 'https://…', inputmode: 'url', maxlength: 500, value: par.url });
              r.addEventListener('input', () => { par.rotulo = r.value; set(it[c.nome]); });
              u.addEventListener('input', () => { par.url = u.value; set(it[c.nome]); });
              linhas.append(h('div', { class: 'linha-link' }, r, u,
                h('button', { type: 'button', class: 'btn peq perigo', 'aria-label': 'Remover link ' + (k + 1), onclick: () => { it[c.nome].splice(k, 1); set(it[c.nome]); desenhar(); } }, 'Remover')));
            });
          };
          desenhar();
          wrap.append(linhas, h('div', { class: 'botoes' }, h('button', { type: 'button', class: 'btn peq', onclick: () => {
            if (it[c.nome].length >= 12) { alert('Máximo de 12 links.'); return; }
            it[c.nome].push({ rotulo: '', url: '' }); set(it[c.nome]); desenhar();
            linhas.querySelector('.linha-link:last-child input')?.focus();
          } }, '+ Adicionar link')));
          break;
        }
        case 'imagem': {
          const entrada = h('input', { type: 'text', ...comum, inputmode: 'url', maxlength: 200, placeholder: '/uploads/…' });
          entrada.value = it[c.nome] ?? '';
          const mini = h('img', { class: 'miniatura', alt: '' });
          const status = h('span', { class: 'dica', role: 'status' });
          const arq = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', hidden: true, tabindex: '-1' });
          const mostrar = () => { if (entrada.value) mini.setAttribute('src', ver(entrada.value)); else mini.removeAttribute('src'); mini.style.visibility = entrada.value ? 'visible' : 'hidden'; };
          const definir = (v) => { entrada.value = v; set(v); mostrar(); };
          entrada.addEventListener('input', () => { set(entrada.value); mostrar(); });
          arq.addEventListener('change', async () => {
            if (!arq.files[0]) return;
            status.textContent = 'Enviando…';
            const r = await enviarImagem(arq.files[0]);
            arq.value = '';
            if (r.dados.ok) { definir(r.dados.url); status.textContent = 'Enviada.'; } else status.textContent = r.dados.erro;
          });
          mostrar();
          wrap.append(rotulo, h('div', { class: 'imagem-campo' }, mini, h('div', {},
            entrada,
            h('div', { class: 'botoes' },
              h('button', { type: 'button', class: 'btn peq', onclick: () => arq.click() }, 'Enviar imagem'),
              h('button', { type: 'button', class: 'btn peq', onclick: () => escolherDaGaleria(definir) }, 'Escolher da galeria'),
              h('button', { type: 'button', class: 'btn peq', onclick: () => definir('') }, 'Remover')),
            arq, status)));
          break;
        }
        default: {
          const tipoInput = { data: 'date', numero: 'number' }[c.tipo] || 'text';
          const extra = {};
          if (c.tipo === 'url') extra.inputmode = 'url';
          if (c.tipo === 'email') extra.inputmode = 'email';
          if (c.tipo === 'slug') { extra.autocapitalize = 'none'; extra.spellcheck = 'false'; }
          ctl = h('input', { type: tipoInput, ...comum, ...extra, maxlength: tipoInput === 'text' ? c.max : null });
          ctl.value = it[c.nome] ?? '';
          wrap.append(rotulo);
          if (c.tipo === 'slug') {
            ctl.addEventListener('input', () => set(ctl.value));
            const gerar = () => {
              if (!campoTitulo) return;
              const v = slugify(it[campoTitulo.nome] || '');
              if (v) { ctl.value = v; set(v); }
            };
            wrap.append(h('div', { class: 'linha-slug' }, ctl, campoTitulo ? h('button', { type: 'button', class: 'btn peq', onclick: gerar }, 'Gerar do título') : null));
            wrap.append(h('span', { class: 'dica' }, 'Só letras minúsculas, números e traços. Vira parte do endereço.'));
          } else {
            ctl.addEventListener('input', () => {
              const antes = it[c.nome];
              set(c.tipo === 'numero' ? ctl.value : ctl.value);
              if (campoTitulo && c.nome === campoTitulo.nome) tituloMudou(it, antes, ctl.value);
            });
            wrap.append(ctl);
            if (c.tipo === 'tags') wrap.append(h('span', { class: 'dica' }, 'Separe por vírgula.'));
          }
        }
      }
      wrap.append(caixaErro);
      return wrap;
    }

    /* Sugere o identificador a partir do título enquanto a pessoa não mexeu nele */
    function tituloMudou(it, antes, depois) {
      desenharLista();
      const h2 = colForm.querySelector('h2');
      if (h2 && h2.firstChild) h2.firstChild.textContent = tituloDe(it);
      if (!ehLista || !campoChave || campoChave.tipo !== 'slug') return;
      const atual = String(it[campoChave.nome] || '');
      if (atual === '' || atual === slugify(antes || '')) {
        it[campoChave.nome] = slugify(depois);
        const inp = colForm.querySelector('[data-campo="' + campoChave.nome + '"] input');
        if (inp) inp.value = it[campoChave.nome];
      }
    }

    /* ----- Prévia, galeria, backups ----- */

    async function previa(texto) {
      const caixa = h('div', { class: 'previa' }, 'Carregando…');
      modal('Prévia', caixa);
      const r = await api('previa', { metodo: 'POST', corpo: { texto } });
      if (!r.dados.ok) { caixa.textContent = r.dados.erro; return; }
      // O HTML vem de md() no servidor, que escapa tudo antes de formatar.
      caixa.innerHTML = r.dados.html || '<p class="mudo">Texto vazio.</p>';
      caixa.querySelectorAll('a').forEach((a) => { a.setAttribute('tabindex', '-1'); a.addEventListener('click', (e) => e.preventDefault()); });
    }

    function escolherDaGaleria(aoEscolher) {
      const alvo = h('div', {});
      const dlg = modal('Escolher da galeria', alvo);
      montarGaleria(alvo, { aoEscolher, dlg });
    }

    async function abrirBackups() {
      const alvo = h('div', {}, 'Carregando…');
      const dlg = modal('Versões antigas: ' + esquema.rotulo, alvo);
      const r = await api('backups/' + nome);
      if (!r.dados.ok) { alvo.textContent = r.dados.erro; return; }
      if (!r.dados.itens.length) { alvo.replaceChildren(h('p', { class: 'mudo' }, 'Ainda não há versões antigas. Elas aparecem a cada salvamento.')); return; }
      const corpo = h('tbody');
      for (const b of r.dados.itens) {
        corpo.append(h('tr', {},
          h('td', {}, b.quando),
          h('td', {}, ehLista ? b.itens + ' itens' : '—'),
          h('td', {}, tamanho(b.bytes)),
          h('td', {}, h('button', { type: 'button', class: 'btn peq', 'aria-label': 'Restaurar a versão de ' + b.quando, onclick: async () => {
            const aviso = estado.sujo ? ' As alterações não salvas aqui serão perdidas.' : '';
            if (!await confirmar('Restaurar a versão de ' + b.quando + '? O conteúdo atual vira uma versão antiga.' + aviso, 'Restaurar', false)) return;
            const rr = await api('restaurar', { metodo: 'POST', corpo: { colecao: nome, arquivo: b.arquivo } });
            if (rr.dados.ok) {
              estado.sujo = false;
              carregar(rr.dados.dados);
              estado.sel = ehLista ? null : 0;
              limparErros(); atualizarBarra(); desenharLista(); desenharForm();
              dlg.close();
              mostrarOk('Versão restaurada.');
            } else {
              alvo.prepend(h('p', { class: 'aviso erro', role: 'alert' }, 'Não deu para restaurar: ' + (rr.dados.erros || [rr.dados.erro]).join(' ')));
            }
          } }, 'Restaurar'))));
      }
      alvo.replaceChildren(h('table', { class: 'tabela-backups' },
        h('thead', {}, h('tr', {}, h('th', { scope: 'col' }, 'Quando'), h('th', { scope: 'col' }, 'Itens'), h('th', { scope: 'col' }, 'Tamanho'), h('th', { scope: 'col' }, 'Ação'))),
        corpo));
    }

    /* ----- Salvar e mostrar erros ----- */

    function limparErros() { estado.erros = []; estado.errosCampo = {}; estado.errosItem = {}; desenharResumo(); }

    function mapearErros(lista) {
      estado.errosCampo = {}; estado.errosItem = {};
      const porTamanho = [...campos].sort((a, b) => b.rotulo.length - a.rotulo.length);
      for (const msg of lista) {
        let idx = 0, resto = msg;
        if (ehLista) {
          const m = /^Item (\d+): ([\s\S]*)$/.exec(msg);
          if (!m) continue;
          idx = Number(m[1]) - 1; resto = m[2];
        }
        const c = porTamanho.find((x) => resto.startsWith(x.rotulo));
        if (c) { ((estado.errosCampo[idx] ||= {})[c.nome] ||= []).push(resto); }
        else (estado.errosItem[idx] ||= []).push(resto);
      }
    }

    function desenharResumo() {
      resumo.replaceChildren();
      if (!estado.erros.length) return;
      const ul = h('ul', {});
      for (const msg of estado.erros) {
        const m = ehLista ? /^Item (\d+):/.exec(msg) : null;
        ul.append(h('li', {}, m
          ? h('button', { type: 'button', onclick: () => selecionar(Number(m[1]) - 1, true) }, msg)
          : msg));
      }
      resumo.append(h('div', { class: 'resumo-erros', role: 'alert' }, h('h2', {}, 'Não salvei. Ajuste isto:'), ul));
    }

    function mostrarOk(msg) {
      resumo.replaceChildren(h('p', { class: 'aviso ok', role: 'status' }, msg));
      anunciar(msg);
    }

    let salvando = false;
    async function salvar() {
      if (salvando) return;
      salvando = true;
      btnSalvar.disabled = true;
      btnSalvar.textContent = 'Salvando…';
      const dados = ehLista ? estado.itens : estado.itens[0];
      const r = await api('salvar/' + nome, { metodo: 'POST', corpo: { dados } });
      salvando = false;
      btnSalvar.disabled = false;
      btnSalvar.textContent = 'Salvar';
      if (r.dados.ok) {
        const sel = estado.sel;
        carregar(r.dados.dados);
        estado.sel = ehLista ? (sel !== null && sel < estado.itens.length ? sel : null) : 0;
        estado.sujo = false;
        limparErros(); atualizarBarra(); desenharLista(); desenharForm();
        mostrarOk('Salvo às ' + r.dados.quando + '.');
        return;
      }
      estado.erros = r.dados.erros || [r.dados.erro || 'Erro ao salvar.'];
      mapearErros(estado.erros);
      desenharResumo();
      desenharLista();
      desenharForm();
      resumo.focus();
      resumo.scrollIntoView({ block: 'center' });
      // Se o item aberto não tem erro, abre o primeiro com erro
      if (ehLista) {
        const primeiro = Object.keys({ ...estado.errosCampo, ...estado.errosItem }).map(Number).sort((a, b) => a - b)[0];
        if (primeiro !== undefined && !(estado.errosCampo[estado.sel] || estado.errosItem[estado.sel])) selecionar(primeiro, false);
      }
    }

    desenharLista();
    desenharForm();
    if (ehLista && estado.itens.length) selecionar(0, false);
  }
})();
