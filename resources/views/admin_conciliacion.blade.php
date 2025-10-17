@extends('layout') 
@section('title','Conciliación bancaria')
@section('content')
@include('partials.flash')

@php
  use Illuminate\Support\Facades\DB;

  $yo    = auth()->user();
  $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
  $ctxId = (int) (session('ctx_condo_id') ?? 0);

  // Si la vista no recibió $lista/$list, o si es admin, re-consultamos de forma segura
  $rows = ($lista ?? ($list ?? collect()));

  if ($rol === 'admin') {
      if ($ctxId > 0) {
          // Solo conciliaciones del condominio activo
          $rows = DB::table('conciliacion')
              ->where('id_condominio', $ctxId)
              ->orderByDesc('id_conciliacion')
              ->limit(60)
              ->get();
      } else {
          // Sin contexto => lista vacía para evitar info cruzada
          $rows = collect();
      }
  }
@endphp

<style>
  .card h3, .card p { text-align: center; }

  /* ===== Archivo (igual que está): label + input en fila centrada */
  .file-row{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
    margin-top:12px;
    flex-wrap:wrap;
  }
  .file-row label{
    font-weight:600;
    text-align:center;
  }
  .control{
    padding:8px;
    border:1px solid #e5e7eb;
    border-radius:10px;
  }

  /* ===== Fechas: dos campos centrados debajo de Archivo */
  .dates-row{
    display:flex;
    justify-content:center;
    gap:24px;
    flex-wrap:wrap;
    margin-top:14px;
  }
  .pair{
    display:flex; flex-direction:column; align-items:center; gap:6px;
  }
  .pair label{ font-weight:600; text-align:center; }
  .pair .control{ width:100%; max-width:260px; }

  /* ===== Botón centrado debajo */
  .actions{
    display:flex; justify-content:center; margin-top:12px;
  }

  /* Tabla */
  table thead th, table tbody td { text-align:center; }

  /* Aviso contexto requerido */
  .alert-soft{
    margin:8px auto 0; max-width:720px; text-align:center;
    background:#fff8e1; border:1px solid #fde68a; color:#7c5800; padding:10px 12px; border-radius:10px;
  }

  @media (max-width:640px){
    .pair .control{ max-width:100%; }
  }
</style>

<div class="card">
  <h3>Cargar extracto (CSV)</h3>
  <p class="muted">
    Formato esperado: <strong>fecha, glosa, monto</strong>. Acepta separador coma o punto y coma. Monto positivo = abono.
  </p>

  @if($rol === 'admin' && $ctxId <= 0)
    <div class="alert-soft">
      Necesitas tener un <strong>condominio activo</strong> para subir extractos. Pide al super admin que te asigne uno.
    </div>
  @endif

  <form method="POST" action="{{ route('admin.conciliacion.upload') }}" enctype="multipart/form-data">
    @csrf

    {{-- ADMIN: fijar id_condominio del contexto para evitar info cruzada --}}
    @if($rol === 'admin' && $ctxId > 0)
      <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
    @endif

    <!-- Archivo (igual que está) -->
    <div class="file-row">
      <label for="archivo">Archivo</label>
      <input id="archivo" type="file" name="archivo" accept=".csv,text/csv" required class="control" {{ ($rol==='admin' && $ctxId<=0) ? 'disabled' : '' }}>
    </div>

    <!-- Fechas centradas debajo -->
    <div class="dates-row">
      <div class="pair">
        <label for="periodo_desde">Periodo desde</label>
        <input id="periodo_desde" type="date" name="periodo_desde" class="control" {{ ($rol==='admin' && $ctxId<=0) ? 'disabled' : '' }}>
      </div>
      <div class="pair">
        <label for="periodo_hasta">Periodo hasta</label>
        <input id="periodo_hasta" type="date" name="periodo_hasta" class="control" {{ ($rol==='admin' && $ctxId<=0) ? 'disabled' : '' }}>
      </div>
    </div>

    <!-- Botón al final, centrado -->
    <div class="actions">
      <button class="btn" {{ ($rol==='admin' && $ctxId<=0) ? 'disabled' : '' }}>Subir</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Conciliaciones recientes</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Archivo</th><th>Desde</th><th>Hasta</th><th>Registros</th><th>Creado</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $c)
        <tr>
          <td>#{{ $c->id_conciliacion }}</td>
          <td>{{ $c->archivo_nombre }}</td>
          <td>{{ $c->periodo_desde ?: '—' }}</td>
          <td>{{ $c->periodo_hasta ?: '—' }}</td>
          <td>{{ $c->total_registros }}</td>
          <td>{{ \Illuminate\Support\Str::limit((string)$c->created_at,19,'') }}</td>
          <td><a class="btn" href="{{ route('admin.conciliacion.detalle',$c->id_conciliacion) }}">Ver detalle</a></td>
        </tr>
      @empty
        <tr><td colspan="7" class="muted">Aún no hay conciliaciones.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
