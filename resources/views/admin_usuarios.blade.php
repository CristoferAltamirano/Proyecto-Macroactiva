@extends('layout')
@section('title','Usuarios')

@section('content')
@include('partials.flash')

@php
  $yo            = auth()->user();
  $tipoYo        = $yo->tipo_usuario ?? ($yo->rol ?? '');
  $soySA         = $tipoYo === 'super_admin';

  $lista         = ($usuarios ?? null) ?: (($users ?? null) ?: collect());
  $condosCombo   = ($condosCombo ?? null) ?: (($condos ?? null) ?: collect());

  $idCondo       = (int) (($idCondo ?? null) ?: request('id_condominio'));
  $ctxCondo      = $condosCombo->firstWhere('id_condominio', $idCondo);
  $ctxNombre     = $ctxCondo->nombre ?? null;

  $crearDeshab   = (!$soySA && !$idCondo);

  $linkResid = \Illuminate\Support\Facades\Route::has('residencias.index')
                 ? route('residencias.index')
                 : (\Illuminate\Support\Facades\Route::has('admin.residencias.panel')
                       ? route('admin.residencias.panel')
                       : null);

  $newId = (int)($newId ?? (session('new_user_id') ?? 0));
@endphp

<style>
  /* ====== Contenedor de tarjetas ====== */
  .cards-vert{ display:grid; grid-template-columns:1fr; gap:24px; }

  /* Centramos visualmente cada card */
  .cards-vert .card{
    display:flex; flex-direction:column; align-items:center; text-align:center; gap:12px;
  }
  .cards-vert .card h3{ margin:0 0 6px; text-align:center; }

  .muted{ color:#64748b; }

  /* ====== Formulario centrado ====== */
  .form-grid{
    display:grid; grid-template-columns: repeat(2, minmax(260px, 1fr));
    gap:16px 18px; align-items:start; justify-items:center;
    width:100%; max-width:900px; margin:0 auto 6px;
  }
  .pair{ display:flex; flex-direction:column; gap:6px; align-items:center; width:100%; }
  .pair.full{ grid-column:1 / -1; }
  .pair label{ font-weight:600; margin:0; text-align:center; width:100%; }

  .control{
    width:100%; max-width:360px; padding:10px 12px;
    border:1px solid #e5e7eb; border-radius:10px; font-size:1rem; height:42px;
  }
  .control[multiple]{ min-height:180px; height:auto; }

  .help{ font-size:.88rem; color:#6b7280; }
  .help.center{ text-align:center; width:100%; }
  .help.spacer{ visibility:hidden; }           /* espaciador "fantasma" */

  /* ===== Fila compacta: Tipo + RUT + DV ===== */
  .row-3{
    --label-h: 24px;                            /* altura fija de labels en desktop */
    --help-gap: 6px;                            /* separación estándar sobre help */
    display:grid;
    grid-template-columns: minmax(240px,1fr) minmax(240px,1fr) 120px; /* Tipo | RUT | DV */
    gap:12px 14px;
    align-items:stretch;
    justify-items:center;
    width:100%; max-width:900px; margin:0 auto 4px;
  }
  /* Cada columna usa 3 filas: label | input | help  */
  .row-3 .pair{
    display:grid;
    grid-template-rows: var(--label-h) 42px auto; /* inputs siempre a la MISMA altura */
    width:100%;
  }
  .row-3 .pair label{
    display:flex; align-items:flex-end; justify-content:center;
    margin:0;
  }
  .row-3 .pair .control{ width:100%; max-width:360px; height:42px; }
  .row-3 .pair .control.dv{ max-width:120px; text-align:center; }
  .row-3 .help{ margin-top: var(--help-gap); }
  .row-3 .help.spacer{ margin-top: var(--help-gap); min-height: 18px; } /* mismo alto base que el help real */

  /* Evitamos quiebres en desktop para que "Tipo de usuario" no baje el input */
  @media (min-width: 901px){
    .row-3 .pair label{ white-space:nowrap; }
  }

  .actions{
    display:flex; justify-content:center; gap:8px; flex-wrap:wrap; width:100%;
  }

  /* ====== Tablas ====== */
  .table-wrap{ width:100%; max-width:1000px; margin:0 auto; overflow:auto; }
  table{ width:100%; border-collapse:collapse; }
  thead th{ text-align:left; padding:10px 12px; border-bottom:1px solid #e5e7eb; white-space:nowrap; }
  tbody td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; vertical-align:top; text-align:left; }
  .nowrap{ white-space:nowrap; }

  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:center; }

  .dd > summary{ cursor:pointer; list-style:none; }
  .dd > summary::-webkit-details-marker{ display:none; }
  .dd-menu{
    padding:14px; min-width:360px; border:1px solid #e5e7eb; border-radius:12px;
    background:#fff; box-shadow:0 10px 28px rgba(2,6,23,.12); text-align:left;
  }
  .mini-form{ display:grid; grid-template-columns: repeat(2, minmax(220px,1fr)); gap:12px 14px; align-items:start; }
  .mini-form .control{ width:100%; max-width:340px; }

  .alert-soft{ background:#fff8e1; border:1px solid #fde68a; color:#7c5800; padding:10px 12px; border-radius:10px; }
  .error-box{ background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:10px 12px; border-radius:10px; max-width:900px; margin:0 auto; }

  .badge{ display:inline-block; padding:.15rem .5rem; border-radius:.5rem; font-size:.75rem; }
  .badge-warn{ background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
  .badge-ok{ background:#ecfdf5; color:#065f46; border:1px solid #b7f7d0; }

  .tr-new{ background:#f0fdf4; }

  /* Responsive */
  @media (max-width: 900px){
    .form-grid{ grid-template-columns: 1fr; }
    .row-3{ grid-template-columns: 1fr; }
    .row-3 .pair .control.dv{ max-width:140px; }
    /* Dejamos que los labels puedan partir línea en móvil */
    .row-3 .pair label{ white-space:normal; }
  }
</style>

<div class="cards-vert">
  {{-- ========================== NUEVO USUARIO ========================== --}}
  <div class="card">
    <h3>Nuevo usuario</h3>

    @if(!$soySA && !$idCondo)
      <div class="alert-soft">No tienes un <strong>condominio activo</strong>. Pide al super admin que te asigne o active uno.</div>
    @endif
    <div class="muted">
      Desde aquí solo creas el usuario y su rol. La vinculación a una unidad se realiza en
      @if($linkResid)
        <a href="{{ $linkResid }}"><strong>Residencias</strong></a>.
      @else
        <strong>Residencias</strong>.
      @endif
    </div>

    {{-- Errores --}}
    @if ($errors->any())
      <div class="error-box" role="alert">
        <strong>Revisa los siguientes errores:</strong>
        <ul style="margin:6px 0 0 18px; text-align:left;">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.usuarios.store') }}" autocomplete="off" novalidate style="width:100%;">
      @csrf
      @unless($soySA)
        <input type="hidden" name="id_condominio" value="{{ $idCondo }}">
      @endunless

      <div class="form-grid">

        {{-- ===== Fila: Tipo + RUT + DV (nivelados) ===== --}}
        <div class="pair full" style="margin-bottom:6px">
          <div class="row-3">

            {{-- Col 1: Tipo --}}
            <div class="pair">
              <label for="tipo">Tipo de usuario</label>
              <select id="tipo" name="tipo_usuario" class="control" required>
                <option value="residente"     {{ old('tipo_usuario')==='residente'     ? 'selected':'' }}>Residente</option>
                <option value="copropietario" {{ old('tipo_usuario')==='copropietario' ? 'selected':'' }}>Copropietario</option>
                <option value="admin"         {{ old('tipo_usuario')==='admin'         ? 'selected':'' }}>Admin</option>
                @if($soySA)
                  <option value="super_admin" {{ old('tipo_usuario')==='super_admin'   ? 'selected':'' }}>Super Admin</option>
                @endif
              </select>
              <div class="help spacer"></div>
            </div>

            {{-- Col 2: RUT (sin DV) --}}
            <div class="pair">
              <label for="rutBase">RUT</label>
              <input
                id="rutBase" class="control" name="rut" placeholder="RUT (sin DV)"
                value="{{ old('rut') }}" required inputmode="numeric"
                pattern="^[0-9]{6,9}$" title="Solo números, entre 6 y 9 dígitos (sin puntos ni guion)."
                oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              <div class="help center">Ej: 12345678-<strong>K</strong> ⇒ escribe <em>12345678</em> y <em>K</em>.</div>
            </div>

            {{-- Col 3: DV --}}
            <div class="pair">
              <label for="rutDv">DV</label>
              <input
                id="rutDv" class="control dv" name="dv" placeholder="DV"
                value="{{ old('dv') }}" required maxlength="1"
                pattern="^[0-9Kk]{1}$" title="Dígito verificador (0-9 o K)."
                oninput="this.value=this.value.replace(/[^0-9Kk]/g,'').toUpperCase()">
              <div class="help spacer"></div>
            </div>

          </div>
        </div>

        {{-- Datos personales --}}
        <div class="pair">
          <label for="nombres">Nombres</label>
          <input id="nombres" class="control" name="nombres" placeholder="Nombres"
                 value="{{ old('nombres') }}" required maxlength="80">
        </div>

        <div class="pair">
          <label for="apellidos">Apellidos</label>
          <input id="apellidos" class="control" name="apellidos" placeholder="Apellidos"
                 value="{{ old('apellidos') }}" required maxlength="80">
        </div>

        <div class="pair">
          <label for="email">Email</label>
          <input id="email" class="control" name="email" type="email" placeholder="Email"
                 value="{{ old('email') }}" required maxlength="120">
        </div>

        <div class="pair">
          <label for="tel">Teléfono</label>
          <input id="tel" class="control" name="telefono" placeholder="Teléfono"
                 value="{{ old('telefono') }}" maxlength="30"
                 pattern="^[0-9\+\-\s\(\)]{6,30}$" title="Usa números y signos comunes (+ - () ).">
        </div>

        <div class="pair">
          <label for="dir">Dirección</label>
          <input id="dir" class="control" name="direccion" placeholder="Dirección"
                 value="{{ old('direccion') }}" maxlength="150">
        </div>

        <div class="pair">
          <label for="pass">Password</label>
          <input id="pass" class="control" name="password" type="password" placeholder="Password"
                 minlength="6" required autocomplete="new-password">
        </div>

        {{-- Condominios (solo SA) --}}
        @if($soySA)
          <div class="pair full">
            <label><strong>Condominios (solo aplica a Admin)</strong></label>
            <select name="id_condominios[]" class="control" multiple>
              @foreach($condosCombo as $c)
                <option value="{{ $c->id_condominio }}"
                        @if(collect(old('id_condominios',[]))->contains($c->id_condominio)) selected @endif>
                  {{ $c->nombre }}
                </option>
              @endforeach
            </select>
            <div class="help">Mantén CTRL/⌘ para seleccionar varios.</div>
          </div>
        @else
          <div class="pair full">
            <div class="muted"><strong>Condominio activo:</strong> {{ $ctxNombre ?? '(no hay contexto definido)' }}</div>
          </div>
        @endif
      </div>

      <div class="actions">
        <button class="btn" {{ $crearDeshab ? 'disabled' : '' }}>Crear</button>
      </div>
    </form>
  </div>

  {{-- ============================ LISTADO ============================ --}}
  <div class="card">
    <h3>Usuarios</h3>

    {{-- Filtro por condominio --}}
    @if($soySA || ($condosCombo && $condosCombo->count() > 1))
      <form method="GET" action="{{ route('admin.usuarios.panel') }}" class="actions">
        <label for="f_id_condominio" class="inline-label">Condominio</label>
        <select id="f_id_condominio" name="id_condominio" class="control" style="max-width:360px">
          <option value="">(Todos)</option>
          @foreach ($condosCombo as $c)
            <option value="{{ $c->id_condominio }}" @selected((int)$idCondo === (int)$c->id_condominio)>
              {{ $c->nombre }}
            </option>
          @endforeach
        </select>
        <button class="btn">Ver</button>
      </form>
    @endif

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Tipo</th>
            <th>Activo</th>
            <th class="nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($lista as $u)
            @php
              $tipoU  = $u->tipo_usuario ?? ($u->rol ?? ($u->role ?? $u->tipo ?? ''));
              $esSAu  = ($tipoU === 'super_admin');
              if(!$soySA && $esSAu){ continue; }

              $pk     = $u->id_usuario ?? ($u->id ?? $u->user_id ?? null);
              $nombre = trim(($u->nombres ?? $u->nombre ?? $u->name ?? '').' '.($u->apellidos ?? ''));
              $email  = $u->email ?? $u->correo ?? $u->mail ?? '';
              $activo = (int)($u->activo ?? $u->is_active ?? $u->enabled ?? $u->estado ?? $u->status ?? 0);
              $soyYo  = ($yo && ($yo->id_usuario ?? $yo->id ?? null) == $pk);
            @endphp
            <tr class="{{ $newId && $newId == $pk ? 'tr-new' : '' }}">
              <td>{{ $pk }}</td>
              <td>{{ $nombre }}</td>
              <td>{{ $email }}</td>
              <td>{{ $tipoU }}</td>
              <td>{{ $activo ? 'Sí':'No' }}</td>
              <td>
                <div class="row-actions">
                  {{-- Editar inline --}}
                  <details class="dd">
                    <summary class="btn">Editar</summary>
                    <div class="dd-menu">
                      <form method="POST" action="{{ route('admin.usuarios.update', $pk) }}" autocomplete="off" novalidate>
                        @csrf
                        @unless($soySA)
                          <input type="hidden" name="id_condominio" value="{{ $idCondo }}">
                        @endunless
                        <div class="mini-form">
                          <div>
                            <label class="form-label">Tipo</label>
                            <select name="tipo_usuario" class="control" required {{ (!$soySA && $esSAu) ? 'disabled' : '' }}>
                              <option value="residente"     @selected($tipoU==='residente')>Residente</option>
                              <option value="copropietario" @selected($tipoU==='copropietario')>Copropietario</option>
                              <option value="admin"         @selected($tipoU==='admin')>Admin</option>
                              @if($soySA)
                                <option value="super_admin" @selected($tipoU==='super_admin')>Super Admin</option>
                              @endif
                            </select>
                          </div>
                          <div>
                            <label class="form-label">Nombres</label>
                            <input class="control" name="nombres" value="{{ $u->nombres ?? $u->nombre ?? $u->name ?? '' }}" required maxlength="80">
                          </div>
                          <div>
                            <label class="form-label">Apellidos</label>
                            <input class="control" name="apellidos" value="{{ $u->apellidos ?? '' }}" required maxlength="80">
                          </div>
                          <div>
                            <label class="form-label">Teléfono</label>
                            <input class="control" name="telefono" value="{{ $u->telefono ?? $u->fono ?? $u->phone ?? '' }}"
                                   maxlength="30" pattern="^[0-9\+\-\s\(\)]{6,30}$">
                          </div>
                          <div>
                            <label class="form-label">Dirección</label>
                            <input class="control" name="direccion" value="{{ $u->direccion ?? $u->address ?? '' }}" maxlength="150">
                          </div>
                          <div style="grid-column:1/-1">
                            <label class="form-label">Nueva clave (opcional)</label>
                            <input class="control" name="password" type="password" minlength="6" placeholder="Dejar en blanco para no cambiar">
                          </div>
                        </div>
                        <div class="actions">
                          <button class="btn" {{ (!$soySA && $esSAu) ? 'disabled' : '' }}>Guardar</button>
                        </div>
                      </form>
                    </div>
                  </details>

                  {{-- Reset clave --}}
                  <form method="POST" action="{{ route('admin.usuarios.reset',$pk) }}"
                        onsubmit="return confirm('Resetear contraseña para {{ $email }}?');" autocomplete="off">
                    @csrf
                    @unless($soySA)
                      <input type="hidden" name="id_condominio" value="{{ $idCondo }}">
                    @endunless
                    <button class="btn" {{ (!$soySA && $esSAu) ? 'disabled' : '' }}>Reset clave</button>
                  </form>

                  {{-- Activar / Desactivar --}}
                  <form method="POST" action="{{ route('admin.usuarios.toggle',$pk) }}"
                        onsubmit="return confirm('¿Seguro que deseas {{ $activo ? 'desactivar':'activar' }} a {{ $nombre }}?');" autocomplete="off">
                    @csrf
                    @unless($soySA)
                      <input type="hidden" name="id_condominio" value="{{ $idCondo }}">
                    @endunless
                    <button class="btn" {{ ($soyYo || (!$soySA && $esSAu)) ? 'disabled' : '' }}>
                      {{ $activo ? 'Desactivar':'Activar' }}
                    </button>
                  </form>

                  {{-- Eliminar --}}
                  <form method="POST" action="{{ route('admin.usuarios.delete',$pk) }}"
                        onsubmit="return confirm('¿Eliminar definitivamente a {{ $nombre }}?');" autocomplete="off">
                    @csrf
                    @unless($soySA)
                      <input type="hidden" name="id_condominio" value="{{ $idCondo }}">
                    @endunless
                    <button class="btn btn--ghost" {{ ($soyYo || (!$soySA && $esSAu)) ? 'disabled' : '' }}>
                      Eliminar
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted" style="text-align:center">Sin usuarios.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- ===================== PENDIENTES DE VINCULAR ===================== --}}
  <div class="card">
    <h3>
      Pendientes de vincular
      <span class="badge badge-warn">Residentes/Copropietarios sin unidad</span>
    </h3>
    <div class="muted">
      Estos usuarios existen pero aún no tienen una residencia asociada.
      @if($linkResid)
        Vincúlalos en <a href="{{ $linkResid }}"><strong>Residencias</strong></a>.
      @else
        Vincúlalos en <strong>Residencias</strong>.
      @endif
    </div>

    <div class="table-wrap">
      <table>
        <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Email</th>
          <th>Tipo</th>
          <th class="nowrap">Acción</th>
        </tr>
        </thead>
        <tbody>
        @forelse(($pendientes ?? collect()) as $p)
          @php
            $pid    = $p->id_usuario ?? $p->id ?? null;
            $pn     = trim(($p->nombres ?? '').' '.($p->apellidos ?? ''));
            $pemail = $p->email ?? '';
            $ptipo  = $p->tipo_usuario ?? '';
          @endphp
          <tr class="{{ $newId && $newId == $pid ? 'tr-new' : '' }}">
            <td>{{ $pid }}</td>
            <td>{{ $pn }}</td>
            <td>{{ $pemail }}</td>
            <td>{{ $ptipo }}</td>
            <td class="nowrap">
              @if($linkResid)
                <a class="btn" href="{{ $linkResid }}#u{{ $pid }}">Vincular</a>
              @else
                <span class="muted">Abrir módulo Residencias</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted" style="text-align:center">No hay pendientes de vincular.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- ========================= RECIENTES ========================= --}}
  <div class="card">
    <h3>
      Últimos creados
      <span class="badge badge-ok">para confirmar el alta</span>
    </h3>
    <div class="table-wrap">
      <table>
        <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Email</th>
          <th>Tipo</th>
        </tr>
        </thead>
        <tbody>
        @forelse(($recientes ?? collect()) as $r)
          @php
            $rid = $r->id_usuario ?? $r->id ?? null;
            $rn  = trim(($r->nombres ?? '').' '.($r->apellidos ?? ''));
          @endphp
          <tr class="{{ $newId && $newId == $rid ? 'tr-new' : '' }}">
            <td>{{ $rid }}</td>
            <td>{{ $rn }}</td>
            <td>{{ $r->email ?? '' }}</td>
            <td>{{ $r->tipo_usuario ?? '' }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="muted" style="text-align:center">Sin datos.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  /* Normalización RUT */
  (function () {
    const base = document.getElementById('rutBase');
    const dv   = document.getElementById('rutDv');
    if(!base || !dv) return;
    base.addEventListener('blur', () => { base.value = (base.value || '').replace(/[^0-9]/g,''); });
    dv.addEventListener('blur', () => { dv.value   = (dv.value   || '').replace(/[^0-9Kk]/g,'').toUpperCase(); });
  })();
</script>
@endsection
