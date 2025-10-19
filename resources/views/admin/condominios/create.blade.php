@extends('layout')

@section('content')
    <h1>Crear Condominio</h1>
    <form action="{{ route('condominios.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" name="nombre" class="form-control">
        </div>
        <div class="form-group">
            <label for="direccion">Dirección</label>
            <input type="text" name="direccion" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Guardar</button>
    </form>
@endsection