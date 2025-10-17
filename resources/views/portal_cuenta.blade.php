@extends('layout')
@section('title','Mi Cuenta')

@section('content')
@include('partials.flash')

@php
  // Normalizamos $unidades (puede venir como colección de IDs o de objetos)
  $uList = collect($unidades ?? []);
  $first = $uList->first();
  $isObj = $uList->isNotEmpty() && is_object($first);
  // Detectamos unidad seleccionada (controlador puede mandar $selected_unidad; si no, usamos query ?id_unidad=)
  $selectedId = $selected_unidad ?? request('id_unidad');
  // Si no viene seleccionada y existen unidades, auto-seleccionar la primera
  if (!$selectedId && $uList->isNotEmpty()) {
    $selectedId = $isObj ? ($first->id_unidad ?? $first->id ?? null) : $first;
  }
@endphp

{{-- ===== Encabezado Usuario ===== --}}
<div class="card" style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
  <div><strong>Usuario:</strong> {{ auth()->user()->nombres }} {{ auth()->user()->apellidos }}</div>
  <div class="muted">{{ auth()->user()->email }}</div>
</div>

{{-- ===== Mis Unidades + Selector ===== --}}
<div class="card">
  <h3>Mis unidades</h3>

  @if($uList->isEmpty())
    <p class="muted">No tienes unidades asociadas vigentes.</p>
  @else
    {{-- listado simple --}}
    <ul>
      @foreach($uList as $u)
        @php
          $uid = $isObj ? ($u->id_unidad ?? $u->id ?? null) : $u;
          $ucd = $isObj ? ($u->codigo ?? ('Unidad '.$uid)) : ('Unidad '.$uid);
        @endphp
        <li>
          {{ $ucd }}
          <a class="btn" href="{{ route('mi.cuenta',['id_unidad'=>$uid]) }}">Ver</a>
        </li>
      @endforeach
    </ul>

    {{-- selector rápido + submit GET --}}
    <form method="GET" action="{{ route('mi.cuenta') }}" style="margin-top:10px; display:flex; gap:10px; align-items:end; flex-wrap:wrap">
      <label>Seleccionar unidad
        <select name="id_unidad" style="padding:8px;border:1px solid #e5e7eb;border-radius:10px; min-width:240px">
          @foreach($uList as $u)
            @php
              $uid = $isObj ? ($u->id_unidad ?? $u->id ?? null) : $u;
              $ucd = $isObj ? ($u->codigo ?? ('Unidad '.$uid)) : ('Unidad '.$uid);
            @endphp
            <option value="{{ $uid }}" @selected($selectedId==$uid)>{{ $ucd }}</option>
          @endforeach
        </select>
      </label>
      <button class="btn">Ver estado</button>
    </form>
  @endif
</div>

