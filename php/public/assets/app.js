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
    res: null, aba: 0, q: '', sf: 'todos', pf: 'todas', ordem: 'status', dir: 'asc', pagina: 0, aberto: null,
    obra: '', secao: 'itens', qa: '', ta: 'todos', pa: 0, abertoA: null, qr: '', rr: 'todos', pr: 0
  };
  // cor do chip por tipo de alerta (mesma paleta do relatorio XLSX)
  var COR_ALERTA = {
    'ODI SEM UC': '#D69E00', 'PEP SEM UC': '#C45911', 'ODI SEM COM': '#2E75B6', 'PU ABAIXO MIN': '#B07C00',
    'PU ACIMA MAX': '#C00000', 'MATERIAL NEGATIVO': '#C00000', 'MATERIAL POSITIVO EM ODD': '#D66300',
    'LACRE x MEDIDOR': '#6040C4', 'POSTE EM PEP .M': '#1F6BB7', 'POSTE EM PEP .S': '#0F8273',
    'UC SUBVALORIZADO': '#C00000', 'UC - PRECO NAO ENCONTRADO': '#B07C00', 'UC - COD MATERIAL VAZIO': '#6E6E6E',
    'PU BAIXO EM ODI': '#BE185D', 'PU 0,01 EM CABO/COND': '#C2410C', 'ODD VALOR ABAIXO DO LIMITE': '#991B1B',
    'POSTE ODI PU BAIXO': '#3730A3', 'MEDIDOR EM PEP ODI': '#6D28D9', 'QUALIDADE DA BASE - CRITICO': '#C00000',
    'QUALIDADE DA BASE': '#B07C00'
  };
  var RISCO = { ALTO: { cls: 'alto', cor: '#DC2626' }, MEDIO: { cls: 'medio', cor: '#D97706' }, BAIXO: { cls: 'baixo', cor: '#2563EB' }, OK: { cls: 'okr', cor: '#16A34A' } };
  var colar = new Intl.Collator('pt-BR', { sensitivity: 'base', numeric: true });
  var cacheFiltro = { chave: null, rows: [] };
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

    // XHR em vez de fetch: so ele informa o progresso do envio dos arquivos
    var xhr = new XMLHttpRequest();
    var inicio = Date.now(), relogio = null;
    var barra = $('progresso'), preench = $('progresso-barra');
    var fase = function (txt, pct) {
      $('executar').innerHTML = '<span class="giro" aria-hidden="true"></span>' + esc(txt);
      barra.hidden = false;
      barra.classList.toggle('indeterminado', pct == null);
      preench.style.width = pct == null ? '' : pct + '%';
    };
    xhr.open('POST', '?api=analisar');
    xhr.setRequestHeader('X-CSRF', csrf);
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) fase('Enviando os arquivos... ' + Math.round(e.loaded / e.total * 100) + '%', Math.round(e.loaded / e.total * 100));
    };
    xhr.upload.onload = function () {
      fase('Analisando as bases...', null);
      relogio = setInterval(function () {
        fase('Analisando as bases... ' + Math.round((Date.now() - inicio) / 1000) + ' s', null);
      }, 1000);
    };
    var fim = function () { clearInterval(relogio); barra.hidden = true; };
    xhr.onload = function () {
      fim();
      var j = null;
      try { j = JSON.parse(xhr.responseText); } catch (e) { /* resposta nao-JSON: erro do servidor */ }
      if (!j || j.erro) {
        s.ocupado = false;
        s.erro = j && j.erro ? j.erro : xhr.status === 413 ? 'Os arquivos passam do limite de envio do servidor.' :
          'O servidor não respondeu como esperado (HTTP ' + xhr.status + '). Se a base for muito grande, tente de novo em instantes.';
        desenharNova();
        return;
      }
      s.ocupado = false;
      s.res = j; s.aba = 0; s.banner = true; s.duracao = Math.round((Date.now() - inicio) / 100) / 10;
      limparFiltros();
      desenharNova(); prepararResultados(); ir('resultados');
    };
    xhr.onerror = function () {
      fim();
      s.ocupado = false;
      s.erro = 'Falha de conexão com o servidor. Verifique a rede e tente novamente.';
      desenharNova();
    };
    xhr.send(fd);
    fase('Enviando os arquivos... 0%', 0);
  }

  function limparFiltros() {
    s.q = ''; s.sf = 'todos'; s.pf = 'todas'; s.obra = ''; s.pagina = 0; s.aberto = null; s.secao = 'itens';
    s.qa = ''; s.ta = 'todos'; s.pa = 0; s.abertoA = null; s.qr = ''; s.rr = 'todos'; s.pr = 0;
    ['busca', 'busca-alertas', 'busca-ranking'].forEach(function (id) { $(id).value = ''; });
    cacheFiltro.chave = null;
  }

  // ---------- resultados ----------
  function analise() { return s.res ? s.res.analises[s.aba] : null; }
  function linhasEscopo() { var a = analise(); return a ? a.linhas : []; }
  // filtrar + ordenar 30 mil linhas a cada clique pesava: o resultado fica em cache
  // ate mudar a base, a pesquisa, um filtro ou a ordenacao
  function filtradas() {
    var chave = [s.aba, s.q, s.sf, s.pf, s.obra, s.ordem, s.dir].join('\u0001');
    if (cacheFiltro.chave === chave) return cacheFiltro.rows;
    var qq = s.q.trim().toLowerCase();
    var rows = linhasEscopo().filter(function (r) {
      if (r._q === undefined) r._q = (r.code + ' ' + r.desc + ' ' + r.base + ' ' + r.pep4 + ' ' + r.pep3).toLowerCase();
      return (s.sf === 'todos' || r.status === s.sf) && (s.pf === 'todas' || r.priceFlag === s.pf) &&
        (!s.obra || r.pep3 === s.obra) && (!qq || r._q.indexOf(qq) >= 0);
    });
    var val = function (r) {
      if (s.ordem === 'status') return ST[r.status].ord;
      if (s.ordem === 'diff' || s.ordem === 'valor') return r[s.ordem] == null ? null : Math.abs(r[s.ordem]);
      return r[s.ordem];
    };
    var d = s.dir === 'asc' ? 1 : -1;
    rows.sort(function (a, b) {
      var x = val(a), y = val(b);
      if (x == null && y == null) return 0;
      if (x == null) return 1;
      if (y == null) return -1;
      var c = typeof x === 'string' ? colar.compare(x, y) : x - y;
      return c * d || Math.abs(b.valor || 0) - Math.abs(a.valor || 0);
    });
    cacheFiltro = { chave: chave, rows: rows };
    return rows;
  }

  // contagem por status da base inteira ou de uma obra; guardada por analise
  function contagem(a, obra) {
    a._cnt = a._cnt || {};
    var k = obra || '\u0000';
    if (a._cnt[k]) return a._cnt[k];
    var c = { ok: 0, ver: 0, div: 0, sem: 0, fora: 0, liq: 0, total: 0 };
    a.linhas.forEach(function (x) {
      if (obra && x.pep3 !== obra) return;
      c.total++; c[x.status]++; if (x.priceFlag === 'fora') c.fora++; c.liq += x.valor || 0;
    });
    return (a._cnt[k] = c);
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
      b.addEventListener('click', function () { s.aba = i; s.pagina = 0; s.aberto = null; s.pa = 0; s.pr = 0; desenharResultados(); });
      tabs.appendChild(b);
    });
    desenharResultados();
  }

  function kpi(rotulo, valor, sub, cor, chave) {
    var b = el(chave ? 'button' : 'div', 'kpi');
    if (typeof chave === 'function') {
      b.type = 'button'; b.classList.add('atalho'); b.addEventListener('click', chave);
    } else if (chave) {
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
      : fN(a.linhas.length) + ' registros processados' + (s.duracao ? ' em ' + String(s.duracao).replace('.', ',') + ' s' : '') + '.';
    var avisos = (r.avisos || []).concat(a.avisos || []);
    $('aviso').hidden = !avisos.length;
    $('aviso').innerHTML = avisos.map(function (t) { return '<div>' + esc(t) + '</div>'; }).join('');

    var esc0 = linhasEscopo(), rows = filtradas();
    // com uma obra selecionada, os contadores de status passam a ser os da obra
    var co = contagem(a, s.obra), base = s.obra ? co.total : esc0.length;
    var cnt = function (k) { return co[k]; };
    var pct = function (n) { return base ? (n / base * 100).toFixed(1).replace('.', ',') + (s.obra ? '% da obra' : '% do total') : ''; };
    var liq = co.liq;

    var k = $('kpis'); k.innerHTML = '';
    k.appendChild(s.obra
      ? kpi('Registros da obra', fN(co.total), 'Obra ' + s.obra + ' · de ' + fN(esc0.length) + ' registros', '#17365D')
      : kpi('Registros analisados', fN(esc0.length), r.analises.length > 1 ? 'Nesta base' : r.arquivos.length + (r.arquivos.length > 1 ? ' bases' : ' base'), '#17365D'));
    k.appendChild(kpi('Não aderentes', fN(cnt('div')), pct(cnt('div')), ST.div.dot, 'div'));
    k.appendChild(kpi('Sem correspondência', fN(cnt('sem')), pct(cnt('sem')), ST.sem.dot, 'sem'));
    k.appendChild(kpi('Verificar', fN(cnt('ver')), pct(cnt('ver')), ST.ver.dot, 'ver'));
    k.appendChild(kpi('Aderentes', fN(cnt('ok')), pct(cnt('ok')), ST.ok.dot, 'ok'));
    k.appendChild(kpi('Obras reprovadas', fN(a.obras.reprovadas), 'de ' + fN(a.obras.total) + ' obras · ver ranking', '#B91C1C', function () { irSecao('ranking'); }));
    k.appendChild(kpi('Alertas críticos', fN(a.alertas), a.notaBase == null ? 'Ver alertas' : 'Saúde da base: ' + fN(a.notaBase) + ' · ver alertas', '#D97706', function () { irSecao('alertas'); }));
    if (a.temPrecos) k.appendChild(kpi('Fora da faixa MIN/MAX', fN(co.fora), 'Base de preços', ST.ver.dot));
    k.appendChild(kpi('Valor líquido das diferenças', fBRL(liq), 'PRJ − SAP', liq < 0 ? ST.div.dot : '#16A34A'));

    var chip = function (chave, rotulo, n, campo) {
      var b = el('button', 'chip'); b.type = 'button'; b.setAttribute('aria-pressed', String(s[campo] === chave));
      b.innerHTML = esc(rotulo) + (n === '' ? '' : '<span>' + fN(n) + '</span>');
      b.addEventListener('click', function () { s[campo] = chave; s.pagina = 0; desenharResultados(); });
      return b;
    };
    var ch = $('chips'); ch.innerHTML = '';
    if (s.obra) {
      var cob = el('button', 'chip'); cob.type = 'button'; cob.setAttribute('aria-pressed', 'true');
      cob.setAttribute('aria-label', 'Remover filtro da obra ' + s.obra);
      cob.innerHTML = 'Obra ' + esc(s.obra) + ' <span aria-hidden="true">×</span>';
      cob.addEventListener('click', function () { s.obra = ''; s.pagina = 0; desenharResultados(); });
      ch.appendChild(cob);
    }
    [['todos', 'Todos', base], ['div', 'Não aderente', cnt('div')], ['sem', 'Sem correspondência', cnt('sem')],
      ['ver', 'Verificar', cnt('ver')], ['ok', 'Aderente', cnt('ok')]].forEach(function (c) { ch.appendChild(chip(c[0], c[1], c[2], 'sf')); });
    var cp = $('chips-preco'); cp.innerHTML = ''; cp.hidden = !a.temPrecos;
    if (a.temPrecos) [['todas', 'Todos os preços'], ['fora', 'Fora da faixa'], ['dentro', 'Dentro da faixa']].forEach(function (c) { cp.appendChild(chip(c[0], c[1], '', 'pf')); });

    var nExp = s.secao === 'alertas' ? [alertasFiltrados(a).length, a.alertasLista.length]
      : s.secao === 'ranking' ? [rankingFiltrado(a).length, a.ranking.length] : [rows.length, esc0.length];
    var rot = s.secao === 'alertas' ? ['Exportar alertas filtrados', 'Exportar todos os alertas']
      : s.secao === 'ranking' ? ['Exportar obras filtradas', 'Exportar todas as obras'] : ['Exportar filtrados', 'Exportar todos'];
    $('exp-filtrados').textContent = rot[0] + ' (' + fN(nExp[0]) + ') · CSV';
    $('exp-todos').textContent = rot[1] + ' (' + fN(nExp[1]) + ')';
    var dl = $('res-downloads'); dl.innerHTML = '';
    Object.keys(a.downloads || {}).forEach(function (rot) {
      var l = el('a', 'btn-secundario', 'Relatório completo · ' + esc(rot)); l.href = a.downloads[rot]; dl.appendChild(l);
    });

    $('n-itens').textContent = fN(esc0.length);
    $('n-alertas').textContent = fN(a.alertasLista.length);
    $('n-ranking').textContent = fN(a.ranking.length);
    document.querySelectorAll('.secao').forEach(function (b) { b.setAttribute('aria-selected', String(b.dataset.secao === s.secao)); });
    ['itens', 'alertas', 'ranking'].forEach(function (x) { $('sec-' + x).hidden = x !== s.secao; });
    if (s.secao === 'itens') desenharTabela(a, rows);
    else if (s.secao === 'alertas') desenharAlertas(a);
    else desenharRanking(a);
    // o relatorio completo so e redesenhado quando muda a base (pode ser grande)
    if (s.relDesenhado !== a) { $('relatorio').innerHTML = a.relatorioHtml || ''; s.relDesenhado = a; }
  }

  function desenharTabela(a, rows) {
    var mostrarBase = a.variasBases, mm = a.temPrecos;
    var cols = ['28px', '104px', 'minmax(160px,1.6fr)'].concat(mostrarBase ? ['minmax(130px,1fr)'] : [])
      .concat(['minmax(110px,1fr)', '84px', '84px', '88px', '116px']).concat(mm ? ['104px'] : []).concat(['172px']).join(' ');
    var t = $('tabela'); t.innerHTML = '';
    t.style.minWidth = (946 + (mostrarBase ? 130 : 0) + (mm ? 104 : 0)) + 'px';

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
      lb.addEventListener('click', function () { s.q = ''; $('busca').value = ''; s.sf = 'todos'; s.pf = 'todas'; s.obra = ''; s.pagina = 0; desenharResultados(); });
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

  function irSecao(x) {
    s.secao = x; desenharResultados();
    $('secoes').scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  function paginar(total, pag, ids) {
    var paginas = Math.max(1, Math.ceil(total / PAGINA)), p = Math.min(pag, paginas - 1);
    $(ids[0]).textContent = total ? 'Exibindo ' + fN(p * PAGINA + 1) + '–' + fN(Math.min(total, (p + 1) * PAGINA)) + ' de ' + fN(total) : '0 registros';
    $(ids[1]).textContent = 'Página ' + (p + 1) + ' de ' + paginas;
    $(ids[2]).disabled = p === 0; $(ids[3]).disabled = p >= paginas - 1;
    return p;
  }

  function vazio(t, texto) {
    t.appendChild(el('div', 'vazio-tabela', '<div class="vazio-titulo">' + esc(texto) + '</div><div class="vazio-sub">Ajuste a pesquisa ou os filtros aplicados.</div>'));
  }

  function alertasFiltrados(a) {
    var qq = s.qa.trim().toLowerCase();
    return a.alertasLista.filter(function (x) {
      if (s.ta !== 'todos' && x.tipo !== s.ta) return false;
      if (!qq) return true;
      if (x._q === undefined) x._q = (x.tipo + ' ' + x.pep3 + ' ' + x.pep4 + ' ' + x.code + ' ' + x.desc + ' ' + x.motivo).toLowerCase();
      return x._q.indexOf(qq) >= 0;
    });
  }

  function rankingFiltrado(a) {
    var qq = s.qr.trim().toLowerCase();
    return a.ranking.filter(function (o) {
      return (s.rr === 'todos' || o.risco === s.rr) && (!qq || (o.pep3 + ' ' + o.diag).toLowerCase().indexOf(qq) >= 0);
    });
  }

  function desenharAlertas(a) {
    var lista = a.alertasLista;
    var porTipo = {};
    lista.forEach(function (x) { porTipo[x.tipo] = (porTipo[x.tipo] || 0) + 1; });
    var tipos = Object.keys(porTipo).sort(function (x, y) { return porTipo[y] - porTipo[x]; });
    var ch = $('chips-alertas'); ch.innerHTML = '';
    [['todos', 'Todos', lista.length]].concat(tipos.map(function (t) { return [t, t, porTipo[t]]; })).forEach(function (c) {
      var b = el('button', 'chip'); b.type = 'button'; b.setAttribute('aria-pressed', String(s.ta === c[0]));
      b.innerHTML = (c[0] !== 'todos' ? '<span class="ponto" style="opacity:1;background:' + (COR_ALERTA[c[0]] || '#787878') + '"></span>' : '') + esc(c[1]) + '<span>' + fN(c[2]) + '</span>';
      b.addEventListener('click', function () { s.ta = c[0]; s.pa = 0; s.abertoA = null; desenharResultados(); });
      ch.appendChild(b);
    });
    var rows = alertasFiltrados(a);
    var cols = '236px 104px 128px 96px minmax(140px,1fr) 100px 56px 96px minmax(240px,2fr)';
    var t = $('tabela-alertas'); t.innerHTML = ''; t.style.minWidth = '1210px';
    var cab = el('div', 'lin lin-cab'); cab.style.gridTemplateColumns = cols; cab.setAttribute('role', 'row');
    cab.innerHTML = ['Tipo', 'Obra (PEP3)', 'PEP4', 'Material', 'Descrição', 'Valor', 'Qtd', 'Preço unit.', 'Motivo'].map(function (h, i) {
      return '<div role="columnheader"' + (i >= 5 && i <= 7 ? ' class="dir"' : '') + '>' + h + '</div>';
    }).join('');
    t.appendChild(cab);
    if (!rows.length) vazio(t, 'Nenhum alerta encontrado');
    var p = s.pa = paginar(rows.length, s.pa, ['faixa-alertas', 'pagina-alertas', 'anterior-alertas', 'proxima-alertas']);
    rows.slice(p * PAGINA, p * PAGINA + PAGINA).forEach(function (x) {
      var l = el('div', 'lin grupo'); l.setAttribute('role', 'row'); l.style.gridTemplateColumns = cols;
      l.innerHTML = '<div role="cell" class="c"><span class="tipo-alerta" title="' + esc(x.tipo) + '" style="background:' + (COR_ALERTA[x.tipo] || '#787878') + '">' + esc(x.tipo) + '</span></div>' +
        '<div role="cell" class="c c-mono" title="' + esc(x.pep3) + '">' + esc(x.pep3 || '—') + '</div>' +
        '<div role="cell" class="c c-mono" title="' + esc(x.pep4) + '">' + esc(x.pep4 || '—') + '</div>' +
        '<div role="cell" class="c c-cod">' + esc(x.code || '—') + '</div>' +
        '<div role="cell" class="c" title="' + esc(x.desc) + '">' + esc(x.desc || '—') + '</div>' +
        '<div role="cell" class="c num">' + fBRL(x.valor) + '</div>' +
        '<div role="cell" class="c num">' + fN(x.qtd) + '</div>' +
        '<div role="cell" class="c num">' + fBRL(x.pu) + '</div>' +
        '<div role="cell" class="c c-motivo">' + esc(x.motivo) + '</div>';
      t.appendChild(l);
    });
  }

  function desenharRanking(a) {
    var lista = a.ranking;
    var porRisco = { ALTO: 0, MEDIO: 0, BAIXO: 0, OK: 0 };
    lista.forEach(function (o) { porRisco[o.risco] = (porRisco[o.risco] || 0) + 1; });
    var ch = $('chips-ranking'); ch.innerHTML = '';
    [['todos', 'Todos', lista.length], ['ALTO', 'Risco alto', porRisco.ALTO], ['MEDIO', 'Médio', porRisco.MEDIO], ['BAIXO', 'Baixo', porRisco.BAIXO], ['OK', 'Sem apontamento', porRisco.OK]].forEach(function (c) {
      var b = el('button', 'chip'); b.type = 'button'; b.setAttribute('aria-pressed', String(s.rr === c[0]));
      b.innerHTML = esc(c[1]) + '<span>' + fN(c[2]) + '</span>';
      b.addEventListener('click', function () { s.rr = c[0]; s.pr = 0; desenharResultados(); });
      ch.appendChild(b);
    });
    var rows = rankingFiltrado(a);
    // divergencias de preco, sobrepreco e COM fora ja aparecem escritos no diagnostico
    var cols = '48px minmax(130px,1fr) 124px 128px 72px 160px 84px minmax(280px,2.2fr)';
    var t = $('tabela-ranking'); t.innerHTML = ''; t.style.minWidth = '1040px';
    var cab = el('div', 'lin lin-cab'); cab.style.gridTemplateColumns = cols; cab.setAttribute('role', 'row');
    cab.innerHTML = ['#', 'Obra (PEP3)', 'Valor da obra', 'Situação', 'Alertas', 'Score de risco', 'Risco', 'Diagnóstico'].map(function (h, i) {
      return '<div role="columnheader"' + ((i === 2 || i === 4) ? ' class="dir"' : '') + '>' + h + '</div>';
    }).join('');
    t.appendChild(cab);
    if (!rows.length) vazio(t, 'Nenhuma obra encontrada');
    var p = s.pr = paginar(rows.length, s.pr, ['faixa-ranking', 'pagina-ranking', 'anterior-ranking', 'proxima-ranking']);
    rows.slice(p * PAGINA, p * PAGINA + PAGINA).forEach(function (o) {
      var rk = RISCO[o.risco] || RISCO.OK;
      var l = el('div', 'lin lin-obra grupo'); l.setAttribute('role', 'row'); l.tabIndex = 0; l.style.gridTemplateColumns = cols;
      l.title = 'Ver os itens da obra ' + o.pep3;
      l.innerHTML = '<div role="cell" class="c num">' + o.pos + '</div>' +
        '<div role="cell" class="c c-mono" style="color:var(--navy)">' + esc(o.pep3) + '</div>' +
        '<div role="cell" class="c num">' + fBRL(o.valor) + '</div>' +
        '<div role="cell" class="c"><span class="pill ' + (o.reprovado ? 'div' : 'ok') + '"><b aria-hidden="true">' + (o.reprovado ? '×' : '✓') + '</b>' + (o.reprovado ? 'Reprovada' : 'Aprovada') + '</span></div>' +
        '<div role="cell" class="c num">' + fN(o.alertas) + '</div>' +
        '<div role="cell" class="c"><div class="score"><div class="score-barra"><span style="width:' + Math.min(100, o.score) + '%;background:' + rk.cor + '"></span></div><span class="score-num">' + Math.round(o.score) + '</span></div></div>' +
        '<div role="cell" class="c"><span class="pill ' + rk.cls + '">' + esc(o.risco) + '</span></div>' +
        '<div role="cell" class="c c-diag">' + esc(o.diag) + '</div>';
      var abrir = function () {
        s.secao = 'itens'; s.obra = o.pep3; s.q = ''; s.sf = 'todos'; s.pf = 'todas'; s.pagina = 0; s.aberto = null;
        $('busca').value = ''; desenharResultados();
      };
      l.addEventListener('click', abrir);
      l.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(); } });
      t.appendChild(l);
    });
    var sb = $('sem-baixa'), semB = a.semBaixa || [];
    sb.hidden = !semB.length;
    sb.innerHTML = semB.length ? '<h3>PEPs sem material baixado no projeto (' + fN(semB.length) + ')</h3><ul>' + semB.slice(0, 50).map(function (x) {
      return '<li><span class="pill sem">' + esc(x.nivel) + '</span><span class="c-mono">' + esc(x.pep) + '</span><span>' + fBRL(x.valor) + '</span><span>' + esc(x.obs) + '</span></li>';
    }).join('') + '</ul>' + (semB.length > 50 ? '<p class="dica-linha">Lista completa no relatório XLSX (aba RANKING DE RISCO).</p>' : '') : '';

    // PU de cada linha ODI x media do mesmo codigo; entra no score da obra
    var dv = $('discrep-valor'), dis = a.discrepancias || [], tol = Math.round((a.discrepTol || 0.3) * 100);
    dv.hidden = false;
    dv.innerHTML = '<h3>Discrepância de valor de material · ODI (' + fN(dis.length) + ')</h3>' +
      '<p class="dica-linha">Preço unitário fora de ±' + tol + '% da média do mesmo código nas linhas ODI da base.</p>' +
      (dis.length ? '<ul>' + dis.slice(0, 50).map(function (x) {
        var acima = x.desvio > 0, pct = (acima ? '+' : '−') + (Math.abs(x.desvio) * 100).toFixed(1).replace('.', ',') + '%';
        return '<li><span class="pill ' + (acima ? 'div' : 'ver') + '">' + pct + '</span><span class="c-mono">' + esc(x.pep4) + '</span>' +
          '<span class="c-mono">' + esc(x.code) + '</span><span>' + esc(x.desc) + '</span>' +
          '<span>PU ' + fBRL(x.pu) + ' × média ' + fBRL(x.media) + ' (' + fN(x.n) + ' lançamentos)</span></li>';
      }).join('') + '</ul>' + (dis.length > 50 ? '<p class="dica-linha">Lista completa no relatório XLSX (aba RANKING DE RISCO).</p>' : '')
        : '<p class="dica-linha">Nenhum material de ODI com valor fora de ±' + tol + '% da média do código.</p>');
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

  // pesquisa com espera curta: nao refiltra a cada tecla em bases grandes
  function adiar(fn, ms) { var t; return function (e) { clearTimeout(t); t = setTimeout(function () { fn(e); }, ms); }; }
  $('busca').addEventListener('input', adiar(function (e) { s.q = e.target.value; s.pagina = 0; desenharResultados(); }, 180));
  $('busca-alertas').addEventListener('input', adiar(function (e) { s.qa = e.target.value; s.pa = 0; desenharResultados(); }, 180));
  $('busca-ranking').addEventListener('input', adiar(function (e) { s.qr = e.target.value; s.pr = 0; desenharResultados(); }, 180));
  document.querySelectorAll('.secao').forEach(function (b) { b.addEventListener('click', function () { s.secao = b.dataset.secao; desenharResultados(); }); });
  $('anterior-alertas').addEventListener('click', function () { s.pa = Math.max(0, s.pa - 1); desenharResultados(); });
  $('proxima-alertas').addEventListener('click', function () { s.pa += 1; desenharResultados(); });
  $('anterior-ranking').addEventListener('click', function () { s.pr = Math.max(0, s.pr - 1); desenharResultados(); });
  $('proxima-ranking').addEventListener('click', function () { s.pr += 1; desenharResultados(); });
  $('anterior').addEventListener('click', function () { s.pagina = Math.max(0, s.pagina - 1); desenharResultados(); });
  $('proxima').addEventListener('click', function () { s.pagina += 1; desenharResultados(); });
  $('fechar-banner').addEventListener('click', function () { s.banner = false; desenharResultados(); });
  $('exp-filtrados').addEventListener('click', function () { exportarSecao(true); });
  $('exp-todos').addEventListener('click', function () { exportarSecao(false); });

  // guias do relatorio completo
  $('relatorio').addEventListener('click', function (ev) {
    var b = ev.target.closest('.guia'); if (!b) return;
    var rel = $('relatorio');
    rel.querySelectorAll('.guia, .painel').forEach(function (e) { e.classList.remove('ativo'); });
    b.classList.add('ativo');
    var alvo = rel.querySelector('#' + b.dataset.alvo); if (alvo) alvo.classList.add('ativo');
  });

  // o CSV segue a secao aberta: itens, alertas ou ranking
  function exportarSecao(soFiltrados) {
    var a = analise(), suf = soFiltrados ? 'filtrados' : 'todos';
    if (s.secao === 'alertas') exportarAlertas(soFiltrados ? alertasFiltrados(a) : a.alertasLista, 'alertas-' + suf);
    else if (s.secao === 'ranking') exportarRanking(soFiltrados ? rankingFiltrado(a) : a.ranking, 'ranking-' + suf);
    else exportar(soFiltrados ? filtradas() : linhasEscopo(), suf);
  }

  var csvC = function (v) { v = v == null ? '' : String(v); return /[;"\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
  var csvN = function (v) { return v == null ? '' : String(v).replace('.', ','); };
  // texto comecando com = + - @ vira formula no Excel: prefixa apostrofo
  var csvT = function (v) { v = v == null ? '' : String(v); return csvC(/^[=+\-@]/.test(v) ? "'" + v : v); };
  var csvCod = function (v) { return v == null || v === '' ? '' : '="' + String(v).replace(/"/g, '') + '"'; };

  function baixarCsv(cab, linhas, sufixo) {
    var blob = new Blob(['\ufeff' + [cab.map(csvC).join(';')].concat(linhas.map(function (l) { return l.join(';'); })).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob); var e = document.createElement('a');
    e.href = url; e.download = 'analise-inventario-' + sufixo + '.csv'; document.body.appendChild(e); e.click(); e.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  function exportarAlertas(rows, sufixo) {
    baixarCsv(['Tipo', 'Obra (PEP3)', 'PEP4', 'Material', 'Descrição', 'Família', 'Valor', 'Qtd', 'Preço unitário', 'Referência', 'Motivo'],
      rows.map(function (x) {
        return [csvT(x.tipo), csvT(x.pep3), csvT(x.pep4), csvCod(x.code), csvT(x.desc), csvT(x.familia), csvN(x.valor), csvN(x.qtd),
          csvN(x.pu == null ? null : Math.round(x.pu * 100) / 100), typeof x.ref === 'number' ? csvN(x.ref) : csvT(x.ref), csvT(x.motivo)];
      }), sufixo);
  }

  function exportarRanking(rows, sufixo) {
    baixarCsv(['#', 'Obra (PEP3)', 'Valor da obra', 'Situação', 'Alertas', 'Diverg. preço', 'Sobrepreço R$', 'COM fora NT.006',
      'Discrepâncias de valor', 'Score', 'Risco', 'Diagnóstico'],
      rows.map(function (o) {
        return [o.pos, csvT(o.pep3), csvN(o.valor), o.reprovado ? 'REPROVADO' : 'APROVADO', o.alertas, o.divPreco, csvN(Math.round(o.sobrepreco * 100) / 100),
          o.comFora, o.discrep || 0, csvN(o.score), o.risco, csvT(o.diag)];
      }), sufixo);
  }

  function exportar(rows, sufixo) {
    var a = analise(); var mm = a.temPrecos;
    var c = csvC, n = csvN, t = csvT;
    var cab = ['Material', 'Descrição', 'Base', 'PEP3', 'PEP4', 'Tipo', 'Família', 'Unidade', 'Qtd SAP', 'Qtd PRJ', 'Diferença', 'Preço unitário', 'Valor diferença']
      .concat(mm ? ['Preço MIN', 'Preço MAX', 'Faixa MIN/MAX'] : []).concat(['SIT MAT', 'Status', 'Aprovação', 'Motivo']);
    baixarCsv(cab, rows.map(function (r) {
      return [csvCod(r.code), t(r.desc), t(r.base), t(r.pep3), t(r.pep4), t(r.tipo), t(r.familia), t(r.un), n(r.qSap), n(r.qPrj), n(r.diff),
        n(r.price == null ? null : Math.round(r.price * 100) / 100), n(r.valor)]
        .concat(mm ? [n(r.mn), n(r.mx), r.priceFlag === 'fora' ? 'Fora da faixa' : r.priceFlag === 'dentro' ? 'Dentro da faixa' : ''] : [])
        .concat([t(r.sit), ST[r.status].label, r.aprovacao, t(r.motivo)]);
    }), sufixo);
  }

  ir('nova'); desenharNova();
})();
