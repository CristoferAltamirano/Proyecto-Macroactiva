@extends('layout')

@section('content')
    <h1>Condominios</h1>
    <a href="{{ route('condominios.create') }}" class="btn btn-primary">Crear Condominio</a>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($condominios as $condominio)
                <tr>
                    <td>{{ $condominio->id_condominio }}</td>
                    <td>{{ $condominio->nombre }}</td>
                    <td>{{ $condominio->direccion }}</td>
                    <td>
                        <a href="{{ route('condominios.show', $condominio->id_condominio) }}" class="btn btn-info">Ver</a>
                        <a href="{{ route('condominios.edit', $condominio->id_condominio) }}" class="btn btn-warning">Editar</a>
                        <form action="{{ route('condominios.destroy', $condominio->id_condominio) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection