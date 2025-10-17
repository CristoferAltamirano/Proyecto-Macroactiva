@if(session('ok'))
  <div class="card" style="border-left:6px solid #16a34a;background:#f0fdf4">
    {{ session('ok') }}
  </div>
@endif
@if(session('error'))
  <div class="card" style="border-left:6px solid #dc2626;background:#fef2f2">
    {{ session('error') }}
  </div>
@endif
@if ($errors->any())
  <div class="card" style="border-left:6px solid #f59e0b;background:#fffbeb">
    <strong>Revisa los campos:</strong>
    <ul style="margin:6px 0 0 18px">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
