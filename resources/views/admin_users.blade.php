@extends('layout')
@section('title', 'Usuarios')
@section('content')
    @include('partials.flash')

    <div class="card">
        <h3>Crear usuario</h3>
        <form method="POST" action="{{ route('admin.usuarios.store') }}" class="grid">@csrf
            <select name="tipo_usuario" style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
                <option>admin</option>
                <option>copropietario</option>
                <option>residente</option>
                <option>super_admin</option>
            </select>
            <input name="rut" placeholder="RUT (12345678-9)" required
                style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <input name="nombres" placeholder="Nombres" required
                style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <input name="apellidos" placeholder="Apellidos" required
                style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <input name="email" type="email" placeholder="Email" required
                style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <input name="telefono" placeholder="Teléfono" style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <input name="direccion" placeholder="Dirección" style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <input name="password" type="password" placeholder="Password" required
                style="padding:8px;border:1px solid #e5e7eb;border-radius:10px">
            <button class="btn">Crear</button>
        </form>
    </div>

    <div class="card">
        <h3>Últimos usuarios</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Activo</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>{{ $u->id_usuario }}</td>
                        <td>{{ $u->tipo_usuario }}</td>
                        <td>{{ $u->nombres }} {{ $u->apellidos }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->activo ? 'Sí' : 'No' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.usuarios.toggle', $u->id_usuario) }}">@csrf
                                <button class="btn">{{ $u->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty <tr>
                        <td colspan="6" class="muted">Sin usuarios.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
