@extends('layout')
@section('title','Plan de Cuentas')
@section('content')
@include('partials.flash')

<div class="card">
  <h3>Plan de Cuentas</h3>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" action="{{ route('admin.cuentas.store') }}" style="display:flex;gap:8px;flex-wrap:wrap">
      @csrf
      <input type="hidden" name="id_cta_contable" id="edit_id">
      <input type="text" name="codigo" id="edit_codigo" placeholder="Código (ej: 1101)" required>
      <input type="text" name="nombre" id="edit_nombre" placeholder="Nombre" required>
      <button class="btn">Guardar</button>
    </form>

    <form method="POST" action="{{ route('admin.cuentas.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap">
      @csrf
      <input type="file" name="archivo" accept=".csv,text/csv" required>
      <select name="modo" required>
        <option value="insert">Insertar solo nuevas</option>
        <option value="upsert">Insertar/Actualizar</option>
        <option value="replace">Reemplazar TODO (sin movimientos)</option>
      </select>
      <button class="btn">Importar CSV</button>
    </form>

    <form method="GET" action="{{ route('admin.cuentas.export') }}">
      <button class="btn">Exportar CSV</button>
    </form>
  </div>
</div>

<div class="card">
  <table>
    <thead><tr>
      <th>Código</th><th>Nombre</th><th style="width:160px">Acciones</th>
    </tr></thead>
    <tbody>
      @forelse($cuentas as $c)
        <tr>
          <td>{{ $c->codigo }}</td>
          <td>{{ $c->nombre }}</td>
          <td>
            <button class="btn" onclick="editar({{ $c->id_cta_contable }}, '{{ addslashes($c->codigo) }}', '{{ addslashes($c->nombre) }}')">Editar</button>
            <form method="POST" action="{{ route('admin.cuentas.delete',$c->id_cta_contable) }}" style="display:inline">
              @csrf
              <button class="btn" onclick="return confirm('¿Eliminar la cuenta {{ $c->codigo }}?')">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="muted">No hay cuentas aún.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<script>
function editar(id,codigo,nombre){
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_codigo').value = codigo;
  document.getElementById('edit_nombre').value = nombre;
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>
@endsection
