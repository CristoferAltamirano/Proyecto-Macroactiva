@extends('layout')
@section('title','Trabajadores')
@section('content')
  @include('partials.flash')

  @php
    $condos       = $condos ?? collect();
    $trabajadores = $trabajadores ?? collect();
    $contratos    = $contratos ?? collect();
  @endphp

  <style>
    .cards-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    @media(max-width:900px){.cards-2{grid-template-columns:1fr}}
    .card{display:flex;flex-direction:column;align-items:center;text-align:center}
    .pair{display:flex;flex-direction:column;gap:6px;min-width:220px;align-items:center}
    .control{width:100%;max-width:260px;padding:8px;border:1px solid #e5e7eb;border-radius:10px;text-align:center}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;justify-items:center}
    @media(max-width:600px){.form-grid{grid-template-columns:1fr}}
    .actions{display:flex;gap:10px;justify-content:center;margin-top:8px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:center}
    .table-wrap{width:100%;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
    .num{text-align:right}
    .muted{color:#6b7280}
  </style>

  <div class="cards-2">
    {{-- ===== Nuevo trabajador ===== --}}
    <div class="card">
      <h3>Nuevo trabajador</h3><br>
      <form method="POST" action="{{ route('admin.trab.store') }}">
        @csrf
        <div class="form-grid">

          <div class="pair">
            <label>Condominio</label>
            <select name="id_condominio" class="control" required>
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>

          <div class="pair">
            <label>Tipo</label>
            <select name="tipo" class="control">
              <option value="empleado">empleado</option>
              <option value="externo">externo</option>
            </select>
          </div>

          <div class="pair">
            <label>RUT base</label>
            <input class="control" name="rut_base" placeholder="Ej: 12345678" required>
          </div>

          <div class="pair">
            <label>DV</label>
            <input class="control" name="rut_dv" placeholder="K/0-9" maxlength="1">
          </div>

          <div class="pair">
            <label>Nombres</label>
            <input class="control" name="nombres" required>
          </div>

          <div class="pair">
            <label>Apellidos</label>
            <input class="control" name="apellidos">
          </div>

          <div class="pair">
            <label>Cargo</label>
            <input class="control" name="cargo">
          </div>

          <div class="pair">
            <label>Email</label>
            <input class="control" type="email" name="email" placeholder="ej: correo@demo.cl">
          </div>

          <div class="pair">
            <label>Teléfono</label>
            <input class="control" name="telefono" placeholder="+56 9 ....">
          </div>

        </div>
        <div class="actions">
          <button class="btn" type="submit">Guardar</button>
        </div>
      </form>
    </div>

    {{-- ===== Contratos (rápido) ===== --}}
    <div class="card">
      <h3>Contratos (rápido)</h3><br>
      <form method="POST" action="{{ route('admin.contratos.store') }}">
        @csrf
        <div class="form-grid">
          <div class="pair">
            <label>ID Trabajador</label>
            <input class="control" type="number" min="1" name="id_trabajador" required>
          </div>

          <div class="pair">
            <label>Tipo contrato</label>
            <select class="control" name="tipo_contrato">
              <option value="indefinido">indefinido</option>
              <option value="plazo_fijo">plazo fijo</option>
              <option value="honorarios">honorarios</option>
            </select>
          </div>

          <div class="pair">
            <label>Inicio</label>
            <input class="control" type="date" name="inicio" required>
          </div>

          <div class="pair">
            <label>Término</label>
            <input class="control" type="date" name="termino">
          </div>

          <div class="pair">
            <label>Sueldo base</label>
            <input class="control" type="number" name="sueldo_base" min="0" step="0.01" value="0">
          </div>

          <div class="pair">
            <label>Jornada</label>
            <input class="control" name="jornada" value="Completa">
          </div>

          <div class="pair" style="grid-column:1/-1">
            <label>Documento URL</label>
            <input class="control" type="url" name="doc_url" placeholder="https://...">
          </div>
        </div>
        <div class="actions">
          <button class="btn" type="submit">Registrar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ===== Últimos trabajadores ===== --}}
  <div class="card" style="margin-top:20px">
    <h3>Últimos trabajadores</h3><br>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Condominio</th><th>Nombre</th><th>Cargo</th><th>Email</th>
          </tr>
        </thead>
        <tbody>
          @forelse($trabajadores as $t)
            <tr>
              <td>{{ $t->id_trabajador }}</td>
              <td>{{ $t->condominio ?? $t->id_condominio }}</td>
              <td>{{ trim(($t->nombres ?? '').' '.($t->apellidos ?? '')) }}</td>
              <td>{{ $t->cargo }}</td>
              <td>{{ $t->email }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="muted">Sin datos.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div><br>
  </div>

  {{-- ===== Últimos contratos ===== --}}
  <div class="card">
    <h3>Últimos contratos</h3><br>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Trabajador</th>
            <th>Condominio</th>
            <th>Tipo</th>
            <th>Inicio</th>
            <th>Término</th>
            <th class="num">Sueldo</th>
            <th>Jornada</th>
            <th>Doc</th>
          </tr>
        </thead>
        <tbody>
          @forelse($contratos as $c)
            <tr>
              <td>{{ $c->id_contrato }}</td>
              <td>#{{ $c->id_trabajador }} - {{ trim(($c->nombres ?? '').' '.($c->apellidos ?? '')) }}</td>
              <td>{{ $c->condominio }}</td>
              <td>{{ $c->tipo }}</td>
              <td>{{ $c->inicio }}</td>
              <td>{{ $c->termino }}</td>
              <td class="num">${{ number_format((float)$c->sueldo_base, 0, ',', '.') }}</td>
              <td>{{ $c->jornada }}</td>
              <td>
                @if(!empty($c->doc_url))
                  <a href="{{ $c->doc_url }}" target="_blank">ver</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="muted">Sin contratos.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div><br>
  </div>
@endsection
