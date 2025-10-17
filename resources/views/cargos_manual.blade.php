@extends('layout')
@section('title', 'Cargos manuales')
@section('content')
  @include('partials.flash')

  {{-- Errores de validación --}}
  @if ($errors->any())
    <div class="card" style="background:#fff3f3;border:1px solid #fecaca;color:#991b1b">
      <ul style="margin:0 0 0 18px;">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @php
    use Illuminate\Support\Facades\DB;

    // ===== Contexto/rol
    $yo     = auth()->user();
    $rol    = $yo->rol ?? ($yo->tipo_usuario ?? null);
    $ctxId  = (int) (session('ctx_condo_id') ?? 0);

    // ===== Unidades disponibles según rol
    if ($rol === 'admin' && $ctxId > 0) {
        $unidades = DB::table('unidad as u')
          ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
          ->where('g.id_condominio',$ctxId)
          ->orderBy('u.codigo')
          ->get(['u.id_unidad','u.codigo']);
    } else {
        // super_admin u otros: lista completa
        $unidades = DB::table('unidad')->orderBy('codigo')->get(['id_unidad','codigo']);
    }

    // ===== Listados: reforzar filtro anti info cruzada si vienen del controller
    $ultU = $ultU ?? collect();
    $ultI = $ultI ?? collect();

    if ($rol === 'admin' && $ctxId > 0) {
        // Cargos por unidad (tabla cargo_unidad)
        $ultU = DB::table('cargo_unidad as c')
          ->join('unidad as u','u.id_unidad','=','c.id_unidad')
          ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
          ->where('g.id_condominio',$ctxId)
          ->orderByDesc('c.id_cargo_uni')->limit(80)
          ->get([
            'c.id_cargo_uni','c.id_unidad','c.periodo','c.id_concepto_cargo','c.tipo','c.monto','c.detalle'
          ]);

        // Cargos individuales (tabla cargo_individual)
        $ultI = DB::table('cargo_individual as c')
          ->join('unidad as u','u.id_unidad','=','c.id_unidad')
          ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
          ->where('g.id_condominio',$ctxId)
          ->orderByDesc('c.id_cargo_indv')->limit(80)
          ->get([
            'c.id_cargo_indv','c.id_unidad','c.periodo','c.tipo','c.referencia','c.monto','c.detalle'
          ]);
    }

    $isBlockedAdmin = ($rol === 'admin' && $ctxId <= 0);
  @endphp

  {{-- Info de integración con libro --}}
  <div class="card" style="background:#f0f9ff;border:1px solid #bae6fd;color:#0c4a6e">
    <div><strong>TIP:</strong> Los cargos individuales de tipo
      <code>multa</code>, <code>interes</code>, <code>mora</code> o <code>recargo</code>
      generan automáticamente el asiento en el libro:
      Debe <code>1201</code> (CxC) / Haber <code>4202</code> (multas) ó <code>4203</code> (intereses).
    </div>
  </div>

  @if($isBlockedAdmin)
    <div class="card" style="background:#fff8e1;border:1px solid #fde68a;color:#7c5800">
      Necesitas un <strong>condominio activo</strong> para operar cargos. Pide al super admin que te asigne uno.
    </div>
  @endif

  <style>
    .cards-2{ display:grid; gap:20px; grid-template-columns:1fr 1fr; }
    @media(max-width:900px){ .cards-2{ grid-template-columns:1fr; } }
    .card{ display:flex; flex-direction:column; align-items:center; text-align:center; }
    .card h3{ margin-bottom:8px; }
    .card form{ width:100%; display:flex; flex-direction:column; align-items:center; }
    .form-grid{
      display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:12px;
      justify-content:center; justify-items:center; align-items:center; max-width:880px; width:100%;
    }
    @media(max-width:600px){ .form-grid{ grid-template-columns:1fr; } }
    .pair{ display:flex; flex-direction:column; align-items:center; gap:6px; min-width:220px; }
    .pair label{ font-weight:600; }
    .control{
      width:100%; max-width:260px; padding:6px 8px; border:1px solid #e5e7eb; border-radius:10px;
      text-align:center; background:#fff; color:#0f172a; outline:0; transition:border-color .15s, box-shadow .15s;
      box-shadow:var(--shadow-xs);
    }
    .control:focus{ border-color:#c7d2fe; box-shadow:0 0 0 4px rgba(59,130,246,.12); }
    select.control{ text-align-last:center; }
    input[type="number"].control{ text-align:center; }
    .actions{ display:flex; justify-content:center; width:100%; margin-top:8px; gap:8px; flex-wrap:wrap; }
    .table-wrap{
      width:100%; overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow:var(--shadow-xs);
    }
    .table-flat{ width:100%; border-collapse:collapse; }
    .table-flat thead th, .table-flat tbody td{
      padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:center; white-space:nowrap;
    }
    .table-flat thead th{
      font-weight:700; position:sticky; top:0; z-index:1; background:#f8fafc; color:#475569;
      text-transform:uppercase; letter-spacing:.02em; font-size:.9rem;
    }
    .table-flat tbody tr:hover{ background:#f9fafb; }
    .num{ text-align:right !important; }
    .muted{ color:#6b7280; text-align:center; }
  </style>

  <div class="cards-2">
    <!-- ===== Cargo por unidad ===== -->
    <div class="card">
      <h3>Cargo por unidad</h3><br>
      <form method="POST" action="{{ route('admin.cargos.unidad.store') }}">
        @csrf
        <div class="form-grid">
          <div class="pair">
            <label for="uni_id_u">Unidad</label>
            <select id="uni_id_u" class="control" name="id_unidad" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
              @foreach($unidades as $u)
                <option value="{{ $u->id_unidad }}">#{{ $u->id_unidad }} {{ $u->codigo }}</option>
              @endforeach
            </select>
          </div>

          <div class="pair">
            <label for="periodo_u">Periodo (AAAAMM)</label>
            <input id="periodo_u" class="control" type="text" name="periodo" placeholder="AAAAMM"
                   pattern="[0-9]{6}" title="Formato: 6 dígitos (AAAAMM)" inputmode="numeric" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>

          <div class="pair">
            <label for="concepto_u">Concepto</label>
            <select id="concepto_u" class="control" name="id_concepto_cargo" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
              @foreach ($conceptos as $k)
                <option value="{{ $k->id_concepto_cargo }}">{{ $k->nombre }}</option>
              @endforeach
            </select>
          </div>

          <div class="pair">
            <label for="tipo_u">Tipo</label>
            <select id="tipo_u" class="control" name="tipo" {{ $isBlockedAdmin ? 'disabled' : '' }}>
              <option value="normal">normal</option>
              <option value="extra">extra</option>
              <option value="ajuste">ajuste</option>
            </select>
          </div>

          <div class="pair">
            <label for="monto_u">Monto</label>
            <input id="monto_u" class="control" type="number" step="0.01" min="0" inputmode="decimal"
                   name="monto" placeholder="Monto" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>

          <div class="pair">
            <label for="detalle_u">Detalle (opcional)</label>
            <input id="detalle_u" class="control" name="detalle" placeholder="Detalle (opcional)" {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>
        </div>
        <div class="actions">
          <button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Agregar</button>
        </div>
      </form>
    </div>

    <!-- ===== Cargo individual (con tipos estandarizados) ===== -->
    <div class="card">
      <h3>Cargo individual</h3><br>
      <form method="POST" action="{{ route('admin.cargos.individual.store') }}">
        @csrf
        <div class="form-grid">
          <div class="pair">
            <label for="uni_id_i">Unidad</label>
            <select id="uni_id_i" class="control" name="id_unidad" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
              @foreach($unidades as $u)
                <option value="{{ $u->id_unidad }}">#{{ $u->id_unidad }} {{ $u->codigo }}</option>
              @endforeach
            </select>
          </div>

          <div class="pair">
            <label for="periodo_i">Periodo (AAAAMM)</label>
            <input id="periodo_i" class="control" type="text" name="periodo" placeholder="AAAAMM"
                   pattern="[0-9]{6}" title="Formato: 6 dígitos (AAAAMM)" inputmode="numeric" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>

          <div class="pair">
            <label for="tipo_i">Tipo</label>
            {{-- Estos valores activan el trigger de devengo en libro --}}
            <select id="tipo_i" class="control" name="tipo" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
              <option value="multa">multa</option>
              <option value="interes">interes</option>
              <option value="mora">mora</option>
              <option value="recargo">recargo</option>
              <option value="otro">otro (no devenga auto)</option>
            </select>
          </div>

          <div class="pair">
            <label for="ref_i">Referencia (opcional)</label>
            <input id="ref_i" class="control" name="referencia" placeholder="Referencia (opcional)" {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>

          <div class="pair">
            <label for="monto_i">Monto</label>
            <input id="monto_i" class="control" type="number" step="0.01" min="0" inputmode="decimal"
                   name="monto" placeholder="Monto" required {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>

          <div class="pair">
            <label for="detalle_i">Detalle (opcional)</label>
            <input id="detalle_i" class="control" name="detalle" placeholder="Detalle (opcional)" {{ $isBlockedAdmin ? 'disabled' : '' }}>
          </div>
        </div>
        <div class="actions">
          <button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Agregar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===== Últimos cargos por unidad ===== -->
  <div class="card" style="margin-top:20px">
    <h3>Últimos cargos por unidad</h3><br>
    <div class="table-wrap">
      <table class="table table-flat">
        <thead>
          <tr>
            <th>ID</th>
            <th>Unidad</th>
            <th>Periodo</th>
            <th>Concepto</th>
            <th>Tipo</th>
            <th class="num">Monto</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ultU as $c)
            <tr>
              <td>{{ $c->id_cargo_uni }}</td>
              <td>{{ $c->id_unidad }}</td>
              <td>{{ $c->periodo }}</td>
              <td>{{ $c->id_concepto_cargo }}</td>
              <td>{{ $c->tipo }}</td>
              <td class="num">${{ number_format($c->monto, 0, ',', '.') }}</td>
              <td>{{ $c->detalle }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Sin cargos.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div><br>
  </div>

  <!-- ===== Últimos cargos individuales ===== -->
  <div class="card">
    <h3>Últimos cargos individuales</h3><br>
    <div class="table-wrap">
      <table class="table table-flat">
        <thead>
          <tr>
            <th>ID</th>
            <th>Unidad</th>
            <th>Periodo</th>
            <th>Tipo</th>
            <th>Ref</th>
            <th class="num">Monto</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ultI as $c)
            <tr>
              <td>{{ $c->id_cargo_indv }}</td>
              <td>{{ $c->id_unidad }}</td>
              <td>{{ $c->periodo }}</td>
              <td>{{ $c->tipo }}</td>
              <td>{{ $c->referencia }}</td>
              <td class="num">${{ number_format($c->monto, 0, ',', '.') }}</td>
              <td>{{ $c->detalle }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Sin cargos.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div><br>
  </div>
@endsection
