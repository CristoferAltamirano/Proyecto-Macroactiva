@extends('layout')
@section('title','Cobros')

@section('content')
@include('partials.flash')

@php
    use Illuminate\Support\Facades\DB;

    // ===== Contexto de sesión y rol
    $yo    = auth()->user();
    $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
    $isSA  = $rol === 'super_admin';
    $ctxId = (int) (session('ctx_condo_id') ?? 0);

    // ===== Condominios para los selects:
    // - SA: todos (de $condos si viene, o consultamos)
    // - Admin: solo el condominio activo (ctx)
    $condos = $condos ?? collect();
    if ($condos->isEmpty()) {
        $condos = DB::table('condominio')->orderBy('nombre')->get(['id_condominio','nombre']);
    }

    if (!$isSA) {
        // Si es admin, dejamos solo el condominio activo (si hay)
        if ($ctxId > 0) {
            $condos = $condos->where('id_condominio', $ctxId)->values();
        } else {
            // Sin contexto, no mostramos ninguno (evita cruzada)
            $condos = collect();
        }
    }

    // ===== Listado "últimos cobros": si es admin, re-filtramos por ctx
    $ultimos = $ultimos ?? collect();
    if (!$isSA && $ctxId > 0) {
        $ultimos = DB::table('cobro as c')
            ->join('unidad as u', 'u.id_unidad', '=', 'c.id_unidad')
            ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
            ->leftJoin('condominio as co', 'co.id_condominio', '=', 'g.id_condominio')
            ->where('g.id_condominio', $ctxId)
            ->orderByDesc('c.id_cobro')
            ->limit(150)
            ->get([
                'c.id_cobro',
                'co.nombre as condominio',
                DB::raw("COALESCE(u.codigo, CONCAT('U', u.id_unidad)) as unidad"),
                'c.periodo',
                'c.total_cargos',
                'c.total_interes',
                'c.total_descuentos',
                'c.total_pagado',
                DB::raw('(c.total_cargos + c.total_interes - c.total_descuentos - c.total_pagado) as saldo'),
                'c.id_cobro_estado',
            ]);
    }
@endphp

