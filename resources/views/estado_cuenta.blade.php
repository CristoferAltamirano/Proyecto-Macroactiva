@extends('layout')
@section('title','Estado de cuenta')
@section('content')
@include('partials.flash')

<style>
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media (max-width:900px){ .grid2{grid-template-columns:1fr} }
  table thead th, table tbody td{ text-align:center; }
  .pay-row{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
  .control{padding:8px;border:1px solid #e5e7eb;border-radius:10px}
</style>

@forelse($porUnidad as $id => $u)
  <div class="card">
    <h3>{{ $u['info']->condominio }} — Unidad {{ $u['info']->unidad }}</h3>

    {{-- Pago rápido del saldo abierto --}}
    @if($u['abierto'])
      <div class="card" style="background:#f8fafc">
        <p style="margin:0 0 6px">
          <strong>Periodo abierto:</strong> {{ $u['abierto']->periodo }}
          &nbsp;|&nbsp;
          <strong>Saldo:</strong> ${{ number_format($u['abierto']->saldo,0,',','.') }}
        </p>

        {{-- Pagar SALDO COMPLETO --}}
        <form method="POST"
              action="{{ route('webpay.start') }}"
              class="pay-row"
              onsubmit="this.querySelectorAll('button').forEach(b=>b.disabled=true)">
          @csrf
          <input type="hidden" name="id_unidad" value="{{ $u['info']->id_unidad }}">
          <input type="hidden" name="periodo"   value="{{ $u['abierto']->periodo }}">
          <input type="hidden" name="monto"     value="{{ (float)$u['abierto']->saldo }}">
          <button class="btn">Pagar saldo ahora</button>
        </form>

        {{-- Pagar otro monto (opcional) --}}
        <details style="margin-top:10px">
          <summary class="link-muted">Pagar otro monto…</summary>
          <form method="POST"
                action="{{ route('webpay.start') }}"
                class="pay-row"
                style="margin-top:8px"
                onsubmit="this.querySelectorAll('button').forEach(b=>b.disabled=true)">
            @csrf
            <input type="hidden" name="id_unidad" value="{{ $u['info']->id_unidad }}">
            <label>Monto
              <input class="control" name="monto" type="number" step="0.01" min="1" required>
            </label>
            <label>Periodo (AAAAMM)
              <input class="control" name="periodo" pattern="[0-9]{6}" value="{{ $u['abierto']->periodo }}">
            </label>
            <button class="btn">Pagar con Webpay</button>
          </form>
        </details>
      </div>
    @else
      <p class="muted">No hay saldos abiertos para esta unidad.</p>
      {{-- Aún así permitimos pago libre (por si quiere adelantar) --}}
      <details class="card" style="background:#f8fafc">
        <summary class="link-muted">Pagar un monto libre…</summary>
        <form method="POST"
              action="{{ route('webpay.start') }}"
              class="pay-row"
              style="margin-top:8px"
              onsubmit="this.querySelectorAll('button').forEach(b=>b.disabled=true)">
          @csrf
          <input type="hidden" name="id_unidad" value="{{ $u['info']->id_unidad }}">
          <label>Monto
            <input class="control" name="monto" type="number" step="0.01" min="1" required>
          </label>
          <label>Periodo (AAAAMM)
            <input class="control" name="periodo" pattern="[0-9]{6}">
          </label>
          <button class="btn">Pagar con Webpay</button>
        </form>
      </details>
    @endif

    <div class="grid2" style="margin-top:14px">
      <div class="card">
        <h4>Últimos cobros</h4>
        <table>
          <thead><tr><th>Periodo</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>PDF</th></tr></thead>
          <tbody>
            @forelse($u['cobros'] as $c)
              @php
                $total = (float)($c->total_cargos ?? 0) + (float)($c->total_interes ?? 0) - (float)($c->total_descuentos ?? 0);
              @endphp
              <tr>
                <td>{{ $c->periodo }}</td>
                <td>${{ number_format($total,0,',','.') }}</td>
                <td>${{ number_format($c->total_pagado ?? 0,0,',','.') }}</td>
                <td><strong>${{ number_format($c->saldo ?? 0,0,',','.') }}</strong></td>
                <td><a class="btn btn--sm" href="{{ route('cobro.aviso.pdf',$c->id_cobro) }}" target="_blank">Aviso</a></td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Sin cobros históricos.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="card">
        <h4>Pagos recientes</h4>
        <table>
          <thead><tr><th>Fecha</th><th>Periodo</th><th>Monto</th><th>Recibo</th></tr></thead>
          <tbody>
            @forelse($u['pagos'] as $p)
              <tr>
                <td>{{ \Illuminate\Support\Str::limit((string)$p->fecha_pago,19,'') }}</td>
                <td>{{ $p->periodo ?? '—' }}</td>
                <td>${{ number_format($p->monto ?? 0,0,',','.') }}</td>
                <td><a class="btn btn--sm" href="{{ route('pagos.recibo.pdf',$p->id_pago) }}" target="_blank">Ver</a></td>
              </tr>
            @empty
              <tr><td colspan="4" class="muted">Sin pagos registrados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@empty
  <div class="card"><p class="muted">No tienes unidades asociadas.</p></div>
@endforelse
@endsection
