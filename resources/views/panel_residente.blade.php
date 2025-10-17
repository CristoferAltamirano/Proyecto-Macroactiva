@extends('layout')
@section('title','Mi Cuenta')
@section('content')
<div class="card">
  <h3>Mis unidades</h3>
  @php $unidades = $unidades ?? collect(); @endphp
  @if($unidades->isEmpty())
    <p class="muted">No tienes unidades asociadas vigentes.</p>
  @else
    <ul>
      @foreach($unidades as $id)
        <li>
          Unidad #{{ $id }}
          <a class="btn" href="{{ route('estado.cuenta',['id_unidad'=>$id]) }}">Estado de cuenta</a>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection
