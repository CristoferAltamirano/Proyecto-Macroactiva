@extends('layout')
@section('title','Restablecer contraseña')
@section('content')
@include('partials.flash')
<div class="card">
  <h3>Restablecer contraseña</h3>
  <form method="POST" action="{{ route('password.reset') }}" class="grid">@csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <input type="hidden" name="token" value="{{ $token }}">
    <input type="password" name="password" placeholder="Nueva contraseña" required>
    <input type="password" name="password_confirmation" placeholder="Confirmar contraseña" required>
    <button class="btn">Actualizar</button>
  </form>
</div>
@endsection
