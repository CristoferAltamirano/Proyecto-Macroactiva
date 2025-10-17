@extends('layout')
@section('title', 'Fondo de Reserva')

@section('content')
@include('partials.flash')

@php
  // ===== Fallbacks seguros (sin tocar controllers)
  $condos  = $condos  ?? collect();
  $idCondo = $idCondo ?? request('id_condominio');
  $mov     = $mov     ?? collect();
  $resumen = $resumen ?? collect();

  // Rol / contexto para ocultar selector a admin de condominio
  $user   = auth()->user();
  $role   = $user->rol ?? ($user->tipo_usuario ?? null);
  $ctxNom = session('ctx_condo_nombre');
  $ctxId  = (int) (session('ctx_condo_id') ?? 0);

  // 🔒 Admin: forzar idCondo al contexto (anti info cruzada)
  if ($role === 'admin') {
      $idCondo = $ctxId ?: null;
  }
@endphp

<style>
/* ===== NUEVAS CLASES PARA ESTILOS ÚNICOS DE ESTA PÁGINA ===== */
.cards-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  margin-top: 20px;
}
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  box-shadow: var(--shadow-sm);
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.card h3 {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 16px;
}
/* ===== Estilo para los elementos dentro del card (centrados horizontalmente) ===== */
.form-inline {
  display: flex;
  justify-content: center; /* Centra los items horizontalmente */
  gap: 20px; /* Espacio entre los elementos */
  width: 100%;
}
.pair {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 240px;
}
.control {
  width: 100%;
  max-width: 280px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  margin: 0 auto;
}
.actions {
  display: flex;
  justify-content: center;
  width: 100%;
  margin-top: 12px;
  gap: 10px;
  grid-column: span 2;
}
/* Ajustes para la tabla */
.movimientos-table,
.resumen-mensual-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.movimientos-table th,
.movimientos-table td,
.resumen-mensual-table th,
.resumen-mensual-table td {
  border-bottom: 1px solid #e5e7eb;
  text-align: center;
  padding: 6px 10px;
}
.movimientos-table th,
.resumen-mensual-table th {
  font-weight: 700;
}
.movimientos-table td,
.resumen-mensual-table td {
  text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;
}
/* Resumen mensual */
.resumen-mensual-table th { width: 40%; }
.resumen-mensual-table td { width: 30%; }
.resumen-mensual-table td:last-child { width: 30%; }
/* Botón de exportar CSV */
.export-button {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 10px 20px;
  border-radius: 10px;
  background-color: var(--primary);
  color: white;
  cursor: pointer;
  width: 100%;
  text-align: center;
}
.export-button:hover { background-color: var(--primary-hover); }
.num { text-align: right; }
.pill {
  background: #fff;
  color: var(--primary);
  border: 1px solid var(--border);
  padding: 8px 12px;
  border-radius: 999px;
  box-shadow: var(--shadow-xs);
  font-weight: 600;
}
</style>

<div class="card">
  <h3>Fondo de Reserva</h3><br>

  {{-- SUPER ADMIN: puede elegir condominio --}}
  @if ($role === 'super_admin')
    <form method="GET" action="{{ route('admin.fr.panel') }}">
      <div class="form-inline">
        <label style="margin-top: 10px" for="condo" class="inline-label">Condominio</label>
        <select id="condo" name="id_condominio" class="control">
          @forelse ($condos as $c)
            <option value="{{ $c->id_condominio }}" @selected(($idCondo ?? null) == $c->id_condominio)>{{ $c->nombre }}</option>
          @empty
            <option value="" disabled selected>— Sin condominios —</option>
          @endforelse
        </select>
        <button class="btn">Ver</button>
      </div>
    </form>
  @else
    {{-- ADMIN DE CONDOMINIO (u otros): no mostramos selector, solo el condominio activo --}}
    <div class="form-inline">
      <label style="margin-top: 10px" class="inline-label">Condominio</label>
      <span class="pill">{{ $ctxNom ?: 'Condominio activo' }}</span>
    </div>
  @endif

  <br>