<style>
  /* ======= SOLO PARA ESTA VISTA ======= */
  .cobros-wrap{ max-width:1100px; margin:24px auto; padding:0 16px; }

  /* ---- Cards superiores (2 columnas en desktop) ---- */
  .cards-row{
    display:grid; gap:18px; margin-bottom:18px;
    grid-template-columns:1fr;
  }
  @media (min-width:900px){ .cards-row{ grid-template-columns:1fr 1fr; } }

  .cards-row .card{
    display:flex; flex-direction:column; justify-content:flex-start;
  }
  .cards-row .card h3{
    text-align:center; margin:4px 0 0; font-weight:800; color:#111827;
  }
  .cards-row .hint{ text-align:center; color:#475569; margin:6px 0 0; }

  /* ---- Formularios: filas (label + input) ---- */
  .form-rows{ display:grid; gap:14px; margin-top:16px; }
  .form-row{
    display:flex; justify-content:center; align-items:center; gap:12px;
  }
  .form-row label{
    min-width:160px; text-align:right; font-weight:600; color:#111827;
  }
  .control{
    flex:1; max-width:320px; height:44px;
    padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px;
    background:#fff; color:#0f172a; outline:0;
    transition:border-color .15s, box-shadow .15s;
    text-align:center;
  }
  .control:focus{ border-color:#c7d2fe; box-shadow:0 0 0 4px rgba(59,130,246,.12); }
  .btn-wrap{ display:flex; justify-content:center; margin-top:6px; }
  .btn-wrap .btn{ min-width:150px; }

  /* ---- Card listado ---- */
  .list-card h3{ text-align:center; margin:6px 0 0; font-weight:800; color:#111827; }
  .table-wrap{
    margin-top:12px; border:1px solid #e5e7eb; border-radius:12px; overflow:auto; background:#fff;
  }
  table{ width:100%; border-collapse:collapse; }
  thead th{
    position:sticky; top:0; z-index:1;
    background:#f8fafc; color:#475569; font-weight:700;
    text-transform:uppercase; letter-spacing:.02em; font-size:.9rem;
    border-bottom:1px solid #e5e7eb; padding:12px 14px; text-align:center;
  }
  tbody td{ padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#111827; text-align:center; }
  tbody tr:hover{ background:#f9fafb; }
  td.num{ text-align:right; }

  @media (max-width:720px){
    .form-row{ flex-direction:column; align-items:center; }
    .form-row label{ text-align:center; min-width:auto; }
    .control{ width:100%; max-width:360px; }
    thead th, tbody td{ padding:10px 12px; }
  }
</style>

<div class="cobros-wrap">
  {{-- ===== Cards superiores ===== --}}
  <div class="cards-row">
    {{-- Card 1: generar/actualizar --}}
    <div class="card">
      <h3>Generar/Actualizar cobros desde cargos</h3>
      <p class="hint">Periodo en formato <strong>AAAAMM</strong>. Puedes filtrar por condominio.</p>

      <form method="POST" action="{{ route('admin.cobros.generar') }}" class="form-rows">
        @csrf
        <div class="form-row">
          <label for="periodo-gen">Periodo (AAAAMM):</label>
          <input id="periodo-gen" type="text" name="periodo" placeholder="AAAAMM"
                 required pattern="[0-9]{6}" class="control" value="{{ old('periodo') }}">
        </div>

        <div class="form-row">
          <label for="condo-gen">Condominio:</label>

          {{-- SA => ve todos + opción (Todos); Admin => solo su condominio activo (sin "Todos") --}}
          @if($isSA)
            <select id="condo-gen" name="id_condominio" class="control">
              <option value="">(Todos)</option>
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}" @selected(old('id_condominio')==$c->id_condominio)>{{ $c->nombre }}</option>
              @endforeach
            </select>
          @else
            <select id="condo-gen" name="id_condominio" class="control">
              @forelse($condos as $c)
                <option value="{{ $c->id_condominio }}" selected>{{ $c->nombre }}</option>
              @empty
                <option value="" disabled selected>(sin condominio)</option>
              @endforelse
            </select>
          @endif
        </div>

        <div class="btn-wrap"><button class="btn btn--sm">Generar</button></div>
      </form>
    </div>

    {{-- Card 2: intereses --}}
    <div class="card">
      <h3>Generar intereses de mora</h3>
      <p class="hint">Aplica intereses a los saldos del periodo indicado.</p>

      <form method="POST" action="{{ route('admin.cobros.intereses') }}" class="form-rows">
        @csrf
        <div class="form-row">
          <label for="periodo-int">Periodo (AAAAMM):</label>
          <input id="periodo-int" type="text" name="periodo" placeholder="AAAAMM"
                 required pattern="[0-9]{6}" class="control" value="{{ old('periodo') }}">
        </div>

        <div class="form-row">
          <label for="condo-int">Condominio:</label>

          @if($isSA)
            <select id="condo-int" name="id_condominio" class="control">
              <option value="">(Todos)</option>
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}" @selected(old('id_condominio')==$c->id_condominio)>{{ $c->nombre }}</option>
              @endforeach
            </select>
          @else
            <select id="condo-int" name="id_condominio" class="control">
              @forelse($condos as $c)
                <option value="{{ $c->id_condominio }}" selected>{{ $c->nombre }}</option>
              @empty
                <option value="" disabled selected>(sin condominio)</option>
              @endforelse
            </select>
          @endif
        </div>

        <div class="btn-wrap"><button class="btn btn--sm">Aplicar intereses</button></div>
      </form>
    </div>
  </div>

  {{-- ===== Listado ===== --}}
  <div class="card list-card">
    <h3>Últimos cobros</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Condominio</th>
            <th>Unidad</th>
            <th>Periodo</th>
            <th>Total</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ultimos as $c)
            <tr>
              <td>{{ $c->id_cobro }}</td>
              <td>{{ $c->condominio }}</td>
              <td>{{ $c->unidad }}</td>
              <td>{{ $c->periodo }}</td>
              <td class="num">${{ number_format(($c->total_cargos + $c->total_interes - $c->total_descuentos),0,',','.') }}</td>
              <td class="num">${{ number_format($c->total_pagado,0,',','.') }}</td>
              <td class="num">${{ number_format($c->saldo,0,',','.') }}</td>
              <td>{{ $c->id_cobro_estado }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted">Sin registros.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
