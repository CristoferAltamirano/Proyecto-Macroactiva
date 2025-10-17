@extends('layout')
@section('title','Recuperar contraseña')
@section('content')
@include('partials.flash')
<div class="card">
  <h3>Recuperar contraseña</h3>
  <form method="POST" action="{{ route('password.email') }}" class="grid">@csrf
    <input type="email" name="email" placeholder="Correo" required>
    <button class="btn">Enviar enlace</button>
  </form>
</div>
@endsection