</div>

@if (!empty($idCondo))
  <div class="cards-2">
    {{-- Card Nuevo Movimiento --}}
    <div class="card new-card-full">
      <h3>Nuevo movimiento</h3><br>

      <form method="POST" action="{{ route('admin.fr.store') }}">
        @csrf
        <input type="hidden" name="id_condominio" value="{{ $idCondo }}">

        <div class="pair">
          <label for="fecha">Fecha</label>
          <input id="fecha" class="control" type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
        </div><br>

        <div class="pair">
          <label for="tipo">Tipo</label>
          <select id="tipo" class="control" name="tipo" required>
            <option value="abono" @selected(old('tipo') === 'abono')>Abono (entra al FR)</option>
            <option value="cargo" @selected(old('tipo') === 'cargo')>Cargo (sale del FR)</option>
          </select>
        </div><br>

        <div class="pair">
          <label for="monto">Monto</label>
          <input id="monto" class="control" type="number" name="monto" step="0.01" min="0.01" placeholder="Ej: 30000" value="{{ old('monto') }}" required>
        </div><br>

        <div class="pair">
          <label for="periodo">Periodo (AAAAMM, opcional)</label>
          <input id="periodo" class="control" type="text" name="periodo" pattern="^[0-9]{6}$" placeholder="Ej: 202509" value="{{ old('periodo') }}">
        </div><br>

        <div class="pair glosa">
          <label for="glosa">Glosa (opcional)</label>
          <input id="glosa" class="control" type="text" name="glosa" maxlength="200" placeholder="Detalle del movimiento" value="{{ old('glosa') }}">
          <div class="help">Ej: “Aporte extraordinario”, “Pago reparación ascensor”, etc.</div>
        </div><br>

        <div class="pair">
          <label style="display:flex;gap:8px;">
            <input type="checkbox" name="contabilizar" value="1" {{ old('contabilizar', true) ? 'checked' : '' }}>
            Contabilizar asiento en Libro (Banco 1101 / FR 3101)
          </label>
        </div><br>

        <div class="actions">
          <button class="btn">Guardar</button>
        </div>
      </form><br>

      <div class="hr-soft"></div>
      <div class="help">
        <strong>Abono:</strong> Debe Banco / Haber Fondo de Reserva.<br>
        <strong>Cargo:</strong> Debe Fondo de Reserva / Haber Banco.
      </div>
    </div>

    {{-- Movimientos --}}
    <div class="card">
      <h3>Movimientos (últimos 200)</h3>
      <table class="movimientos-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Periodo</th>
            <th class="num">Monto</th>
            <th>Glosa</th>
          </tr>
        </thead>
        <tbody>
          @forelse($mov as $m)
            <tr>
              <td>{{ $m->fecha ?? '' }}</td>
              <td>{{ $m->tipo ?? '' }}</td>
              <td>{{ $m->periodo ?? '' }}</td>
              <td class="num">
                @php $val = isset($m->monto) ? (float)$m->monto : 0; @endphp
                ${{ number_format($val, 0, ',', '.') }}
              </td>
              <td>{{ $m->glosa ?? '' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="muted">Sin datos.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Resumen mensual + export --}}
    <div class="card">
      <h3>Resumen mensual</h3>
      <table class="resumen-mensual-table">
        <thead>
          <tr>
            <th>Mes</th>
            <th class="new-num">Neto</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          @forelse($resumen as $r)
            @php $neto = isset($r->neto) ? (float)$r->neto : 0; @endphp
            <tr>
              <td>{{ $r->mes ?? '' }}</td>
              <td class="new-num">${{ number_format($neto, 0, ',', '.') }}</td>
              <td>
                <div class="export-button-wrapper">
                  <form method="POST" action="{{ route('admin.fr.export.csv') }}">
                    @csrf
                    <input type="hidden" name="id_condominio" value="{{ $idCondo }}">
                    <button class="export-button">Exportar CSV</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="muted">Sin datos.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif

@endsection