{{-- ===== Estado de Cuenta (sólo si hay una unidad seleccionada) ===== --}}
@if($selectedId)
  @php
    // Totales seguros
    $totalDeuda = isset($cobros) ? (collect($cobros)->sum('saldo')) : 0;
  @endphp

  <div class="card" style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
    <div><strong>Unidad seleccionada:</strong> {{ $unidad_codigo ?? ('Unidad '.$selectedId) }}</div>
    <div><strong>Deuda total:</strong> ${{ number_format($totalDeuda, 0, ',', '.') }}</div>
  </div>

  {{-- COBROS --}}
  <div class="card">
    <h3>Cobros</h3>
    <table>
      <thead>
        <tr>
          <th>Periodo</th>
          <th>Emitido</th>
          <th>Total</th>
          <th>Pagado</th>
          <th>Saldo</th>
          <th>Estado</th>
          <th>PDF</th>
          <th style="width:1%"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($cobros ?? [] as $c)
          @php
            $totalBruto = ($c->total_cargos + $c->total_interes - $c->total_descuentos);
          @endphp
          <tr>
            <td>{{ $c->periodo }}</td>
            <td class="muted">{{ \Illuminate\Support\Str::limit($c->emitido_at, 16, '') }}</td>
            <td>${{ number_format($totalBruto, 0, ',', '.') }}</td>
            <td>${{ number_format($c->total_pagado, 0, ',', '.') }}</td>
            <td><strong>${{ number_format($c->saldo, 0, ',', '.') }}</strong></td>
            <td>{{ $c->estado ?? $c->estado_nombre ?? '—' }}</td>
            <td>
              <a class="btn" href="{{ route('cobro.aviso.pdf',$c->id_cobro) }}" title="Aviso de cobro PDF">Aviso</a>
            </td>
            <td>
              @if(($c->saldo ?? 0) > 0)
                <button
                  class="btn js-fill-pay"
                  data-id-unidad="{{ $c->id_unidad }}"
                  data-periodo="{{ $c->periodo }}"
                  data-monto="{{ number_format($c->saldo, 0, '', '') }}"
                  title="Pagar saldo de este período"
                >Pagar saldo</button>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="muted">Sin cobros para esta unidad.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- PAGOS --}}
  <div class="card">
    <h3>Pagos</h3>
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Periodo</th>
          <th>Monto</th>
          <th>Método</th>
          <th>Recibo</th>
        </tr>
      </thead>
      <tbody>
        @forelse($pagos ?? [] as $p)
          <tr>
            <td>{{ \Illuminate\Support\Str::limit($p->fecha_pago, 16, '') }}</td>
            <td>{{ $p->periodo ?? '—' }}</td>
            <td>${{ number_format($p->monto, 0, ',', '.') }}</td>
            <td>{{ $p->metodo ?? $p->metodo_nombre ?? $p->id_metodo_pago }}</td>
            <td>
              @if(isset($p->id_pago))
                <a class="btn" href="{{ route('pagos.recibo.pdf',$p->id_pago) }}" title="Recibo PDF">PDF</a>
              @else
                <span class="muted">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">Sin pagos para esta unidad.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- PAGO EN LÍNEA (Webpay) --}}
  <div class="card">
    <h3>Pagar en línea</h3>
    <form method="POST" action="{{ route('webpay.start') }}" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
      @csrf

      {{-- Unidad (combo con tus unidades) --}}
      @if($uList->isNotEmpty())
        <label>Unidad
          <select name="id_unidad" required style="padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%">
            @foreach($uList as $u)
              @php
                $uid = $isObj ? ($u->id_unidad ?? $u->id ?? null) : $u;
                $ucd = $isObj ? ($u->codigo ?? ('Unidad '.$uid)) : ('Unidad '.$uid);
              @endphp
              <option value="{{ $uid }}" @selected($selectedId==$uid)>{{ $ucd }}</option>
            @endforeach
          </select>
        </label>
      @else
        <label>Unidad
          <input name="id_unidad" type="number" required
                 style="padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%">
        </label>
      @endif

      <label>Periodo (AAAAMM) <small class="muted">(opcional)</small>
        <input name="periodo" pattern="^[0-9]{6}$"
               placeholder="Ej: {{ now()->format('Ym') }}"
               style="padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%">
      </label>

      <label>Monto
        <input name="monto" type="number" min="1" step="1" required
               placeholder="Ej: {{ number_format(($cobros->first()->saldo ?? 0),0,'','') }}"
               style="padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%">
      </label>

      <div style="align-self:end">
        <button class="btn">Pagar con Webpay</button>
        <div class="muted" style="margin-top:6px">Usa “Pagar saldo” en la tabla para autocompletar.</div>
      </div>
    </form>

    @if(session('ok'))
      <p class="muted" style="margin-top:8px">{{ session('ok') }}</p>
    @endif
  </div>
@else
  {{-- Sin unidad seleccionada --}}
  <div class="card">
    <h3>Estado de cuenta</h3>
    <p class="muted">Selecciona una unidad arriba y presiona <strong>Ver estado</strong>.</p>
  </div>
@endif

{{-- ===== Script para autocompletar pago desde la tabla de cobros ===== --}}
<script>
  document.addEventListener('click', function(e){
    if(e.target && e.target.classList.contains('js-fill-pay')){
      const btn = e.target;
      const id = btn.getAttribute('data-id-unidad');
      const per = btn.getAttribute('data-periodo');
      const mon = btn.getAttribute('data-monto');

      // set unidad (select si existe)
      const sel = document.querySelector('select[name="id_unidad"]');
      if(sel){
        const opt = [...sel.options].find(o => o.value == id);
        if(opt){ sel.value = id; }
      }else{
        const inp = document.querySelector('input[name="id_unidad"]');
        if(inp){ inp.value = id; }
      }

      const p = document.querySelector('input[name="periodo"]');
      if(p && per){ p.value = per; }

      const m = document.querySelector('input[name="monto"]');
      if(m && mon){ m.value = mon; }

      // scroll al formulario
      const form = document.querySelector('form[action$="{{ route('webpay.start') }}"]');
      if(form){ form.scrollIntoView({behavior:'smooth', block:'start'}); }
    }
  });
</script>
@endsection
