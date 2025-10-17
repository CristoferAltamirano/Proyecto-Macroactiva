@extends('layout')
@section('title','Exportar')
@section('content')
@include('partials.flash')

@php
  use Illuminate\Support\Facades\DB;

  $yo    = auth()->user();
  $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
  $ctxId = (int) (session('ctx_condo_id') ?? 0);

  $condos = collect();
  $ctxCondo = null;

  if ($rol === 'super_admin') {
      $condos = DB::table('condominio')->orderBy('nombre')->get();
  } elseif ($rol === 'admin') {
      if ($ctxId > 0) {
          $ctxCondo = DB::table('condominio')->where('id_condominio', $ctxId)->first();
      }
  }
@endphp

<style>
  .grid{
    display:grid;
    gap:18px;
    grid-template-columns:1fr;
  }
  @media (min-width:900px){ .grid{ grid-template-columns:1fr 1fr; } }

  .card h3{ text-align:center; margin:0 0 4px; font-weight:800; color:#111827; }
  .form-block{ display:grid; gap:10px; justify-items:center; }
  .control{
    width:100%; max-width:320px; padding:8px;
    border:1px solid #e5e7eb; border-radius:10px; text-align:center;
  }
  .row{ display:flex; gap:10px; flex-wrap:wrap; justify-content:center; }
  .muted{ color:#64748b; text-align:center; }
  .alert-soft{
    margin:8px auto 0; max-width:720px; text-align:center;
    background:#fff8e1; border:1px solid #fde68a; color:#7c5800; padding:10px 12px; border-radius:10px;
  }
</style>

<div class="grid">

  {{-- ================= Exportar Cobros ================= --}}
  <div class="card">
    <h3>Exportar Cobros (CSV)</h3>

    @if($rol==='admin' && $ctxId<=0)
      <div class="alert-soft">Necesitas un <strong>condominio activo</strong> para exportar. Pide al super admin que te asigne uno.</div>
    @endif

    <form method="POST" action="{{ route('admin.export.cobros.csv') }}" class="form-block">
      @csrf

      {{-- Condominio (según rol) --}}
      @if($rol==='super_admin')
        <div class="row">
          <select name="id_condominio" class="control">
            <option value="">(Todos)</option>
            @foreach($condos as $c)
              <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
            @endforeach
          </select>
        </div>
      @elseif($rol==='admin' && $ctxId>0)
        <div class="muted">
          <strong>Condominio:</strong> {{ $ctxCondo->nombre ?? ('#'.$ctxId) }}
        </div>
        <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
      @endif

      <div class="row">
        <input type="text" name="periodo_desde" placeholder="Desde AAAAMM" pattern="[0-9]{6}" class="control">
        <input type="text" name="periodo_hasta" placeholder="Hasta AAAAMM" pattern="[0-9]{6}" class="control">
      </div>

      <button class="btn" {{ ($rol==='admin' && $ctxId<=0) ? 'disabled' : '' }}>Descargar</button>
    </form>
  </div>

  {{-- ================= Exportar Pagos ================= --}}
  <div class="card">
    <h3>Exportar Pagos (CSV)</h3>

    @if($rol==='admin' && $ctxId<=0)
      <div class="alert-soft">Necesitas un <strong>condominio activo</strong> para exportar. Pide al super admin que te asigne uno.</div>
    @endif

    <form method="POST" action="{{ route('admin.export.pagos.csv') }}" class="form-block">
      @csrf

      {{-- Condominio (según rol) --}}
      @if($rol==='super_admin')
        <div class="row">
          <select name="id_condominio" class="control">
            <option value="">(Todos)</option>
            @foreach($condos as $c)
              <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
            @endforeach
          </select>
        </div>
      @elseif($rol==='admin' && $ctxId>0)
        <div class="muted">
          <strong>Condominio:</strong> {{ $ctxCondo->nombre ?? ('#'.$ctxId) }}
        </div>
        <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
      @endif

      <div class="row">
        <input type="date" name="fecha_desde" class="control">
        <input type="date" name="fecha_hasta" class="control">
      </div>

      <button class="btn" {{ ($rol==='admin' && $ctxId<=0) ? 'disabled' : '' }}>Descargar</button>
    </form>
  </div>

</div>
@endsection
