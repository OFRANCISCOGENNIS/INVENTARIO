/* Analise de Inventario - tela Nova analise / Resultados / Ajuda.
   Os numeros vem do servidor (POST ?api=analisar); aqui ficam so estado,
   filtros, ordenacao, paginacao e exportacao CSV. */
(function () {
  'use strict';

  var EXT = ['xlsx', 'xlsm', 'xls', 'ods', 'csv'];
  var PAGINA = 25;
  var ST = {
    div: { label: 'Não aderente', icon: '×', dot: '#DC2626', ord: 0 },
    sem: { label: 'Sem correspondência', icon: '–', dot: '#94A3B8', ord: 1 },
    ver: { label: 'Verificar', icon: '!', dot: '#D97706', ord: 2 },
    ok: { label: 'Aderente', icon: '✓', dot: '#16A34A', ord: 3 }
  };

  var s = {
    view: 'nova', arquivos: [], modo: 'juntar', precos: null, ocupado: false, erro: '',
    res: null, aba: 0, q: '', sf: 'todos', pf: 'todas', ordem: 'status', dir: 'asc', pagina: 0, aberto: null
  };
  var uid = 0;
  var $ = function (id) { return document.getElementById(id); };
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  // ---------- formatos ----------
  var nf = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
  var brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  function fN(n) { return n == null ? '—' : nf.format(n); }
  function fBRL(n) { return n == null ? '—' : brl.format(n); }
  function fTam(b) { return b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
  function extDe(n) { return (n.split('.').pop() || '').toLowerCase(); }
  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }

  // ---------- navegacao ----------
  function ir(view) {
    if (view === 'resultados' && !s.res) return;
    s.view = view;
    ['nova', 'resultados', 'ajuda'].forEach(function (v) { $('view-' + v).hidden = v !== view; });
    document.querySelectorAll('.nav-item').forEach(function (b) {
      if (b.dataset.view === view) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
      if (b.dataset.view === 'resultados') b.disabled = !s.res;
    });
    $('topo-nova').hidden = view === 'nova';
    window.scrollTo(0, 0);
  }
  document.querySelectorAll('.nav-item').forEach(function (b) { b.addEventListener('click', function () { ir(b.dataset.view); }); });
  $('topo-nova').addEventListener('click', function () { ir('nova'); });

  // ---------- nova analise ----------
  function adicionar(lista) {
    var erros = [];
    lista.forEach(function (f) {
      var ext = extDe(f.name);
      if (EXT.indexOf(ext) < 0) { erros.push('"' + f.name + '": formato não suportado. Use XLSX, XLSM, XLS, ODS ou CSV.'); return; }
      if (f.size === 0) { erros.push('"' + f.name + '": o arquivo está vazio.'); return; }
      if (s.arquivos.some(function (x) { return x.name === f.name && x.size === f.size; })) { erros.push('"' + f.name + '" já foi selecionado.'); return; }
      s.arquivos.push({ id: ++uid, name: f.name, size: f.size, ext: ext, file: f });
    });
    s.errosUpload = erros; s.erroCampo = ''; s.erro = '';
    desenharNova();
  }

  function desenharNova() {
    var n = s.arquivos.length, multi = n > 1;
    var dz = $('dropzone');
    dz.classList.toggle('com-erro', !!s.erroCampo);
    $('erro-campo').hidden = !s.erroCampo; $('erro-campo').textContent = s.erroCampo || '';
    var eu = s.errosUpload || [];
    $('erros-upload').hidden = eu.length === 0;
    $('erros-upload-lista').innerHTML = eu.map(function (e) { return '<div>' + esc(e) + '</div>'; }).join('');

    $('arquivos-bloco').hidden = n === 0;
    $('arquivos-rotulo').textContent = n + (n === 1 ? ' arquivo selecionado' : ' arquivos selecionados');
    $('limpar').disabled = s.ocupado;
    var ul = $('lista-arquivos'); ul.innerHTML = '';
    s.arquivos.forEach(function (f) {
      var li = el('li', '', '<span class="ext">' + esc(f.ext) + '</span><div class="arq-info"><div class="arq-nome">' + esc(f.name) +
        '</div><div class="arq-tam">' + fTam(f.size) + '</div></div>');
      var b = el('button', 'btn-remover', 'Remover'); b.type = 'button'; b.disabled = s.ocupado;
      b.setAttribute('aria-label', 'Remover ' + f.name);
      b.addEventListener('click', function () { s.arquivos = s.arquivos.filter(function (x) { return x.id !== f.id; }); desenharNova(); });
      li.appendChild(b); ul.appendChild(li);
    });

    $('modo-dica').textContent = multi ? 'Defina como os arquivos selecionados serão processados.' : 'Disponível quando houver mais de um arquivo selecionado.';
    $('modos').classList.toggle('inativo', !multi);
    document.querySelectorAll('.modo').forEach(function (b) {
      b.disabled = !multi || s.ocupado;
      b.setAttribute('aria-checked', String(multi && b.dataset.modo === s.modo));
    });

    $('mm-vazio').hidden = !!s.precos; $('mm-arquivo').hidden = !s.precos;
    $('escolher-precos').disabled = s.ocupado;
    if (s.precos) {
      var mm = $('mm-arquivo');
      mm.innerHTML = '<span class="ext">' + esc(s.precos.ext) + '</span><div class="arq-info"><div class="arq-nome">' + esc(s.precos.name) +
        '</div><div class="arq-tam">' + fTam(s.precos.size) + '</div></div>';
      var rb = el('button', 'btn-remover', 'Remover'); rb.type = 'button'; rb.disabled = s.ocupado;
      rb.setAttribute('aria-label', 'Remover base de preços');
      rb.addEventListener('click', function () { s.precos = null; desenharNova(); });
      mm.appendChild(rb);
    }
    $('erro-precos').hidden = !s.erroPrecos; $('erro-precos').textContent = s.erroPrecos || '';

    var ra = $('r-arquivos'); ra.textContent = n ? String(n) : 'Nenhum'; ra.classList.toggle('vazio', !n);
    $('r-modo').textContent = !n ? '—' : !multi ? 'Análise única' : s.modo === 'juntar' ? 'Juntar bases' : 'Separar bases';
    $('r-precos').textContent = s.precos ? 'Incluída' : 'Não incluída';
    $('r-dica').hidden = n > 0 || s.ocupado;
    $('r-erro').hidden = !s.erro; $('r-erro-texto').textContent = s.erro || '';

    var bt = $('executar');
    bt.disabled = s.ocupado; bt.setAttribute('aria-busy', String(s.ocupado));
    bt.classList.toggle('ocupado', s.ocupado);
    bt.innerHTML = s.ocupado ? '<span class="giro" aria-hidden="true"></span>Analisando as bases...' : (s.erro ? 'Tentar novamente' : 'Executar análise');
    $('aguarde').hidden = !s.ocupado;
  }

  $('escolher').addEventListener('click', function () { $('arquivos').click(); });
  $('arquivos').addEventListener('change', function (e) { adicionar(Array.prototype.slice.call(e.target.files || [])); e.target.value = ''; });
  var dz = $('dropzone');
  dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('arrastando'); });
  dz.addEventListener('dragleave', function () { dz.classList.remove('arrastando'); });
  dz.addEventListener('drop', function (e) {
    e.preventDefault(); dz.classList.remove('arrastando');
    if (!s.ocupado) adicionar(Array.prototype.slice.call(e.dataTransfer.files || []));
  });
  $('fechar-erros').addEventListener('click', function () { s.errosUpload = []; desenharNova(); });
  $('limpar').addEventListener('click', function () { s.arquivos = []; s.errosUpload = []; desenharNova(); });
  document.querySelectorAll('.modo').forEach(function (b) { b.addEventListener('click', function () { s.modo = b.dataset.modo; desenharNova(); }); });
  $('escolher-precos').addEventListener('click', function () { $('precos').click(); });
  $('precos').addEventListener('change', function (e) {
    var f = (e.target.files || [])[0]; e.target.value = '';
    if (!f) return;
    var ext = extDe(f.name);
    if (EXT.indexOf(ext) < 0) { s.erroPrecos = '"' + f.name + '": formato não suportado.'; desenharNova(); return; }
    s.precos = { name: f.name, size: f.size, ext: ext, file: f }; s.erroPrecos = '';
    desenharNova();
  });

  $('executar').addEventListener('click', executar);
  function executar() {
    if (s.ocupado) return;
    if (!s.arquivos.length) { s.erroCampo = 'Selecione ao menos um arquivo para executar a análise.'; desenharNova(); return; }
    var fd = new FormData();
    s.arquivos.forEach(function (f) { fd.append('bases[]', f.file, f.name); });
    if (s.precos) fd.append('precos', s.precos.file, s.precos.name);
    fd.append('modo', s.modo);
    s.ocupado = true; s.erro = ''; s.erroCampo = ''; desenharNova();

    fetch('?api=analisar', { method: 'POST', body: fd, headers: { 'X-CSRF': csrf }, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (t) {
          var j = null;
          try { j = JSON.parse(t); } catch (e) { /* resposta nao-JSON: erro do servidor */ }
          if (!j) throw new Error(r.status === 413 ? 'Os arquivos passam do limite de envio do servidor.' :
            'O servidor não respondeu como esperado (HTTP ' + r.status + '). Se a base for muito grande, tente de novo em instantes.');
          if (j.erro) throw new Error(j.erro);
          return j;
        });
      })
      .then(function (j) {
        s.ocupado = false;
        s.res = j; s.aba = 0; s.q = ''; s.sf = 'todos'; s.pf = 'todas'; s.pagina = 0; s.aberto = null; s.banner = true;
        $('busca').value = '';
        desenharNova(); prepararResultados(); ir('resultados');
      })
      .catch(function (e) {
        s.ocupado = false;
        s.erro = e && e.message ? e.message : 'Falha de conexão com o servidor. Verifique a rede e tente novamente.';
        if (e instanceof TypeError) s.erro = 'Falha de conexão com o servidor. Verifique a rede e tente novamente.';
        desenharNova();
      });
  }

  // ---------- resultados ----------
  function analise() { return s.res ? s.res.analises[s.aba] : null; }
  function linhasEscopo() { var a = analise(); return a ? a.linhas : []; }
  function filtradas() {
    var qq = s.q.trim().toLowerCase();
    var rows = linhasEscopo().filter(function (r) {
      return (s.sf === 'todos' || r.status === s.sf) && (s.pf === 'todas' || r.priceFlag === s.pf) &&
        (!qq || r.code.toLowerCase().indexOf(qq) >= 0 || r.desc.toLowerCase().indexOf(qq) >= 0 ||
          r.base.toLowerCase().indexOf(qq) >= 0 || r.pep4.toLowerCase().indexOf(qq) >= 0 || r.pep3.toLowerCase().indexOf(qq) >= 0);
    });
    var val = function (r) {
      if (s.ordem === 'status') return ST[r.status].ord;
      if (s.ordem === 'diff' || s.ordem === 'valor') return r[s.ordem] == null ? null : Math.abs(r[s.ordem]);
      return r[s.ordem];
    };
    var d = s.dir === 'asc' ? 1 : -1;
    return rows.slice().sort(function (a, b) {
      var x = val(a), y = val(b);
      if (x == null && y == null) return 0;
      if (x == null) return 1;
      if (y == null) return -1;
      var c = typeof x === 'string' ? x.localeCompare(y, 'pt-BR') : x - y;
      return c * d || Math.abs(b.valor || 0) - Math.abs(a.valor || 0);
    });
  }

  function prepararResultados() {
    var r = s.res;
    var modoTxt = r.modo === 'juntar' ? 'Juntar bases' : r.modo === 'separar' ? 'Separar bases' : 'Análise única';
    var a0 = r.analises[0];
    $('res-meta').textContent = a0.geradoEm + ' · ' + r.arquivos.length + (r.arquivos.length > 1 ? ' bases' : ' base') + ' · ' + modoTxt +
      ' · MIN/MAX ' + (r.precos ? 'incluída' : 'não incluída');
    var tabs = $('abas-base'); tabs.innerHTML = '';
    tabs.hidden = r.analises.length < 2;
    r.analises.forEach(function (a, i) {
      var b = el('button', 'aba-base'); b.type = 'button'; b.setAttribute('role', 'tab'); b.textContent = a.base;
      b.addEventListener('click', function () { s.aba = i; s.pagina = 0; s.aberto = null; desenharResultados(); });
      tabs.appendChild(b);
    });
    desenharResultados();
  }

  function kpi(rotulo, valor, sub, cor, chave) {
    var b = el(chave ? 'button' : 'div', 'kpi');
    if (chave) {
      b.type = 'button'; b.setAttribute('aria-pressed', String(s.sf === chave));
      b.addEventListener('click', function () { s.sf = s.sf === chave ? 'todos' : chave; s.pagina = 0; desenharResultados(); });
    }
    b.innerHTML = '<span class="kpi-rotulo"><span class="ponto" style="background:' + cor + '"></span>' + esc(rotulo) + '</span>' +
      '<span class="kpi-valor' + (valor.length > 12 ? ' longo' : '') + '">' + esc(valor) + '</span><span class="kpi-sub">' + esc(sub) + '</span>';
    return b;
  }

  function desenharResultados() {
    var r = s.res, a = analise(); if (!a) return;
    document.querySelectorAll('.aba-base').forEach(function (b, i) { b.setAttribute('aria-selected', String(i === s.aba)); });

    $('banner').hidden = !s.banner;
    $('banner-texto').textContent = r.analises.length > 1
      ? 'Foram geradas ' + r.analises.length + ' análises independentes — alterne entre elas pelas abas.'
      : fN(a.linhas.length) + ' registros processados.';
    var avisos = (r.avisos || []).concat(a.avisos || []);
    $('aviso').hidden = !avisos.length;
    $('aviso').innerHTML = avisos.map(function (t) { return '<div>' + esc(t) + '</div>'; }).join('');

    var esc0 = linhasEscopo(), rows = filtradas();
    var cnt = function (k) { return esc0.filter(function (x) { return x.status === k; }).length; };
    var pct = function (n) { return esc0.length ? (n / esc0.length * 100).toFixed(1).replace('.', ',') + '% do total' : ''; };
    var liq = esc0.reduce(function (t, x) { return t + (x.valor || 0); }, 0);

    var k = $('kpis'); k.innerHTML = '';
    k.appendChild(kpi('Registros analisados', fN(esc0.length), r.analises.length > 1 ? 'Nesta base' : r.arquivos.length + (r.arquivos.length > 1 ? ' bases' : ' base'), '#17365D'));
    k.appendChild(kpi('Não aderentes', fN(cnt('div')), pct(cnt('div')), ST.div.dot, 'div'));
    k.appendChild(kpi('Sem correspondência', fN(cnt('sem')), pct(cnt('sem')), ST.sem.dot, 'sem'));
    k.appendChild(kpi('Verificar', fN(cnt('ver')), pct(cnt('ver')), ST.ver.dot, 'ver'));
    k.appendChild(kpi('Aderentes', fN(cnt('ok')), pct(cnt('ok')), ST.ok.dot, 'ok'));
    k.appendChild(kpi('Obras reprovadas', fN(a.obras.reprovadas), 'de ' + fN(a.obras.total) + ' obras (PEP3)', '#B91C1C'));
    k.appendChild(kpi('Alertas críticos', fN(a.alertas), a.notaBase == null ? 'Ver relatório completo' : 'Saúde da base: ' + fN(a.notaBase), '#D97706'));
    if (a.temPrecos) k.appendChild(kpi('Fora da faixa MIN/MAX', fN(esc0.filter(function (x) { return x.priceFlag === 'fora'; }).length), 'Base de preços', ST.ver.dot));
    k.appendChild(kpi('Valor líquido das diferenças', fBRL(liq), 'PRJ − SAP', liq < 0 ? ST.div.dot : '#16A34A'));

    var chip = function (chave, rotulo, n, campo) {
      var b = el('button', 'chip'); b.type = 'button'; b.setAttribute('aria-pressed', String(s[campo] === chave));
      b.innerHTML = esc(rotulo) + (n === '' ? '' : '<span>' + fN(n) + '</span>');
      b.addEventListener('click', function () { s[campo] = chave; s.pagina = 0; desenharResultados(); });
      return b;
    };
    var ch = $('chips'); ch.innerHTML = '';
    [['todos', 'Todos', esc0.length], ['div', 'Não aderente', cnt('div')], ['sem', 'Sem correspondência', cnt('sem')],
      ['ver', 'Verificar', cnt('ver')], ['ok', 'Aderente', cnt('ok')]].forEach(function (c) { ch.appendChild(chip(c[0], c[1], c[2], 'sf')); });
    var cp = $('chips-preco'); cp.innerHTML = ''; cp.hidden = !a.temPrecos;
    if (a.temPrecos) [['todas', 'Todos os preços'], ['fora', 'Fora da faixa'], ['dentro', 'Dentro da faixa']].forEach(function (c) { cp.appendChild(chip(c[0], c[1], '', 'pf')); });

    $('exp-filtrados').textContent = 'Exportar filtrados (' + fN(rows.length) + ') · CSV';
    $('exp-todos').textContent = 'Exportar todos (' + fN(esc0.length) + ')';
    var dl = $('res-downloads'); dl.innerHTML = '';
    Object.keys(a.downloads || {}).forEach(function (rot) {
      var l = el('a', 'btn-secundario', 'Relatório completo · ' + esc(rot)); l.href = a.downloads[rot]; dl.appendChild(l);
    });

    desenharTabela(a, rows);
    // o relatorio completo so e redesenhado quando muda a base (pode ser grande)
    if (s.relDesenhado !== a) { $('relatorio').innerHTML = a.relatorioHtml || ''; s.relDesenhado = a; }
  }

  function desenharTabela(a, rows) {
    var mostrarBase = a.variasBases, mm = a.temPrecos;
    var cols = ['28px', '112px', 'minmax(180px,1.6fr)'].concat(mostrarBase ? ['minmax(130px,1fr)'] : [])
      .concat(['minmax(120px,1fr)', '84px', '84px', '88px', '116px']).concat(mm ? ['104px'] : []).concat(['172px']).join(' ');
    var t = $('tabela'); t.innerHTML = '';
    t.style.minWidth = (1004 + (mostrarBase ? 130 : 0) + (mm ? 104 : 0)) + 'px';

    var seta = function (k) { return s.ordem === k ? (s.dir === 'asc' ? ' ↑' : ' ↓') : ''; };
    var cab = el('div', 'lin lin-cab'); cab.setAttribute('role', 'row'); cab.style.gridTemplateColumns = cols;
    var hcol = function (rot, k, dir) {
      if (!k) { var d = el('div', dir ? 'dir' : '', esc(rot)); d.setAttribute('role', 'columnheader'); return d; }
      var b = el('button', dir ? 'dir' : '', esc(rot) + seta(k)); b.type = 'button'; b.setAttribute('role', 'columnheader');
      b.addEventListener('click', function () { s.dir = s.ordem === k && s.dir === 'asc' ? 'desc' : 'asc'; s.ordem = k; s.pagina = 0; desenharResultados(); });
      return b;
    };
    cab.appendChild(hcol(''));
    cab.appendChild(hcol('Material', 'code'));
    cab.appendChild(hcol('Descrição', 'desc'));
    if (mostrarBase) cab.appendChild(hcol('Base', 'base'));
    cab.appendChild(hcol('PEP4', 'pep4'));
    cab.appendChild(hcol('Qtd SAP', 'qSap', true));
    cab.appendChild(hcol('Qtd PRJ', 'qPrj', true));
    cab.appendChild(hcol('Diferença', 'diff', true));
    cab.appendChild(hcol('Valor dif.', 'valor', true));
    if (mm) cab.appendChild(hcol('MIN/MAX'));
    cab.appendChild(hcol('Status', 'status'));
    t.appendChild(cab);

    var paginas = Math.max(1, Math.ceil(rows.length / PAGINA));
    var p = Math.min(s.pagina, paginas - 1); s.pagina = p;

    if (!rows.length) {
      var v = el('div', 'vazio-tabela', '<div class="vazio-titulo">Nenhum registro encontrado</div><div class="vazio-sub">Ajuste a pesquisa ou os filtros aplicados.</div>');
      var lb = el('button', 'btn-secundario', 'Limpar filtros'); lb.type = 'button';
      lb.addEventListener('click', function () { s.q = ''; $('busca').value = ''; s.sf = 'todos'; s.pf = 'todas'; s.pagina = 0; desenharResultados(); });
      v.appendChild(lb); t.appendChild(v);
    }

    rows.slice(p * PAGINA, p * PAGINA + PAGINA).forEach(function (r) {
      var aberto = s.aberto === r.id, st = ST[r.status];
      var corDif = r.status === 'div' ? 't-div' : r.status === 'ver' ? 't-ver' : r.diff === 0 ? 't-neutro' : '';
      var g = el('div', 'grupo' + (aberto ? ' aberto' : '')); g.setAttribute('role', 'rowgroup');
      var l = el('div', 'lin lin-dado'); l.setAttribute('role', 'row'); l.tabIndex = 0; l.setAttribute('aria-expanded', String(aberto));
      l.style.gridTemplateColumns = cols;
      var precoTxt = r.priceFlag === 'fora' ? '<span class="preco-fora">! Fora da faixa</span>' : r.priceFlag === 'dentro' ? '<span class="preco-dentro">Dentro</span>' : '<span class="preco-dentro">—</span>';
      l.innerHTML = '<div role="cell" class="c-seta">' + (aberto ? '▾' : '▸') + '</div>' +
        '<div role="cell" class="c c-cod">' + esc(r.code) + '</div>' +
        '<div role="cell" class="c c-desc" title="' + esc(r.desc) + '">' + esc(r.desc) + '</div>' +
        (mostrarBase ? '<div role="cell" class="c c-cinza" title="' + esc(r.base) + '">' + esc(r.base) + '</div>' : '') +
        '<div role="cell" class="c c-mono" title="' + esc(r.pep4) + '">' + esc(r.pep4) + '</div>' +
        '<div role="cell" class="c num">' + fN(r.qSap) + '</div>' +
        '<div role="cell" class="c num">' + fN(r.qPrj) + '</div>' +
        '<div role="cell" class="c num forte ' + corDif + '">' + (r.diff > 0 ? '+' : '') + fN(r.diff) + '</div>' +
        '<div role="cell" class="c num ' + corDif + '">' + fBRL(r.valor) + '</div>' +
        (mm ? '<div role="cell" class="c">' + precoTxt + '</div>' : '') +
        '<div role="cell" class="c"><span class="pill ' + r.status + '"><b aria-hidden="true">' + st.icon + '</b>' + st.label + '</span></div>';
      var alternar = function () { s.aberto = aberto ? null : r.id; desenharResultados(); };
      l.addEventListener('click', alternar);
      l.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); alternar(); } });
      g.appendChild(l);
      if (aberto) {
        // o detalhe acompanha a area visivel, mesmo com a tabela rolada para o lado
        var det = detalhe(r, mm, st);
        det.style.maxWidth = Math.max(280, t.parentElement.clientWidth - 8) + 'px';
        g.appendChild(det);
      }
      t.appendChild(g);
    });

    $('faixa').textContent = rows.length ? 'Exibindo ' + fN(p * PAGINA + 1) + '–' + fN(Math.min(rows.length, (p + 1) * PAGINA)) + ' de ' + fN(rows.length) + ' registros' : '0 registros';
    $('pagina').textContent = 'Página ' + (p + 1) + ' de ' + paginas;
    $('anterior').disabled = p === 0; $('proxima').disabled = p >= paginas - 1;
  }

  function detalhe(r, mm, st) {
    var un = r.un ? ' ' + esc(r.un) : '';
    var d = el('div', 'detalhe');
    d.innerHTML =
      '<div class="det-cartao"><div class="det-titulo sap">SAP</div><div class="det-grade">' +
        '<span>Quantidade</span><span>' + fN(r.qSap) + un + '</span>' +
        '<span>PEP4</span><span class="c-mono">' + esc(r.pep4) + '</span>' +
        '<span>Preço unitário</span><span>' + fBRL(r.price) + '</span>' +
        '<span>Valor</span><span>' + fBRL(r.valorSap) + '</span></div></div>' +
      '<div class="det-cartao"><div class="det-titulo prj">PRJ</div><div class="det-grade">' +
        '<span>Quantidade</span><span>' + fN(r.qPrj) + un + '</span>' +
        '<span>Obra (PEP3)</span><span class="c-mono">' + esc(r.pep3) + '</span>' +
        '<span>Tipo</span><span>' + esc(r.tipo) + '</span>' +
        '<span>Família</span><span>' + esc(r.familia) + '</span>' +
        '<span>Valor estimado</span><span>' + fBRL(r.qPrj == null || r.price == null ? null : r.qPrj * r.price) + '</span></div></div>' +
      '<div class="det-cartao"><div class="det-titulo cmp">COMPARAÇÃO</div><div class="det-grade">' +
        '<span>Diferença (PRJ − SAP)</span><span class="forte">' + (r.diff > 0 ? '+' : '') + fN(r.diff) + '</span>' +
        '<span>Valor da diferença</span><span>' + fBRL(r.valor) + '</span>' +
        '<span>Faixa MIN/MAX</span><span>' + (mm ? (r.mn == null ? 'Sem faixa' : fBRL(r.mn) + ' – ' + fBRL(r.mx)) : 'Base não incluída') + '</span>' +
        '<span>SIT MAT</span><span>' + esc(r.sit) + '</span>' +
        '<span>Status</span><span class="pill-txt">' + st.label + '</span></div></div>' +
      '<div class="det-cartao largo"><div class="det-titulo cmp">VEREDITO DA OBRA · ' + esc(r.aprovacao) + '</div>' +
        '<div class="det-texto">' + esc(r.motivo) + '</div>' +
        (r.devolucao ? '<div class="det-texto"><strong>' + esc(r.devolucao) + '</strong></div>' : '') + '</div>';
    return d;
  }

  $('busca').addEventListener('input', function (e) { s.q = e.target.value; s.pagina = 0; desenharResultados(); });
  $('anterior').addEventListener('click', function () { s.pagina = Math.max(0, s.pagina - 1); desenharResultados(); });
  $('proxima').addEventListener('click', function () { s.pagina += 1; desenharResultados(); });
  $('fechar-banner').addEventListener('click', function () { s.banner = false; desenharResultados(); });
  $('exp-filtrados').addEventListener('click', function () { exportar(filtradas(), 'filtrados'); });
  $('exp-todos').addEventListener('click', function () { exportar(linhasEscopo(), 'todos'); });

  // guias do relatorio completo
  $('relatorio').addEventListener('click', function (ev) {
    var b = ev.target.closest('.guia'); if (!b) return;
    var rel = $('relatorio');
    rel.querySelectorAll('.guia, .painel').forEach(function (e) { e.classList.remove('ativo'); });
    b.classList.add('ativo');
    var alvo = rel.querySelector('#' + b.dataset.alvo); if (alvo) alvo.classList.add('ativo');
  });

  function exportar(rows, sufixo) {
    var a = analise(); var mm = a.temPrecos;
    var c = function (v) { v = v == null ? '' : String(v); return /[;"\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    var n = function (v) { return v == null ? '' : String(v).replace('.', ','); };
    // texto comecando com = + - @ vira formula no Excel: prefixa apostrofo
    var t = function (v) { v = v == null ? '' : String(v); return c(/^[=+\-@]/.test(v) ? "'" + v : v); };
    var cab = ['Material', 'Descrição', 'Base', 'PEP3', 'PEP4', 'Tipo', 'Família', 'Unidade', 'Qtd SAP', 'Qtd PRJ', 'Diferença', 'Preço unitário', 'Valor diferença']
      .concat(mm ? ['Preço MIN', 'Preço MAX', 'Faixa MIN/MAX'] : []).concat(['SIT MAT', 'Status', 'Aprovação', 'Motivo']);
    var linhas = [cab.map(c).join(';')].concat(rows.map(function (r) {
      return ['="' + r.code.replace(/"/g, '') + '"', t(r.desc), t(r.base), t(r.pep3), t(r.pep4), t(r.tipo), t(r.familia), t(r.un), n(r.qSap), n(r.qPrj), n(r.diff),
        n(r.price == null ? null : Math.round(r.price * 100) / 100), n(r.valor)]
        .concat(mm ? [n(r.mn), n(r.mx), r.priceFlag === 'fora' ? 'Fora da faixa' : r.priceFlag === 'dentro' ? 'Dentro da faixa' : ''] : [])
        .concat([t(r.sit), ST[r.status].label, r.aprovacao, t(r.motivo)]).join(';');
    }));
    var blob = new Blob(['﻿' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob); var e = document.createElement('a');
    e.href = url; e.download = 'analise-inventario-' + sufixo + '.csv'; document.body.appendChild(e); e.click(); e.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  ir('nova'); desenharNova();
})();
