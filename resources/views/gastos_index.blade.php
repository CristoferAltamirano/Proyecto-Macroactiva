@extends('layout')
@section('title', 'Gastos')

@section('content')
    @if (session('ok'))
        <div class="card"><strong>{{ session('ok') }}</strong></div>
    @endif

    {{-- Errores de validación (opcional) --}}
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
        // ===== Contexto/rol
        $yo    = auth()->user();
        $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
        $isSA  = $rol === 'super_admin';
        $ctxId = (int) (session('ctx_condo_id') ?? 0);

        // Normalizamos colecciones llegadas del controller
        $condosCol = collect($condos ?? []);
        $gastosCol = collect($gastos ?? []);

        // Admin: limitar a su condominio activo (ctx)
        if (!$isSA && $ctxId > 0) {
            // combo
            $condosCol = $condosCol->filter(function ($c) use ($ctxId) {
                $id = is_array($c) ? ($c['id_condominio'] ?? null) : ($c->id_condominio ?? null);
                return (int) $id === $ctxId;
            })->values();

            // listado
            $gastosCol = $gastosCol->filter(function ($g) use ($ctxId) {
                // el controller puede traer alias "id_condominio" o "condominio_id"
                $gid = is_array($g) ? ($g['id_condominio'] ?? ($g['condominio_id'] ?? null))
                                    : ($g->id_condominio ?? ($g->condominio_id ?? null));
                return (int) $gid === $ctxId;
            })->values();
        } else {
            // SA ve todo tal cual
            $gastosCol = $gastosCol->values();
        }
    @endphp

    <style>
        /* Grid en 3 columnas */
        .form-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(260px, 1fr));
            gap: 18px 24px;
            margin-top: 8px;
            justify-items: center;
        }
        .pair { display:flex; flex-direction:column; gap:6px; align-items:center; width:100%; }
        .pair label { font-weight: 600; text-align: center; }
        .control{
            width: 100%; max-width: 280px; padding: 6px 8px;
            border: 1px solid #e5e7eb; border-radius: 10px; text-align: center;
            background: #fff; color: #0f172a; outline: 0;
            transition: border-color .15s, box-shadow .15s; box-shadow: var(--shadow-xs);
        }
        .control:focus{ border-color: var(--accent); box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        select.control{ text-align-last: center; }
        .actions{ grid-column: 1 / -1; display:flex; justify-content:center; margin-top:8px; }
        h3{ text-align:center; }
        .muted{ color:#64748B; text-align:center; }

        .table-wrap{ width:100%; overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow:var(--shadow-xs); }
        .table-flat{ width:100%; border-collapse:collapse; }
        .table-flat thead th, .table-flat tbody td{
            text-align:center; padding:12px 14px; border-bottom:1px solid #e5e7eb; white-space:nowrap;
        }
        .table-flat thead th{
            position:sticky; top:0; z-index:1; background:#f8fafc; color:#475569; font-weight:700;
            text-transform:uppercase; letter-spacing:.02em; font-size:.9rem;
        }
        .table-flat tbody tr:hover{ background:#f9fafb; }

        @media (max-width: 1024px){ .form-3{ grid-template-columns: repeat(2, minmax(260px, 1fr)); } }
        @media (max-width: 640px){
            .form-3{ grid-template-columns: 1fr; }
            .control{ max-width: 100%; }
            .table-flat thead th, .table-flat tbody td{ padding:10px 12px; }
        }
    </style>

    <div class="card">
        <h3>Registrar gasto</h3><br>
        <form method="POST" action="{{ route('admin.gastos.store') }}" class="form-3">
            @csrf

            <div class="pair">
                <label for="id_condominio">Condominio</label>
                <select id="id_condominio" name="id_condominio" required class="control">
                    @forelse ($condosCol as $c)
                        <option value="{{ is_array($c) ? $c['id_condominio'] : $c->id_condominio }}">
                            {{ is_array($c) ? ($c['nombre'] ?? ('#'.$c['id_condominio'])) : ($c->nombre ?? ('#'.$c->id_condominio)) }}
                        </option>
                    @empty
                        <option value="" disabled selected>(sin condominio)</option>
                    @endforelse
                </select>
            </div>

            <div class="pair">
                <label for="periodo">Periodo (AAAAMM)</label>
                <input id="periodo" name="periodo" pattern="[0-9]{6}" title="Formato: 6 dígitos (AAAAMM)" required
                    class="control" inputmode="numeric" placeholder="Ej: 202509">
            </div>

            <div class="pair">
                <label for="id_gasto_categ">Categoría</label>
                <select id="id_gasto_categ" name="id_gasto_categ" required class="control">
                    @foreach ($cats ?? [] as $c)
                        <option value="{{ is_array($c) ? $c['id_gasto_categ'] : $c->id_gasto_categ }}">
                            {{ is_array($c) ? ($c['nombre'] ?? ('#'.$c['id_gasto_categ'])) : ($c->nombre ?? ('#'.$c->id_gasto_categ)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pair">
                <label for="id_proveedor">Proveedor</label>
                <select id="id_proveedor" name="id_proveedor" class="control">
                    <option value="">(sin proveedor)</option>
                    @foreach ($provs ?? [] as $p)
                        <option value="{{ is_array($p) ? $p['id_proveedor'] : $p->id_proveedor }}">
                            {{ is_array($p) ? ($p['nombre'] ?? ('#'.$p['id_proveedor'])) : ($p->nombre ?? ('#'.$p->id_proveedor)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pair">
                <label for="id_doc_tipo">Tipo doc</label>
                <select id="id_doc_tipo" name="id_doc_tipo" class="control">
                    <option value="">(ninguno)</option>
                    @foreach ($docs ?? [] as $d)
                        <option value="{{ is_array($d) ? $d['id_doc_tipo'] : $d->id_doc_tipo }}">
                            {{ is_array($d) ? ($d['codigo'] ?? ('#'.$d['id_doc_tipo'])) : ($d->codigo ?? ('#'.$d->id_doc_tipo)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pair">
                <label for="documento_folio">Folio</label>
                <input id="documento_folio" name="documento_folio" class="control" placeholder="Ej: 123456">
            </div>

            <div class="pair">
                <label for="fecha_emision">Emisión</label>
                <input id="fecha_emision" type="date" name="fecha_emision" class="control">
            </div>

            <div class="pair">
                <label for="fecha_venc">Vencimiento</label>
                <input id="fecha_venc" type="date" name="fecha_venc" class="control">
            </div>

            <div class="pair">
                <label for="neto">Neto</label>
                <input id="neto" type="number" step="0.01" name="neto" required class="control"
                    inputmode="decimal" placeholder="0">
            </div>

            <div class="pair">
                <label for="iva">IVA</label>
                <input id="iva" type="number" step="0.01" name="iva" value="0" required class="control"
                    inputmode="decimal" placeholder="0">
            </div>

            <div class="pair">
                <label for="total">Total</label>
                <input id="total" type="number" step="0.01" name="total" value="0" class="control" inputmode="decimal"
                       placeholder="0" readonly>
            </div>

            <div class="pair">
                <label for="descripcion">Descripción</label>
                <input id="descripcion" name="descripcion" class="control" placeholder="Glosa del gasto">
            </div>

            <div class="pair">
                <label for="evidencia_url">Evidencia URL</label>
                <input id="evidencia_url" name="evidencia_url" type="url" class="control" placeholder="https://...">
            </div>

            <div class="actions">
                <button class="btn">Guardar</button>
            </div>
        </form> <br>
    </div>

    <div class="card">
        <h3>Listado últimos 50 gastos</h3><br>
        <div class="table-wrap">
            <table class="table table-flat">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Condominio</th>
                        <th>Periodo</th>
                        <th>Proveedor</th>
                        <th>Categoría</th>
                        <th>Neto</th>
                        <th>IVA</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gastosCol as $g)
                        <tr>
                            <td>{{ is_array($g) ? $g['id_gasto'] : $g->id_gasto }}</td>
                            <td>{{ is_array($g) ? ($g['condominio'] ?? '') : ($g->condominio ?? '') }}</td>
                            <td>{{ is_array($g) ? ($g['periodo'] ?? '') : ($g->periodo ?? '') }}</td>
                            <td>{{ is_array($g) ? ($g['proveedor'] ?? '') : ($g->proveedor ?? '') }}</td>
                            <td>{{ is_array($g) ? ($g['categoria'] ?? '') : ($g->categoria ?? '') }}</td>
                            <td>${{ number_format(is_array($g) ? ($g['neto'] ?? 0) : ($g->neto ?? 0), 0, ',', '.') }}</td>
                            <td>${{ number_format(is_array($g) ? ($g['iva'] ?? 0) : ($g->iva ?? 0), 0, ',', '.') }}</td>
                            <td><strong>${{ number_format(is_array($g) ? ($g['total'] ?? 0) : ($g->total ?? 0), 0, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div><br>
    </div>

    {{-- === Autocálculo IVA/Total (19% por defecto, 0% si "Tipo doc" contiene "exent") === --}}
    <script>
    (function(){
      const DEFAULT_IVA = 0.19; // Chile
      const $ = s => document.querySelector(s);
      const netoEl    = $('#neto');
      const ivaEl     = $('#iva');
      const totalEl   = $('#total');
      const tipoDocEl = $('#id_doc_tipo');

      if(!netoEl || !ivaEl) return;

      const parseNum = (v) => {
        if(v == null) return 0;
        v = String(v).replace(/[^\d,.\-]/g,'').replace(/\./g,'').replace(',','.');
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
      };
      const roundPeso = (n) => Math.round((+n + Number.EPSILON));

      function tasaActual(){
        if (tipoDocEl) {
          const opt = tipoDocEl.options[tipoDocEl.selectedIndex] || {};
          const txt = (opt.text || opt.textContent || '').toLowerCase();
          if (txt.includes('exent')) return 0; // exento
        }
        return DEFAULT_IVA;
      }

      function recalcular(){
        const neto = parseNum(netoEl.value);
        const tasa = tasaActual();
        const iva  = roundPeso(neto * tasa);
        ivaEl.value = iva;
        if (totalEl) totalEl.value = roundPeso(neto + iva);
      }

      ['input','change','blur'].forEach(ev => netoEl.addEventListener(ev, recalcular));
      if (tipoDocEl) tipoDocEl.addEventListener('change', recalcular);
      recalcular();
    })();
    </script>
@endsection
