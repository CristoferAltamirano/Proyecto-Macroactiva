@extends('layout')
@section('title','Detalle conciliación')
@section('content')
@include('partials.flash')

@php
  use Illuminate\Support\Facades\DB;

  // Compat: el controlador puede enviar 'conc' o 'c'
  $c = $c ?? ($conc ?? null);

  // ====== Contexto/rol
  $yo    = auth()->user();
  $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
  $ctxId = (int) (session('ctx_condo_id') ?? 0);

  // Detección tolerante del condominio de la conciliación (si el controlador lo incluyó)
  $concCondoId = (int) (
      $c->id_condominio
      ?? ($c->condominio_id ?? 0)
  );

  // ====== Si el admin intenta ver un cierre de otro condominio, bloqueamos la vista
  $bloquearPorCondo = ($rol === 'admin' && $ctxId > 0 && $concCondoId > 0 && $concCondoId !== $ctxId);

  // ====== Unidades visibles en el combo (si el controlador no las envía)
  if (!isset($unidades)) {
    $unidadQuery = DB::table('unidad as u')
      ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
      ->select('u.id_unidad','u.codigo','g.nombre as grupo','g.id_condominio')
      ->orderBy('u.codigo');

    if ($rol === 'admin' && $ctxId > 0) {
      $unidadQuery->where('g.id_condominio', $ctxId);
    }
    $unidades = $unidadQuery->get();
  }

  // ====== Métodos de pago (no sensibles a condominio)
  if (!isset($metodos)) {
    $metodos = DB::table('cat_metodo_pago')->orderBy('nombre')->get();
  }

  // ====== Precalcular IDs de unidades permitidas (para filtrar sugerencias/pagos)
  $allowedUnitIds = collect($unidades)->pluck('id_unidad')->all();
  $allowedUnitSet = array_flip($allowedUnitIds); // lookup O(1)

  // ====== Helper: badge de estado
  function badgeEstado($estado) {
    $estado = strtolower((string)$estado);
    $map = [
      'aplicado'  => ['✅','background:#e8fff0;border:1px solid #34d399;color:#065f46'],
      'pendiente' => ['⏳','background:#fff7ed;border:1px solid #f59e0b;color:#7c2d12'],
      'error'     => ['⚠️','background:#fef2f2;border:1px solid #ef4444;color:#7f1d1d'],
      'sugerido'  => ['🤖','background:#eef2ff;border:1px solid #6366f1;color:#1e3a8a'],
    ];
    $def = ['•','background:#f3f4f6;border:1px solid #d1d5db;color:#374151'];
    [$icon,$style] = $map[$estado] ?? $def;
    return "<span style=\"{$style};padding:2px 8px;border-radius:999px;font-size:12px;display:inline-flex;gap:6px;align-items:center\">{$icon} ".ucfirst($estado)."</span>";
  }

  // ====== Helper: verifica si un pago pertenece al condominio activo (solo admins)
  $pagoPerteneceAlCtx = function($id_pago) use ($rol, $ctxId) {
    if (!$id_pago) return false;
    if ($rol !== 'admin' || $ctxId <= 0) return true; // SA o sin ctx => no filtra
    $gid = DB::table('pago as p')
      ->leftJoin('unidad as u','u.id_unidad','=','p.id_unidad')
      ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
      ->where('p.id_pago',(int)$id_pago)
      ->value('g.id_condominio');
    return ((int)$gid === $ctxId);
  };
@endphp

