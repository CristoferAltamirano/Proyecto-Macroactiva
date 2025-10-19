@extends('layout')

@section('content')
    <h1>Detalle Condominio</h1>
    <p><strong>ID:</strong> {{ $condominio->id_condominio }}</p>
    <p><strong>Nombre:</strong> {{ $condominio->nombre }}</p>
    <p><strong>Dirección:</strong> {{ $condominio->direccion }}</p>
    <a href="{{ route('condominios.index') }}" class="btn btn-primary">Volver</a>
@endsection