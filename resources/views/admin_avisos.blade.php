@extends('layout')
@section('title','Avisos de cobro')
@section('content')
@include('partials.flash')

<div class="card">
  <h3>Enviar avisos</h3>
  <form method="POST" action="{{ route('admin.avisos.enviar') }}" class="grid">@csrf
    <input name="periodo" value="{{ $corte }}" placeholder="Periodo (YYYYMM)" required>
    <select name="id_condominio">
      <option value="">Todos</option>
      @foreach($condos as $c)
        <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
      @endforeach
    </select>
    <label style="display:flex;gap:6px;align-items:center">
      <input type="checkbox" name="solo_pendientes" value="1" checked> Solo con saldo pendiente
    </label>
    <button class="btn">Enviar</button>
  </form>
</div>
@endsection
