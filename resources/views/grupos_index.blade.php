@extends('layout')
@section('title', 'Grupos')

@section('content')
    @if (session('ok'))
        <div class="card"><strong>{{ session('ok') }}</strong></div>
    @endif
    @if (session('err'))
        <div class="card" style="background:#fff3f3;border:1px solid #fecaca;color:#991b1b">
            {{ session('err') }}
        </div>
    @endif

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
        $role    = auth()->user()->rol ?? (auth()->user()->tipo_usuario ?? null);
        $isSuper = $role === 'super_admin';
    @endphp

    <style>
        .control{width:260px;max-width:100%;padding:8px;font-size:.92rem;border:1px solid #e5e7eb;border-radius:10px;text-align:center;box-shadow:var(--shadow-xs);outline:none;}
        .control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(6,182,212,.15);}
        select.control{text-align-last:center;}
        .form-field{display:flex;flex-direction:column;align-items:center;gap:6px;}
        .form-field label{font-weight:600;text-align:center;}
        .form-row-center{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:12px;}
        .form-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px 24px;margin-top:14px;max-width:980px;margin-left:auto;margin-right:auto;}
        .col-span-3{grid-column:1 / -1;}
        .centered{display:flex;justify-content:center;}
        h3{text-align:center;}
        .table-wrap{width:100%;overflow:auto;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);box-shadow:var(--shadow-xs);}
        table{width:100%;border-collapse:collapse;}
        thead th, tbody td{text-align:center;padding:10px 12px;border-bottom:1px solid var(--border);white-space:nowrap;}
        thead th{position:sticky;top:0;background:#f8fafc;z-index:1;}
        tbody tr:hover{background:#f9fafb;}
        @media (max-width:960px){.form-grid-3{grid-template-columns:1fr;}.col-span-3{grid-column:auto;}.control{width:100%;}}
    </style>

    {{-- ====== PANEL: Filtro por condominio (SOLO super_admin) ====== --}}
    @if ($isSuper)
    <div class="card">
        <h3>Filtrar por condominio</h3>
        <form method="GET" action="{{ route('admin.grupos.panel') }}">
            <div class="form-row-center" style="margin-top:10px">
                <label for="f_id_condominio" style="font-weight:600;">Selecciona un condominio:</label>
                <select id="f_id_condominio" name="id_condominio" class="control">
                    @foreach ($condos as $c)
                        <option value="{{ $c->id_condominio }}" @selected((int)$idCondo === (int)$c->id_condominio)>{{ $c->nombre }}</option>
                    @endforeach
                </select>
                <button class="btn">Ver</button>
            </div>
        </form><br>
    </div>
    @endif

    {{-- ====== FORM NUEVO / EDITAR ====== --}}
    <div class="card">
        <h3>Nuevo grupo</h3>
        <form method="POST" action="{{ route('admin.grupos.store') }}" class="form-grid-3" id="frmGrupo">
            @csrf
            <input type="hidden" name="id_grupo" id="id_grupo" value="{{ old('id_grupo', '') }}">

            {{-- Condominio: visible solo para super_admin; admin lo envía oculto --}}
            @if ($isSuper)
                <div class="form-field">
                    <label for="g_id_condominio">Condominio</label>
                    <select id="g_id_condominio" name="id_condominio" required class="control">
                        @foreach ($condos as $c)
                            <option value="{{ $c->id_condominio }}" @selected((int)$idCondo === (int)$c->id_condominio)>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" id="g_id_condominio" name="id_condominio" value="{{ (int)$idCondo }}">
            @endif

            <div class="form-field">
                <label for="g_nombre">Nombre</label>
                <input id="g_nombre" name="nombre" required class="control" value="{{ old('nombre', '') }}" placeholder="Ej: Torre A">
            </div>

            <div class="form-field">
                <label for="g_tipo">Tipo</label>
                <input id="g_tipo" name="tipo" value="{{ old('tipo', 'torre') }}" required class="control" placeholder="torre / pasaje / block">
            </div>

            <div class="col-span-3 centered" style="gap:10px;margin-top:6px">
                <button type="button" class="btn" onclick="resetForm()">Nuevo</button>
                <button class="btn">Guardar</button>
            </div>
        </form><br>
    </div>

    {{-- ====== LISTADO ====== --}}
    <div class="card">
        <h3>Listado Grupos</h3>
        <div class="table-wrap" style="margin-top:10px">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grupos as $g)
                        <tr>
                            <td>{{ $g->id_grupo }}</td>
                            <td>{{ $g->nombre }}</td>
                            <td>{{ $g->tipo }}</td>
                            <td>
                                <button class="btn" type="button" onclick='fillForm(@json($g))'>Editar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div><br>
    </div>

    <script>
        function resetForm() {
            const f = document.getElementById('frmGrupo');
            f.reset();
            document.getElementById('id_grupo').value = '';

            @if ($isSuper)
                // re-seleccionar condominio del filtro para el form
                const selFiltro = document.getElementById('f_id_condominio');
                const selForm   = document.getElementById('g_id_condominio');
                if (selFiltro && selForm) selForm.value = selFiltro.value;
            @endif

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function fillForm(g) {
            document.getElementById('id_grupo').value = g.id_grupo ?? '';

            const map = {
                @if ($isSuper) id_condominio: g.id_condominio, @endif
                nombre: g.nombre,
                tipo: g.tipo
            };
            Object.entries(map).forEach(([k, v]) => {
                const el = document.querySelector('[name="' + k + '"]');
                if (!el) return;
                el.value = v ?? '';
            });

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
@endsection
