@extends('layout')

@section('title', 'Dashboard')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-speedometer2 me-2"></i>
        Dashboard Principal
    </h1>
    <p class="text-muted">Resumen del estado del condominio para {{ Str::ucfirst($periodoActual->translatedFormat('F Y')) }}.</p>
</div>

<div class="row">
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card text-white bg-primary shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold">Total Unidades</h5>
                        <p class="h2 fw-bold">{{ $totalUnidades }}</p>
                    </div>
                    <i class="bi bi-building fs-1 opacity-50"></i>
                </div>
            </div>
            <a href="{{ route('unidades.index') }}" class="card-footer text-white text-decoration-none">
                Ver Detalles <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>

    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card text-white bg-danger shadow-sm h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold">Gastos del Mes</h5>
                        <p class="h2 fw-bold">${{ number_format($totalGastosMes, 0, ',', '.') }}</p>
                    </div>
                    <i class="bi bi-receipt fs-1 opacity-50"></i>
                </div>
            </div>
             <a href="{{ route('gastos.index') }}" class="card-footer text-white text-decoration-none">
                Gestionar Gastos <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>

    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card text-white bg-warning shadow-sm h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold">Cobros Pendientes</h5>
                        <p class="h2 fw-bold">{{ $cobrosPendientes }}</p>
                    </div>
                    <i class="bi bi-exclamation-triangle-fill fs-1 opacity-50"></i>
                </div>
            </div>
             <a href="{{ route('cobros.index') }}" class="card-footer text-white text-decoration-none">
                Revisar Cobros <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>

    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card text-white bg-success shadow-sm h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold">Recaudado del Mes</h5>
                        <p class="h2 fw-bold">${{ number_format($totalRecaudadoMes, 0, ',', '.') }}</p>
                    </div>
                    <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                </div>
            </div>
             <a href="{{ route('cobros.index') }}" class="card-footer text-white text-decoration-none">
                Ver Pagos <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
</div>
@endsection