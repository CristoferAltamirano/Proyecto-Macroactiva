@extends('layout')

@section('title', 'Crear Nuevo Administrador')

@section('content')
<div class="container">
    <h1 class="mb-4">Crear Nuevo Administrador</h1>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                @include('admin.users._form', ['user' => new \App\Models\User()])
                <div class="d-flex justify-content-end">
                    <a href="{{ route('users.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection