@extends('layout')
@section('title','Exportar CSV')
@section('content')

<style>
  .grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-top: 16px;
  }

  .card h3, .card p { text-align: center; }

  .form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    justify-items: center;
    margin-top: 12px;
  }

  .pair {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }

  .pair label {
    font-weight: 600;
    text-align: center;
  }

  .control {
    width: 100%;
    max-width: 260px;
    padding: 8px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
  }

  .actions {
    display: flex;
    justify-content: center;
    margin-top: 12px;
  }

  @media (max-width: 768px) {
    .grid-2 {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="grid-2">
  <!-- Exportar Cobros -->
  <div class="card">
    <h3>Exportar COBROS (CSV)</h3><br>
    <form method="POST" action="{{ route('admin.export.cobros.csv') }}" class="form-grid">
      @csrf
      <div class="pair">
        <label for="periodo_cobros">Periodo (AAAAMM):</label>
        <input id="periodo_cobros" name="periodo" pattern="[0-9]{6}" required class="control">
      </div>
      <div class="pair">
        <label for="condo_cobros">ID Condominio (opcional):</label>
        <input id="condo_cobros" name="id_condominio" type="number" class="control">
      </div>
      <div class="actions">
        <button class="btn">Descargar</button>
      </div>
    </form><br>
  </div>

  <!-- Exportar Pagos -->
  <div class="card">
    <h3>Exportar PAGOS (CSV)</h3><br>
    <form method="POST" action="{{ route('admin.export.pagos.csv') }}" class="form-grid">
      @csrf
      <div class="pair">
        <label for="periodo_pagos">Periodo (AAAAMM):</label>
        <input id="periodo_pagos" name="periodo" pattern="[0-9]{6}" required class="control">
      </div>
      <div class="pair">
        <label for="condo_pagos">ID Condominio (opcional):</label>
        <input id="condo_pagos" name="id_condominio" type="number" class="control">
      </div>
      <div class="actions">
        <button class="btn">Descargar</button>
      </div>
    </form><br>
  </div>
</div>
@endsection
