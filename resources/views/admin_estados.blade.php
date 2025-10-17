@extends('layout')
@section('title', 'Estados Financieros')

@section('content')
  @include('partials.flash')

  @php
    use Illuminate\Support\Facades\DB;

    // Defaults seguros + merge con lo que venga del controlador ($defaults o $d)
    $__def = [
        'desde'  => now()->firstOfMonth()->toDateString(),
        'hasta'  => now()->toDateString(),
        'corte'  => now()->toDateString(),
        'desde2' => now()->copy()->subMonth()->firstOfMonth()->toDateString(),
        'hasta2' => now()->copy()->subMonth()->endOfMonth()->toDateString(),
    ];
    $__src = [];
    if (isset($defaults) && is_array($defaults))      $__src = $defaults;
    elseif (isset($d) && is_array($d))                $__src = $d;
    $d = array_merge($__def, $__src);

    // ====== Contexto/rol
    $yo     = auth()->user();
    $rol    = $yo->rol ?? ($yo->tipo_usuario ?? null);
    $ctxId  = (int) (session('ctx_condo_id') ?? 0);

    // Para super_admin listamos todos; para admin sólo mostramos el activo
    $condos = collect();
    $ctxCondo = null;
    if ($rol === 'super_admin') {
        $condos = DB::table('condominio')->orderBy('nombre')->get();
    } elseif ($rol === 'admin' && $ctxId > 0) {
        $ctxCondo = DB::table('condominio')->where('id_condominio', $ctxId)->first();
    }

    $isBlockedAdmin = ($rol === 'admin' && $ctxId <= 0);
  @endphp

  <style>
    .cards-2{ display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media (max-width:900px){ .cards-2{ grid-template-columns:1fr; } }
    .card{ display:flex; flex-direction:column; align-items:center; text-align:center; }
    .card h3{ margin-bottom:8px; }
    .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:12px; justify-content:center; justify-items:center; align-items:center; max-width:700px; width:100%; }
    @media (max-width:600px){ .form-grid{ grid-template-columns:1fr; } }
    .form-inline{ display:flex; flex-wrap:wrap; gap:16px; margin-bottom:12px; justify-content:center; align-items:center; width:100%; max-width:700px; }
    .block{ display:flex; flex-direction:column; gap:12px; align-items:center; width:100%; }
    .pair{ display:flex; flex-direction:column; align-items:center; gap:6px; min-width:220px; }
    .pair label{ font-weight:600; }
    .control{ width:100%; max-width:240px; padding:8px; border:1px solid #e5e7eb; border-radius:10px; margin:0 auto; }
    .actions{ display:flex; justify-content:center; margin-top:8px; width:100%; }
    .hr-soft{ height:1px; background:#E5E7EB; width:100%; max-width:700px; margin:12px 0; border-radius:1px; }

    .muted{ color:#64748b; }
    .alert-soft{
      background:#fff8e1; border:1px solid #fde68a; color:#7c5800;
      padding:10px 12px; border-radius:10px; margin:8px 0; text-align:center;
      width:100%; max-width:720px;
    }

    /* tablas */
    .table-wrap{ width:100%; overflow:auto; }
    table{ width:100%; border-collapse:collapse; }
    thead th{ background:#f8fafc; border-bottom:1px solid #e5e7eb; padding:8px; text-align:center; }
    tbody td{ border-bottom:1px solid #f1f5f9; padding:8px; text-align:center; }
    tfoot td{ padding:8px; font-weight:700; }
    .num{ text-align:right; white-space:nowrap; }
  </style>

  {{-- Aviso si admin no tiene contexto activo --}}
  @if($isBlockedAdmin)
    <div class="card">
      <div class="alert-soft">
        Necesitas un <strong>condominio activo</strong> para consultar estados. Pide al super admin que te asigne uno.
      </div>
    </div>
  @endif

  <div class="cards-2">

    {{-- ===== Sumas y Saldos ===== --}}
    <div class="card">
      <h3>Sumas y Saldos</h3><br>

      <form method="POST" action="{{ route('admin.estados.sumas') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <div class="muted" style="margin-bottom:6px;">
            <strong>Condominio:</strong> {{ $ctxCondo->nombre ?? ('#'.$ctxId) }}
          </div>
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="form-grid">
          <div class="pair">
            <label>Desde</label>
            <input class="control" type="date" name="desde" value="{{ old('desde', $d['desde'] ?? '') }}" required>
          </div>
          <div class="pair">
            <label>Hasta</label>
            <input class="control" type="date" name="hasta" value="{{ old('hasta', $d['hasta'] ?? '') }}" required>
          </div>
        </div>
        <div class="actions"><button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Ver</button></div>
      </form>

      <div class="hr-soft"></div>

      <form method="POST" action="{{ route('admin.estados.sumas.csv') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="form-grid">
          <div class="pair">
            <label>Desde</label>
            <input class="control" type="date" name="desde" value="{{ old('desde', $d['desde'] ?? '') }}" required>
          </div>
          <div class="pair">
            <label>Hasta</label>
            <input class="control" type="date" name="hasta" value="{{ old('hasta', $d['hasta'] ?? '') }}" required>
          </div>
        </div>
        <div class="actions"><button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Descargar CSV</button></div><br>
      </form>
    </div>

    {{-- ===== EERR ===== --}}
    <div class="card">
      <h3>Estado de Resultados (EERR)</h3><br>

      <form method="POST" action="{{ route('admin.estados.eerr') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <div class="muted" style="margin-bottom:6px;">
            <strong>Condominio:</strong> {{ $ctxCondo->nombre ?? ('#'.$ctxId) }}
          </div>
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="form-grid">
          <div class="pair">
            <label>Desde</label>
            <input class="control" type="date" name="desde" value="{{ old('desde', $d['desde'] ?? '') }}" required>
          </div>
          <div class="pair">
            <label>Hasta</label>
            <input class="control" type="date" name="hasta" value="{{ old('hasta', $d['hasta'] ?? '') }}" required>
          </div>
        </div>
        <div class="actions"><button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Ver</button></div>
      </form>

      <div class="hr-soft"></div>

      <form method="POST" action="{{ route('admin.estados.eerr.csv') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="form-grid">
          <div class="pair">
            <label>Desde</label>
            <input class="control" type="date" name="desde" value="{{ old('desde', $d['desde'] ?? '') }}" required>
          </div>
          <div class="pair">
            <label>Hasta</label>
            <input class="control" type="date" name="hasta" value="{{ old('hasta', $d['hasta'] ?? '') }}" required>
          </div>
        </div>
        <div class="actions"><button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Descargar CSV</button></div><br>
      </form>
    </div>

    {{-- ===== Balance General ===== --}}
    <div class="card">
      <h3>Balance General</h3><br>

      <form method="POST" action="{{ route('admin.estados.balance') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <div class="muted" style="margin-bottom:6px;">
            <strong>Condominio:</strong> {{ $ctxCondo->nombre ?? ('#'.$ctxId) }}
          </div>
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="pair">
          <label>Corte</label>
          <input class="control" type="date" name="corte" value="{{ old('corte', $d['corte'] ?? '') }}" required>
        </div>
        <div class="actions"><button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Ver</button></div>
      </form>

      <div class="hr-soft"></div>

      <form method="POST" action="{{ route('admin.estados.balance.csv') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="pair">
          <label>Corte</label>
          <input class="control" type="date" name="corte" value="{{ old('corte', $d['corte'] ?? '') }}" required>
        </div>
        <div class="actions"><button class="btn" {{ $isBlockedAdmin ? 'disabled' : '' }}>Descargar CSV</button></div><br>
      </form>
    </div>

    {{-- ===== Balance Comparativo ===== --}}
    <div class="card">
      <h3>Balance Comparativo</h3><br>

      <form method="POST" action="{{ route('admin.estados.comparativo') }}">
        @csrf

        {{-- Condominio según rol --}}
        @if($rol === 'super_admin')
          <div class="pair" style="margin-bottom:8px;">
            <label>Condominio</label>
            <select name="id_condominio" class="control">
              @foreach($condos as $c)
                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>
        @elseif($rol === 'admin' && $ctxId > 0)
          <div class="muted" style="margin-bottom:6px;">
            <strong>Condominio:</strong> {{ $ctxCondo->nombre ?? ('#'.$ctxId) }}
          </div>
          <input type="hidden" name="id_condominio" value="{{ $ctxId }}">
        @endif

        <div class="block">

          {{-- Periodo A (Ver) --}}
          <div class="form-inline">
            <div class="pair">
              <label>Desde</label>
              <input class="control" type="date" name="desde1" value="{{ old('desde1', $d['desde'] ?? '') }}" required>
            </div>
            <div class="pair">
              <label>Hasta</label>
              <input class="control" type="date" name="hasta1" value="{{ old('hasta1', $d['hasta'] ?? '') }}" required>
            </div>
            <button class="btn" name="action" value="ver" {{ $isBlockedAdmin ? 'disabled' : '' }}>Ver</button>
          </div>

          <div class="hr-soft"></div>

          {{-- Periodo B (Descargar CSV) --}}
          <div class="form-inline">
            <div class="pair">
              <label>Desde</label>
              <input class="control" type="date" name="desde2" value="{{ old('desde2', $d['desde2'] ?? '') }}" required>
            </div>
            <div class="pair">
              <label>Hasta</label>
              <input class="control" type="date" name="hasta2" value="{{ old('hasta2', $d['hasta2'] ?? '') }}" required>
            </div>
            <button class="btn" name="action" value="csv" {{ $isBlockedAdmin ? 'disabled' : '' }}>Descargar CSV</button><br>
          </div>

        </div>
      </form>
    </div>

  </div>

  {{-- ================== RESULTADOS ================== --}}
  @if(!empty($resultado) && is_array($resultado))
    <div class="card" style="margin-top:20px; align-items:stretch; text-align:left;">
      <h3 class="text-center" style="margin-bottom:12px;">Resultados</h3>

      {{-- SUMAS Y SALDOS --}}
      @if(($resultado['tipo'] ?? '') === 'sumas')
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Cuenta</th><th>Nombre</th>
                <th>Debe</th><th>Haber</th><th>Saldo</th>
              </tr>
            </thead>
            <tbody>
              @php $tDebe=0; $tHaber=0; $tSaldo=0; @endphp
              @foreach(($resultado['rows'] ?? []) as $x)
                @php
                  $tDebe += (float)$x->debe;
                  $tHaber+= (float)$x->haber;
                  $tSaldo+= (float)$x->saldo;
                @endphp
                <tr>
                  <td>{{ $x->codigo }}</td>
                  <td>{{ $x->nombre }}</td>
                  <td class="num">{{ number_format($x->debe,2,',','.') }}</td>
                  <td class="num">{{ number_format($x->haber,2,',','.') }}</td>
                  <td class="num">{{ number_format($x->saldo,2,',','.') }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="num">Totales</td>
                <td class="num">{{ number_format($tDebe,2,',','.') }}</td>
                <td class="num">{{ number_format($tHaber,2,',','.') }}</td>
                <td class="num">{{ number_format($tSaldo,2,',','.') }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      @endif

      {{-- EERR --}}
      @if(($resultado['tipo'] ?? '') === 'eerr')
        @php
          $ing = $resultado['ingresos'] ?? collect();
          $gas = $resultado['gastos'] ?? collect();
          $totIng = $resultado['totIngresos'] ?? 0;
          $totGas = $resultado['totGastos'] ?? 0;
          $util   = $resultado['resultado'] ?? 0;
        @endphp
        <div class="table-wrap">
          <table>
            <thead><tr><th colspan="3">Ingresos</th></tr></thead>
            <tbody>
              @foreach($ing as $x)
                <tr>
                  <td>{{ $x->codigo }}</td>
                  <td>{{ $x->nombre }}</td>
                  <td class="num">{{ number_format(($x->haber - $x->debe),2,',','.') }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot><tr><td colspan="2" class="num">Total Ingresos</td><td class="num">{{ number_format($totIng,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
        <br>
        <div class="table-wrap">
          <table>
            <thead><tr><th colspan="3">Gastos</th></tr></thead>
            <tbody>
              @foreach($gas as $x)
                <tr>
                  <td>{{ $x->codigo }}</td>
                  <td>{{ $x->nombre }}</td>
                  <td class="num">{{ number_format(($x->debe - $x->haber),2,',','.') }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot><tr><td colspan="2" class="num">Total Gastos</td><td class="num">{{ number_format($totGas,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
        <br>
        <div class="table-wrap">
          <table>
            <tfoot><tr><td class="num">Resultado del período</td><td class="num">{{ number_format($util,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
      @endif

      {{-- BALANCE GENERAL --}}
      @if(($resultado['tipo'] ?? '') === 'balance')
        @php
          $act = $resultado['activos'] ?? collect();
          $pas = $resultado['pasivos'] ?? collect();
          $pat = $resultado['patrimonio'] ?? collect();
        @endphp
        <div class="table-wrap">
          <table>
            <thead><tr><th colspan="3">Activos</th></tr></thead>
            <tbody>
              @foreach($act as $x)
                <tr><td>{{ $x->codigo }}</td><td>{{ $x->nombre }}</td><td class="num">{{ number_format($x->saldo,2,',','.') }}</td></tr>
              @endforeach
            </tbody>
            <tfoot><tr><td colspan="2" class="num">Total Activo</td><td class="num">{{ number_format($resultado['totAct'] ?? 0,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
        <br>
        <div class="table-wrap">
          <table>
            <thead><tr><th colspan="3">Pasivos</th></tr></thead>
            <tbody>
              @foreach($pas as $x)
                <tr><td>{{ $x->codigo }}</td><td>{{ $x->nombre }}</td><td class="num">{{ number_format(-$x->saldo,2,',','.') }}</td></tr>
              @endforeach
            </tbody>
            <tfoot><tr><td colspan="2" class="num">Total Pasivo</td><td class="num">{{ number_format($resultado['totPas'] ?? 0,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
        <br>
        <div class="table-wrap">
          <table>
            <thead><tr><th colspan="3">Patrimonio</th></tr></thead>
            <tbody>
              @foreach($pat as $x)
                <tr><td>{{ $x->codigo }}</td><td>{{ $x->nombre }}</td><td class="num">{{ number_format(-$x->saldo,2,',','.') }}</td></tr>
              @endforeach
            </tbody>
            <tfoot><tr><td colspan="2" class="num">Total Patrimonio</td><td class="num">{{ number_format($resultado['totPat'] ?? 0,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
        <br>
        <div class="table-wrap">
          <table>
            <tfoot><tr><td class="num">Activo - (Pasivo + Patrimonio)</td><td class="num">{{ number_format($resultado['equilibrio'] ?? 0,2,',','.') }}</td></tr></tfoot>
          </table>
        </div>
      @endif

      {{-- COMPARATIVO --}}
      @if(($resultado['tipo'] ?? '') === 'comparativo')
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Cuenta</th><th>Nombre</th>
                <th>Debe (1)</th><th>Haber (1)</th><th>Saldo (1)</th>
                <th>Debe (2)</th><th>Haber (2)</th><th>Saldo (2)</th>
                <th>Var saldo</th>
              </tr>
            </thead>
            <tbody>
              @foreach(($resultado['rows'] ?? []) as $r)
                <tr>
                  <td>{{ $r['codigo'] }}</td>
                  <td>{{ $r['nombre'] }}</td>
                  <td class="num">{{ number_format($r['debe1'],2,',','.') }}</td>
                  <td class="num">{{ number_format($r['haber1'],2,',','.') }}</td>
                  <td class="num">{{ number_format($r['saldo1'],2,',','.') }}</td>
                  <td class="num">{{ number_format($r['debe2'],2,',','.') }}</td>
                  <td class="num">{{ number_format($r['haber2'],2,',','.') }}</td>
                  <td class="num">{{ number_format($r['saldo2'],2,',','.') }}</td>
                  <td class="num">{{ number_format($r['var_saldo'],2,',','.') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  @endif
@endsection
