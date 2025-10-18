<div class="mb-3">
    <label for="name" class="form-label">Nombre</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" {{ $user->exists ? '' : 'required' }}>
        @if($user->exists)
            <small class="form-text text-muted">Dejar en blanco para no cambiar la contraseña.</small>
        @endif
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
    </div>
</div>

<div class="mb-3">
    <label for="role" class="form-label">Rol</label>
    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
        <option value="super-admin" {{ old('role', $user->role) === 'super-admin' ? 'selected' : '' }}>Super Admin</option>
    </select>
    @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<hr class="my-4">