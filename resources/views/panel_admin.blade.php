@extends('layout')
@section('title','Panel Admin')

@section('content')
<style>
  /* ===== Hero con buscador ===== */
  .panel-hero{
    background: linear-gradient(90deg, var(--primary), var(--primary-hover));
    color:#fff; border-radius: var(--radius);
    padding: 22px; box-shadow: var(--shadow-sm); margin-bottom: 18px; position:relative;
  }
  .panel-hero h2{ margin:0 0 10px; font-size:1.18rem; font-weight:800; letter-spacing:.2px; }
  .hero-search{ display:grid; grid-template-columns: 1fr auto; gap:8px; background: rgba(255,255,255,.12); padding:10px; border-radius:12px; }
  .hero-search input{ height:44px; border-radius:10px; border:0; outline:none; padding:0 12px; font-size:1rem; color:#0f172a; }
  .hero-search button{ height:44px; border-radius:10px; border:0; cursor:pointer; padding:0 16px; background:#fff; color:var(--primary); font-weight:700; box-shadow: var(--shadow-xs); }

  /* Dropdown global (resultados) */
  .global-results{
    position:absolute; left:22px; right:22px; top:100%; margin-top:8px;
    background:#fff; color:var(--text); border:1px solid var(--border); border-radius:12px;
    box-shadow: var(--shadow-md); display:none; max-height:50vh; overflow:auto; z-index:50;
  }
  .gr-item{ display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer; border-radius:10px; margin:6px; }
  .gr-item:hover{ background:#F1F5F9; }
  .gr-item.active{ background:#EAF2FF; outline:1px solid #cfe3ff; }
  .gr-ico{ width:32px;height:32px;border-radius:10px; display:grid; place-items:center;
    background: color-mix(in srgb, var(--primary) 12%, #fff);
    color: color-mix(in srgb, var(--primary) 80%, #0F172A);
    border:1px solid color-mix(in srgb, var(--primary) 25%, #E6EAF1);
  }
  .gr-title{ font-weight:700; } .gr-desc{ color:#64748B; font-size:.92rem; }
  .hit{ background: #FEF08A; padding:0 2px; border-radius:3px; }

  /* ===== GRID: 4 columnas (desktop) -> 3/2/1 responsivo ===== */
  .tiles{
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* 4 por fila */
    gap: 18px;
  }
  @media (max-width: 1024px){ .tiles{ grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 780px){  .tiles{ grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 520px){  .tiles{ grid-template-columns: 1fr; } }

  /* Tarjetas más grandes y alineadas */
  .tile{
    display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;
    background:#fff; border:1px solid #E6EAF1; border-radius:16px;
    padding: 20px 16px; min-height: 180px; height: 100%;
    color:inherit; text-decoration:none; box-shadow: var(--shadow-xs);
    transition: transform .12s, box-shadow .12s, border-color .12s;
  }
  .tile:hover{ transform: translateY(-2px); box-shadow: var(--shadow-md); border-color:#dbe2ea; }

  .tile .icon{
    width:56px; height:56px; border-radius:12px; display:grid; place-items:center; margin-bottom:14px;
    background: color-mix(in srgb, var(--primary) 10%, #fff);
    color: color-mix(in srgb, var(--primary) 80%, #0F172A);
    border:1px solid color-mix(in srgb, var(--primary) 20%, #E6EAF1);
  }
  .tile .icon svg{ width:26px; height:26px; }
  .tile h3{ margin:2px 0 6px; font-size:1.12rem; color:#111827; letter-spacing:.2px; }
  .tile p{ margin:0; color:#64748B; font-size:.95rem; }
</style>

{{-- HERO (búsqueda) --}}
<div class="panel-hero">
  <h2>¿Qué necesitas hacer hoy?</h2>
  <div class="hero-search">
    <input id="panelSearch" type="search" placeholder="Buscar en todo MacroActiva: pagos, cobros, usuarios, reportes…">
    <button type="button" id="btnSearch">Buscar</button>
  </div>
  <div id="globalResults" class="global-results"></div>
</div>

{{-- GRID: 4 arriba, 4 abajo (8 ítems) --}}
<div class="tiles">
  {{-- 1 --}}
  <a class="tile" href="{{ route('pagos.panel') }}" data-tags="pagos registrar recibos ingresos">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M3.5 7.5h17v9h-17z" stroke="currentColor" stroke-width="1.6"/><path d="M7 7.5V5h10v2.5" stroke="currentColor" stroke-width="1.6"/><circle cx="8.8" cy="12" r="1.2" fill="currentColor"/></svg>
    </span>
    <h3>Pagos</h3><p>Ver y registrar pagos.</p>
  </a>

  {{-- 2 --}}
  <a class="tile" href="{{ route('admin.cobros.panel') }}" data-tags="cobros cargos intereses fr automático">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v10H4z" stroke="currentColor" stroke-width="1.6"/><path d="M8 11h8M8 14h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </span>
    <h3>Cobros</h3><p>Generar cargos, intereses y FR.</p>
  </a>

  {{-- 3 --}}
  <a class="tile" href="{{ route('admin.export.panel') }}" data-tags="exportar csv reportes periodos descargar">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M6 20h12V7l-4-4H6z" stroke="currentColor" stroke-width="1.6"/><path d="M9 13h6M9 16h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </span>
    <h3>Exportar CSV</h3><p>Cobros y pagos por período.</p>
  </a>

  {{-- 4 --}}
  <a class="tile" href="{{ route('cierres.panel') }}" data-tags="cierres mensuales pdf estados">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16M6 10h12M8 14h8M10 18h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </span>
    <h3>Cierres Mensuales</h3><p>Generar y descargar PDF.</p>
  </a>

  {{-- 5 --}}
  <a class="tile" href="{{ route('admin.gastos.panel') }}" data-tags="gastos egresos facturas">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M6 4h12v16H6z" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </span>
    <h3>Gastos</h3><p>Control de egresos.</p>
  </a>

  {{-- 6 --}}
  <a class="tile" href="{{ route('admin.proveedores.panel') }}" data-tags="proveedores compras contactos">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M3 20a5 5 0 0 1 10 0" stroke="currentColor" stroke-width="1.6"/><path d="M16 12h4v8h-4z" stroke="currentColor" stroke-width="1.6"/></svg>
    </span>
    <h3>Proveedores</h3><p>Directorio y gestión.</p>
  </a>

  {{-- 7 --}}
  <a class="tile" href="{{ route('admin.prorrateo.panel') }}" data-tags="prorrateo cuotas gastos comunes">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M4 19V5h16v14H4z" stroke="currentColor" stroke-width="1.6"/><path d="M7 15l4-4 3 3 3-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </span>
    <h3>Prorrateo</h3><p>Cuotas por unidad.</p>
  </a>

  {{-- 8 --}}
  <a class="tile" href="{{ route('admin.fr.panel') }}" data-tags="fondo de reserva fr ahorro">
    <span class="icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4v6c0 4-8 8-8 8s-8-4-8-8V7l8-4z" stroke="currentColor" stroke-width="1.6"/></svg>
    </span>
    <h3>Fondo de Reserva</h3><p>Movimientos y saldo.</p>
  </a>
</div>

<script>
(function(){
  const q        = document.getElementById('panelSearch');
  const btn      = document.getElementById('btnSearch');
  const tiles    = Array.from(document.querySelectorAll('.tile'));
  const resultsC = document.getElementById('globalResults');

  /* 1) Enlaces del layout (hamburguesa) */
  function collectMenuLinks(){
    const items = [];
    document.querySelectorAll('.hamb-menu a').forEach(a=>{
      items.push({
        title: a.textContent.trim(),
        url: a.getAttribute('href'),
        desc: a.closest('details, .hamb-section')?.querySelector('summary, .hamb-title')?.textContent?.trim() || 'Menú',
        tags: (a.textContent + ' ' + (a.closest('.hamb-section')?.textContent||'')).trim()
      });
    });
    return items;
  }

  /* 2) Rutas extra */
  const extra = [
    @auth
      @php $role = auth()->user()->rol ?? auth()->user()->tipo_usuario ?? null; @endphp
      @if (in_array($role, ['super_admin','admin']))
        { title:'Panel', url:'{{ route('panel') }}', desc:'Inicio', tags:'panel inicio home' },
        { title:'Pagos', url:'{{ route('pagos.panel') }}', desc:'Finanzas', tags:'pagos registrar' },
        { title:'Cobros', url:'{{ route('admin.cobros.panel') }}', desc:'Finanzas', tags:'cobros cargos intereses fr' },
        { title:'Gastos', url:'{{ route('admin.gastos.panel') }}', desc:'Finanzas', tags:'gastos egresos' },
        { title:'Proveedores', url:'{{ route('admin.proveedores.panel') }}', desc:'Finanzas', tags:'proveedores' },
        { title:'Prorrateo', url:'{{ route('admin.prorrateo.panel') }}', desc:'Finanzas', tags:'prorrateo cuotas' },
        { title:'Fondo de Reserva', url:'{{ route('admin.fr.panel') }}', desc:'Finanzas', tags:'fondo reserva fr' },
        { title:'Conciliación', url:'{{ route('admin.conciliacion.panel') }}', desc:'Finanzas', tags:'conciliacion' },
        { title:'Cierres Mensuales', url:'{{ route('cierres.panel') }}', desc:'Finanzas', tags:'cierres pdf' },
        { title:'Exportar', url:'{{ route('admin.export.panel') }}', desc:'Herramientas', tags:'exportar csv' },
        { title:'Auditoría', url:'{{ route('admin.audit.panel') }}', desc:'Herramientas', tags:'auditoria logs' },
        { title:'Usuarios', url:'{{ route('admin.usuarios.panel') }}', desc:'RR.HH.', tags:'usuarios' },
        { title:'Trabajadores', url:'{{ route('admin.trab.panel') }}', desc:'RR.HH.', tags:'trabajadores' },
        { title:'Remuneraciones', url:'{{ route('admin.remu.panel') }}', desc:'RR.HH.', tags:'remuneraciones' },
        { title:'Reportes', url:'{{ route('admin.reportes.panel') }}', desc:'Informes', tags:'reportes' },
        { title:'Libro Movimientos', url:'{{ route('admin.libro.panel') }}', desc:'Informes', tags:'libro movimientos' },
      @else
        { title:'Mi cuenta', url:'{{ route('mi.cuenta') }}', desc:'Portal', tags:'mi cuenta perfil' },
        { title:'Estado de cuenta', url:'{{ route('estado.cuenta') }}', desc:'Portal', tags:'estado de cuenta pagos' },
      @endif
    @endauth
  ];

  const globalIndex = [...collectMenuLinks(), ...extra];

  /* 3) Normalizar + buscar */
  const norm = s => (s||'').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,'');
  function searchAll(term){
    if(!term) return [];
    const words = norm(term).split(/\s+/).filter(Boolean);
    return globalIndex.filter(r=>{
      const hay = norm(r.title + ' ' + (r.desc||'') + ' ' + (r.tags||''));
      return words.every(w => hay.includes(w));
    }).slice(0,30);
  }

  /* 4) Resaltado */
  function highlight(text, term){
    if(!term) return text;
    const words = norm(term).split(/\s+/).filter(Boolean);
    if(!words.length) return text;
    let out = text;
    words.forEach(w=>{
      if(!w) return;
      const re = new RegExp(`(${w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`,'gi');
      out = out.replace(re, '<mark class="hit">$1</mark>');
    });
    return out;
  }

  /* 5) Render dropdown + navegación con teclado */
  let sel = -1;
  function icon(){
    return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/>
      <path d="M16.5 16.5 21 21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
  }
  function renderResults(items, term){
    if(!items.length){ resultsC.style.display='none'; resultsC.innerHTML=''; sel=-1; return; }
    resultsC.style.display='block';
    sel = 0;
    resultsC.innerHTML = items.map((it,i)=>`
      <div class="gr-item ${i===0?'active':''}" data-href="${it.url}">
        <span class="gr-ico">${icon()}</span>
        <div>
          <div class="gr-title">${highlight(it.title, term)}</div>
          <div class="gr-desc">${highlight(it.desc||'', term)}</div>
        </div>
      </div>`).join('');
    resultsC.querySelectorAll('.gr-item').forEach(el=> el.addEventListener('click', ()=> location.href = el.dataset.href));
  }

  function moveSel(dir){
    const rows = Array.from(resultsC.querySelectorAll('.gr-item'));
    if(!rows.length) return;
    rows[sel]?.classList.remove('active');
    sel = (sel + dir + rows.length) % rows.length;
    rows[sel]?.classList.add('active');
    rows[sel]?.scrollIntoView({block:'nearest'});
  }

  /* 6) Sincronizar: filtra tiles + resultados globales */
  function run(){
    const term = q.value.trim();
    const tnorm = norm(term);

    // Filtro de tiles del panel
    tiles.forEach(t=>{
      const text = norm(t.innerText + ' ' + (t.dataset.tags||''));
      t.style.display = tnorm && !text.includes(tnorm) ? 'none' : '';
    });

    // Resultados globales
    renderResults(searchAll(term), term);
  }

  q?.addEventListener('input', run);
  btn?.addEventListener('click', run);

  q?.addEventListener('keydown', (e)=>{
    const open = resultsC.style.display === 'block';
    if(!open) return;
    if(e.key === 'ArrowDown'){ e.preventDefault(); moveSel(1); }
    if(e.key === 'ArrowUp'){ e.preventDefault(); moveSel(-1); }
    if(e.key === 'Enter'){
      const active = resultsC.querySelector('.gr-item.active') || resultsC.querySelector('.gr-item');
      if(active){ e.preventDefault(); active.click(); }
    }
    if(e.key === 'Escape'){ resultsC.style.display='none'; }
  });

  document.addEventListener('click',(e)=>{
    if(!resultsC.contains(e.target) && !q.contains(e.target)) resultsC.style.display='none';
  });
})();
</script>
@endsection