<style>
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;vertical-align:top}
  thead th{font-weight:700;text-align:left}
  .muted{color:#6b7280}
  .dd > summary{cursor:pointer;list-style:none}
  .dd > summary::-webkit-details-marker{display:none}
  .dd-menu{
    padding:12px;min-width:340px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;
    box-shadow:0 6px 24px rgba(0,0,0,.08);text-align:left
  }
  .row-actions{display:flex;gap:8px;flex-wrap:wrap}
  .nowrap{white-space:nowrap}
  .money{font-variant-numeric:tabular-nums}
</style>

<div class="card">
  <h3>Conciliación #{{ $c->id_conciliacion ?? '—' }}</h3>
  <p class="muted">
    Archivo:
    {{ $c->archivo_nombre ?? basename((string)($c->archivo_path ?? '')) }}
    — Registros: {{ $c->total_registros ?? $c->items_qty ?? '0' }}
  </p>
  <a class="btn" href="{{ route('admin.conciliacion.panel') }}">Volver</a>
</div>

@if($bloquearPorCondo)
  <div class="card">
    <strong>No estás autorizado para ver los detalles de esta conciliación.</strong>
    <div class="muted" style="margin-top:6px">
      Cambia al condominio correspondiente o solicita acceso.
    </div>
  </div>
@else
  <div class="card">
    <h3>Items</h3>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Glosa</th>
          <th class="nowrap">Monto</th>
          <th>Estado</th>
          <th>Pago</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      @foreach($items as $it)
        @php
          // Sugerencias: soporta 'sugerencias_json' (array) o 'sugerido_id_pago' (única sugerencia)
          $sugs = [];
          if (!empty($it->sugerencias_json)) {
            $tmp = json_decode($it->sugerencias_json, true);
            if (is_array($tmp)) $sugs = $tmp;
          } elseif (!empty($it->sugerido_id_pago)) {
            $p = DB::table('pago')->where('id_pago', $it->sugerido_id_pago)->first();
            if ($p) {
              $sugs[] = [
                'id_pago'    => $p->id_pago,
                'id_unidad'  => $p->id_unidad ?? null,
                'fecha_pago' => (string)($p->fecha_pago ?? ''),
                'monto'      => (float)($p->monto ?? 0),
                'score'      => (float)($it->match_score ?? 0.5),
              ];
            }
          }

          // ==== Filtrar sugerencias a unidades del condominio activo (solo admin)
          if ($rol === 'admin' && $ctxId > 0 && !empty($allowedUnitSet)) {
            $sugs = array_values(array_filter($sugs, function($s) use ($allowedUnitSet) {
              $uid = (int)($s['id_unidad'] ?? 0);
              return $uid > 0 && isset($allowedUnitSet[$uid]);
            }));
          }

          $estado = strtolower((string)$it->estado);
          $isPend = ($estado === 'pendiente');
          $isAplic= ($estado === 'aplicado');

          // Mostrar recibo solo si el pago pertenece al ctx (admins)
          $mostrarRecibo = $it->id_pago ? $pagoPerteneceAlCtx($it->id_pago) : false;
        @endphp
        <tr>
          <td>#{{ $it->id_item }}</td>
          <td class="nowrap">{{ $it->fecha ?: '—' }}</td>
          <td>{{ $it->glosa ?: '—' }}</td>
          <td class="money nowrap">${{ number_format((float)$it->monto, 0, ',', '.') }}</td>
          <td>{!! badgeEstado($estado) !!}</td>
          <td>
            @if($it->id_pago && $mostrarRecibo)
              <div class="nowrap">
                <span class="muted">#{{ $it->id_pago }}</span>
                <a class="btn btn--sm" href="{{ route('pagos.recibo.pdf',$it->id_pago) }}" target="_blank" style="margin-left:6px">Recibo</a>
              </div>
            @elseif($it->id_pago)
              <span class="muted">Pago de otro condominio</span>
            @else
              <span class="muted">—</span>
            @endif
          </td>
          <td>
            @if($isPend && (float)$it->monto > 0)
              <div class="row-actions">
                {{-- Coincidencias sugeridas (ya filtradas si admin) --}}
                @if(!empty($sugs))
                  <details class="dd">
                    <summary class="btn">Coincidencias ({{ count($sugs) }})</summary>
                    <div class="dd-menu" style="position:absolute;max-width:520px">
                      @foreach($sugs as $s)
                        <form method="POST" action="{{ route('admin.conciliacion.aplicar',$it->id_item) }}" style="padding:6px">
                          @csrf
                          <div class="muted" style="font-size:12px;margin-bottom:6px">
                            Pago #{{ $s['id_pago'] }}
                            — Unidad: {{ $s['id_unidad'] ?? '—' }}
                            — Fecha: {{ \Illuminate\Support\Str::limit((string)($s['fecha_pago'] ?? ''), 10, '') }}
                            — ${{ number_format((float)($s['monto'] ?? 0), 0, ',', '.') }}
                            @if(isset($s['score'])) — score {{ number_format((float)$s['score'],2,',','.') }} @endif
                          </div>
                          <input type="hidden" name="id_pago" value="{{ $s['id_pago'] }}">
                          <button class="btn">Aplicar</button>
                        </form>
                      @endforeach
                    </div>
                  </details>

                  {{-- Botón rápido: aplicar primera sugerencia --}}
                  <form method="POST" action="{{ route('admin.conciliacion.aplicar',$it->id_item) }}">
                    @csrf
                    <input type="hidden" name="id_pago" value="{{ $sugs[0]['id_pago'] }}">
                    <button class="btn">Aplicar sugerencia</button>
                  </form>
                @endif

                {{-- Crear pago y aplicar (combo de unidades ya filtrado) --}}
                <details class="dd">
                  <summary class="btn">Crear pago</summary>
                  <div class="dd-menu" style="position:absolute;max-width:520px">
                    <form method="POST" action="{{ route('admin.conciliacion.crear',$it->id_item) }}" style="display:grid;gap:8px;padding:8px">
                      @csrf
                      <label>Unidad
                        <select name="id_unidad" required>
                          @foreach($unidades as $u)
                            <option value="{{ $u->id_unidad }}">
                              {{ $u->codigo }} @if($u->grupo) ({{ $u->grupo }}) @endif
                            </option>
                          @endforeach
                        </select>
                      </label>
                      <label>Periodo (AAAAMM opcional)
                        <input type="text" name="periodo" pattern="^[0-9]{6}$" placeholder="AAAAMM">
                      </label>
                      <label>Fecha pago (opcional)
                        <input type="date" name="fecha" value="{{ $it->fecha ?: '' }}">
                      </label>
                      <label>Método
                        {{-- El controlador espera name="metodo" (id_metodo_pago) --}}
                        <select name="metodo" required>
                          @foreach($metodos as $m)
                            <option value="{{ $m->id_metodo_pago }}">{{ $m->nombre }}</option>
                          @endforeach
                        </select>
                      </label>
                      <button class="btn">Crear y aplicar</button>
                    </form>
                  </div>
                </details>
              </div>
            @else
              <span class="muted">—</span>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
