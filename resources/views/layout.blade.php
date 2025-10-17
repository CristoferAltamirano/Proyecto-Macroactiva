<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Macroactiva')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        html, body { height: 100%; }
        .main-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; flex-shrink: 0; }
        .content { flex-grow: 1; padding: 2rem; overflow-y: auto; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="sidebar d-flex flex-column p-3 text-white bg-dark">
            <a href="{{ route('panel') }}" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <i class="bi bi-shield-lock-fill me-2 fs-4"></i>
                <span class="fs-4">Macroactiva</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item mb-1">
                    <a href="{{ route('panel') }}" class="nav-link text-white {{ request()->routeIs('panel') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="{{ route('unidades.index') }}" class="nav-link text-white {{ request()->routeIs('unidades.*') ? 'active' : '' }}">
                        <i class="bi bi-building me-2"></i> Unidades
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="{{ route('gastos.index') }}" class="nav-link text-white {{ request()->routeIs('gastos.*') ? 'active' : '' }}">
                        <i class="bi bi-receipt me-2"></i> Gastos Comunes
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="{{ route('generacion.index') }}" class="nav-link text-white {{ request()->routeIs('generacion.*') ? 'active' : '' }}">
                        <i class="bi bi-calculator-fill me-2"></i> Generar Cobros
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="{{ route('cobros.index') }}" class="nav-link text-white {{ request()->routeIs('cobros.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-check me-2"></i> Revisión de Cobros
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="#reportes-submenu" data-bs-toggle="collapse" class="nav-link text-white {{ request()->routeIs('reportes.*') ? 'active' : '' }}">
                        <i class="bi bi-graph-up me-2"></i> Reportes
                    </a>
                    <div class="collapse {{ request()->routeIs('reportes.*') ? 'show' : '' }}" id="reportes-submenu">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item">
                                <a href="{{ route('reportes.morosidad') }}" class="nav-link text-white {{ request()->routeIs('reportes.morosidad') ? 'fw-bold' : '' }}">Morosidad</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reportes.gastos') }}" class="nav-link text-white {{ request()->routeIs('reportes.gastos') ? 'fw-bold' : '' }}">Gastos Mensuales</a>
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>
            <hr>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-4 me-2"></i>
                    <strong>{{ Auth::user()->name }}</strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="#">Mi Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
        <div class="content">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>¡Error!</strong> Revisa los problemas en el formulario.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @yield('content')
        </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>