<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'MacroActiva')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* ===== TOKENS ===== */
        :root {
            --primary: #0b2a6f;
            --primary-hover: #092255;
            --accent: #06B6D4;
            --text: #0f172a;
            --muted: #64748b;
            --card: #fff;
            --bg: #fff;
            --border: #E5E7EB;
            --radius: 14px;
            --shadow-xs: 0 1px 2px rgba(2, 6, 23, .06);
            --shadow-sm: 0 4px 14px rgba(2, 6, 23, .08);
            --shadow-md: 0 10px 30px rgba(2, 6, 23, .10);
            --focus: 0 0 0 3px rgba(11, 42, 111, .18);
            --header-h: 72px;
        }

        /* ===== RESET & BASE ===== */
        * { box-sizing: border-box; }
        html, body {
            height: 100%; margin: 0; padding: 0; background: #fff; overflow-x: hidden;
        }
        body {
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--text); background: var(--bg); line-height: 1.6; letter-spacing: .2px;
            -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;
        }

        /* ===== HEADER ===== */
        header {
            background: linear-gradient(90deg, var(--primary), var(--primary-hover));
            color: #fff; padding: 14px 18px; box-shadow: var(--shadow-sm);
        }
        .header-inner {
            max-width: 1100px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap; position: relative;
        }
        .brand {
            font-weight: 800; font-size: 1.125rem; letter-spacing: .3px; display: flex; gap: 10px;
            color: #fff; text-decoration: none;
        }

        /* ===== BOTONES ===== */
        a.btn, button.btn {
            display: inline-flex; justify-content: center; align-items: center; gap: .5rem;
            background: var(--primary); color: #fff; border: 0; padding: 10px 14px; border-radius: 12px;
            cursor: pointer; text-decoration: none; box-shadow: var(--shadow-xs);
            transition: transform .15s, box-shadow .15s, background .15s;
        }
        a.btn:hover, button.btn:hover { background: var(--primary-hover); box-shadow: var(--shadow-sm); }
        .btn--ghost { background: #fff; color: var(--primary); border: 1px solid var(--border); }
        .btn--sm { padding: 8px 12px; font-size: .92rem; border-radius: 10px; }
        .btn--center { display: block; width: 180px; margin: 8px auto 0; }

        /* ===== MAIN & CARD ===== */
        main { max-width: 1100px; margin: 28px auto; padding: 0 16px; }
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow-sm); margin-bottom: 16px; transition: transform .18s, box-shadow .18s, border-color .18s;
            text-align: center;
        }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: #DBE2EA; }
        .muted { color: var(--muted); }
        .pill {
            background: #fff; color: var(--primary); border: 1px solid var(--border);
            padding: 6px 10px; border-radius: 999px; box-shadow: var(--shadow-xs); font-weight: 600;
        }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

        /* ===== HAMBURGUESA ===== */
        .header-left { display: flex; align-items: center; gap: 12px; }
        details.hamb { position: relative; }
        details.hamb>summary {
            list-style: none; cursor: pointer; user-select: none; display: inline-flex; align-items: center; gap: 10px;
            padding: 8px 10px; border-radius: 12px; background: rgba(255,255,255,.10); transition: background .15s;
        }
        details.hamb>summary:hover { background: rgba(255,255,255,.16); }
        details.hamb>summary::-webkit-details-marker { display: none; }
        .hamb-icon { width: 22px; height: 14px; position: relative; display: inline-block; }
        .hamb-icon i { position: absolute; left: 0; right: 0; height: 2px; background: #fff; border-radius: 2px; transition: transform .2s, top .2s, opacity .2s; }
        .hamb-icon i:nth-child(1){ top:0 } .hamb-icon i:nth-child(2){ top:6px } .hamb-icon i:nth-child(3){ top:12px }
        details.hamb[open] .hamb-icon i:nth-child(1){ top:6px; transform:rotate(45deg) }
        details.hamb[open] .hamb-icon i:nth-child(2){ opacity:0 }
        details.hamb[open] .hamb-icon i:nth-child(3){ top:6px; transform:rotate(-45deg) }
        .hamb-menu {
            position: absolute; top: 100%; left: 0; width: 320px; max-width: 92vw; z-index: 60; display: none;
            background: #fff; color: var(--text); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-md);
            padding: 10px; margin-top: 10px;
        }
        details.hamb[open] .hamb-menu { display: block; }
        .hamb-section { padding: 8px; }
        .hamb-title { font-weight: 700; font-size: .9rem; color: #1f2937; margin: 6px 6px 4px; }
        .hamb-list a {
            display: block; padding: 9px 10px; border-radius: 8px; text-decoration: none; color: var(--text);
            transition: background .15s, color .15s; cursor: pointer;
        }
        .hamb-list a:hover { background: #F1F5F9; color: var(--primary); }

        /* ===== MODIFICACIONES MENU ===== */
        details>summary {
            background-color: var(--primary); color: white; padding: 10px; font-size: 1.1rem; font-weight: bold;
            cursor: pointer; border-radius: 10px; transition: background-color .3s ease-in-out;
        }
        details>summary:hover { background-color: var(--primary-hover); }
        details>div { padding: 10px; }
        details[open]>div { display: block; }
        details>div { display: none; }

        @media (max-width:720px){
            .hamb-menu { position: fixed; left: 12px; right: 12px; bottom: 12px; top: auto; width: auto; max-width: none; }
        }

        .field label { font-weight: 600; text-align: left; font-size: 1rem; }
        .field label::after { content: ':'; }
        .checks-row label::after { content: ''; }
        .checks-row label { display: inline-flex; align-items: center; gap: 6px; text-align: left; }

        /* ====== HERO login ====== */
        .auth-hero { position: relative; isolation: isolate; padding: 24px 16px; }
        .auth-hero::before, .auth-hero::after {
            content: ""; position: fixed; left: 0; right: 0; top: var(--header-h); bottom: 0;
        }
        .auth-hero::before { background: center/cover no-repeat var(--login-bg); z-index: -2; }
        .auth-hero::after { background: rgba(255, 255, 255, .82); z-index: -1; }
        main:has(.auth-hero) { margin: 0; padding: 0; }
    </style>
</head>
<body>
<header>
    <div class="header-inner">
        <div class="header-left">
            <details class="hamb">
                <summary aria-label="Abrir menú">
                    <span class="hamb-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="sr-only">Menú</span>
                </summary>
                <div class="hamb-menu" role="menu" aria-label="Navegación principal">
                    @auth
                        @php $role = auth()->user()->rol ?? auth()->user()->tipo_usuario ?? null; @endphp

                        @if (in_array($role, ['super_admin', 'admin']))
                            <div class="hamb-section">
                                <details>
                                    <summary>Comunidad</summary>
                                    <div class="hamb-list">
                                        @if ($role === 'super_admin')
                                            <a href="{{ route('admin.condos.panel') }}">Condominios</a>
                                        @endif
                                        <a href="{{ route('admin.grupos.panel') }}">Grupos</a>
                                        <a href="{{ route('admin.unidades.panel') }}">Unidades</a>
                                        <a href="{{ route('admin.residencias.panel') }}">Residentes</a>
                                        <a href="{{ route('admin.coprop.panel') }}">Copropietarios</a>
                                    </div>
                                </details>
                            </div>

                            <div class="hamb-section">
                                <details>
                                    <summary>Finanzas</summary>
                                    <div class="hamb-list">
                                        <a href="{{ route('pagos.panel') }}">Pagos</a>
                                        <a href="{{ route('admin.cobros.panel') }}">Cobros</a>
                                        <a href="{{ route('admin.prorrateo.panel') }}">Prorrateo</a>
                                        <a href="{{ route('admin.gastos.panel') }}">Gastos</a>
                                        <a href="{{ route('admin.proveedores.panel') }}">Proveedores</a>
                                        <a href="{{ route('cierres.panel') }}">Cierres</a>
                                        <a href="{{ route('admin.conciliacion.panel') }}">Conciliación</a>
                                        <a href="{{ route('admin.export.panel') }}">Exportar</a>
                                        <a href="{{ route('admin.estados.panel') }}">Estados</a>
                                        <a href="{{ route('admin.cargos.panel') }}">Cargos manuales</a>
                                        <a href="{{ route('admin.fr.panel') }}">Fondo de Reserva</a>
                                        <a href="{{ route('admin.audit.panel') }}">Auditoría</a>
                                        <a href="{{ route('admin.reportes.panel') }}">Reportes</a>
                                        <a href="{{ route('admin.libro.panel') }}">Libro de Movimientos</a>
                                        <a href="{{ route('admin.libro.export.csv') }}">Libro (CSV)</a>
                                    </div>
                                </details>
                            </div>

                            <div class="hamb-section">
                                <details>
                                    <summary>Parámetros / RR.HH.</summary>
                                    <div class="hamb-list">
                                        <a href="{{ route('admin.maestros.panel') }}">Reglamento & Interés</a>
                                        @if ($role === 'super_admin')
                                            <a href="{{ route('admin.uac.panel') }}">Asignación Admin↔Condo</a>
                                        @endif
                                        <a href="{{ route('admin.usuarios.panel') }}">Usuarios</a>
                                        <a href="{{ route('admin.trab.panel') }}">Trabajadores</a>
                                        <a href="{{ route('admin.remu.panel') }}">Remuneraciones</a>
                                    </div>
                                </details>
                            </div>
                        @else
                            <div class="hamb-section">
                                <div class="hamb-title">Portal</div>
                                <div class="hamb-list">
                                    <a href="{{ route('mi.cuenta') }}">Mi cuenta</a>
                                    <a href="{{ route('estado.cuenta') }}">Estado de cuenta</a>
                                </div>
                            </div>
                        @endif
                    @endauth
                </div>
            </details>

            <a href="{{ route('panel') }}" class="brand">MacroActiva</a>
        </div>

        {{-- Selector/Indicador de condominio --}}
        @auth
            @php
                $role  = auth()->user()->rol ?? (auth()->user()->tipo_usuario ?? null);
                $ctxId = session('ctx_condo_id');
                $ctxNom= session('ctx_condo_nombre');
            @endphp
            @if ($role === 'super_admin')
                @php
                    $condosTop = \Illuminate\Support\Facades\DB::table('condominio')
                        ->orderBy('nombre')->limit(200)->get();
                @endphp
                <form method="POST" action="{{ route('ctx.condo.set') }}"
                      style="display:flex;gap:8px;align-items:center">
                    @csrf
                    <select name="id_condominio" onchange="this.form.submit()"
                            style="padding:8px;border:0;border-radius:10px">
                        <option value="">(Todos los condominios)</option>
                        @foreach ($condosTop as $c)
                            <option value="{{ $c->id_condominio }}"
                                {{ (int) $ctxId === (int) $c->id_condominio ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @if ($ctxId)
                        <a class="btn btn--ghost" href="{{ route('ctx.condo.set') }}"
                           onclick="event.preventDefault();document.getElementById('ctx-clear').submit()">Limpiar</a>
                    @endif
                </form>
                <form id="ctx-clear" method="POST" action="{{ route('ctx.condo.set') }}" style="display:none">@csrf</form>
            @elseif ($role === 'admin')
                @if ($ctxNom)
                    <span class="pill" title="Condominio activo">{{ $ctxNom }}</span>
                @endif
            @endif
        @endauth

        {{-- Logout por POST (seguro) --}}
        @auth
            <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
            <a href="#" class="btn"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                Salir
            </a>
        @endauth
    </div>
</header>

<main>@yield('content')</main>

<script>
    document.addEventListener('click', (e) => {
        document.querySelectorAll('details[open]').forEach(d => {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('details[open]').forEach(d => d.removeAttribute('open'));
        }
    });
</script>
</body>
</html> 
    