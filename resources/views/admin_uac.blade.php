@extends('layout')
@section('title', 'Asignar condominios a un admin')

@section('content')
    @include('partials.flash')

    <style>
        .pair { display:flex; flex-direction:column; gap:6px; align-items:center; }
        .control { width: 360px; max-width:100%; padding:8px; border:1px solid var(--border); border-radius:10px; text-align:center; }
        .chips { display:flex; gap:12px; flex-wrap:wrap; justify-content:center; }
        .chip  { display:flex; align-items:center; gap:8px; padding:12px 16px; border:1px solid var(--border); border-radius:12px; background:var(--card); }
        .toolbar { display:flex; gap:10px; justify-content:center; margin:10px 0; flex-wrap:wrap; }
        .muted { color:#6b7280; text-align:center; }
    </style>

    <div class="card">
        <h3 style="text-align:center;">Asignar condominios a un admin</h3>

        {{-- Selector de admin --}}
        <div class="pair">
            <label for="selAdmin">Administrador</label>
            <form method="GET" action="{{ route('admin.uac.panel') }}" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                <select name="id_usuario" id="selAdmin" class="control" onchange="this.form.submit()">
                    @foreach($admins as $a)
                        <option value="{{ $a->id }}" {{ (int)($selAdmin ?? 0) === (int)$a->id ? 'selected' : '' }}>
                            {{ $a->label }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn">Ver</button></noscript>
            </form>
        </div>

        <div class="toolbar">
            <button type="button" class="btn" onclick="selectAll(true)">Seleccionar visibles</button>
            <button type="button" class="btn" onclick="selectAll(false)">Quitar visibles</button>
            <button type="button" class="btn" onclick="invert()">Invertir visibles</button>
        </div>

        <div class="muted">{{ $count ?? 0 }} seleccionados de {{ $condos->count() }}</div>

        {{-- Form principal --}}
        <form method="POST" action="{{ route('admin.uac.save') }}">
            @csrf
            <input type="hidden" name="id_usuario" value="{{ (int)($selAdmin ?? 0) }}">

            <div class="chips" id="chips">
                @forelse($condos as $c)
                    @php $checked = in_array((int)$c->id_condominio, $assignedIds ?? [], true); @endphp
                    <label class="chip">
                        <input type="checkbox" name="id_condominio[]" value="{{ $c->id_condominio }}" {{ $checked ? 'checked' : '' }}>
                        {{ $c->nombre }}
                    </label>
                @empty
                    <div class="muted">No hay condominios.</div>
                @endforelse
            </div>

            <div style="display:flex; justify-content:center; margin-top:14px;">
                <button class="btn">Guardar</button>
            </div>
        </form>
    </div>

    <script>
        function selectAll(on) {
            document.querySelectorAll('#chips input[type="checkbox"]').forEach(cb => cb.checked = !!on);
        }
        function invert() {
            document.querySelectorAll('#chips input[type="checkbox"]').forEach(cb => cb.checked = !cb.checked);
        }
    </script>
@endsection
