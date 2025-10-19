@extends('layout')

@section('content')
    <h1>Editar Condominio</h1>
    <form action="{{ route('condominios.update', $condominio->id_condominio) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" name="nombre" class="form-control" value="{{ $condominio->nombre }}">
        </div>
        <div class="form-group">
            <label for="direccion">Dirección</label>
            <input type="text" name="direccion" class="form-control" value="{{ $condominio->direccion }}">
        </div>
        <button type="submit" class="btn btn-success">Actualizar</button>
    </form>
@endsection