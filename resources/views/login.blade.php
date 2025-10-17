@extends('layout')
@section('title','Ingresar')

@section('content')
<style>
  /* ===== Fondo y centrado a pantalla completa ===== */
  main:has(.auth-hero){ max-width:none; }
  .auth-hero{
    position:relative;
    min-height:calc(100vh - 56px);
    display:grid; place-items:center;
    /* más espacio abajo para separar del footer */
    padding:24px 24px 120px; 
    overflow:hidden;
  }
  .auth-hero::before{
    content:""; position:absolute; inset:0; z-index:-2;
    background:
      linear-gradient(180deg, rgba(15,23,42,.18), rgba(15,23,42,.30)),
      var(--login-bg, none) center/cover no-repeat;
  }
  .auth-hero::after{ content:""; position:absolute; inset:0; z-index:-1; backdrop-filter: blur(4px); }

  /* ===== Panel translúcido (glass) ===== */
  .glass{
    --pad: 28px; --show-icon: 1;
    width:100%; max-width:560px;
    background: rgba(255,255,255,.55);
    border:1px solid color-mix(in srgb, #FFFFFF 45%, var(--border));
    border-radius:14px;
    box-shadow: 0 28px 60px rgba(15,23,42,.18), inset 0 1px 0 rgba(255,255,255,.65);
    backdrop-filter: saturate(130%) blur(6px);
    overflow:hidden;
  }
  .glass-head{ display:grid; justify-items:center; gap:10px; text-align:center; padding: 22px var(--pad) 10px; }
  .glass-icon{
    width:46px; height:46px; border-radius:50%; display:grid; place-items:center;
    background: color-mix(in srgb, var(--primary) 10%, #fff);
    color: color-mix(in srgb, var(--primary) 80%, #0F172A);
    border:1px solid color-mix(in srgb, var(--primary) 25%, #E6EAF1);
    opacity: calc(var(--show-icon));
  }
  .glass-title{ margin:0; font-size:1.28rem; letter-spacing:.12rem; color:#0F172A; text-transform:uppercase; }

  .glass-body{ display:grid; gap:12px; padding: 12px var(--pad) var(--pad); }
  .form-label{ font-size:.95rem; color:#334155; text-align:left; }

  .input{
    width:100%; height:48px; padding: 10px 12px;
    border-radius:6px; background:#fff; border:1px solid #E5E7EB;
    font-size:1rem; outline:none; transition:border-color .15s, box-shadow .15s;
  }
  .input:focus{
    border-color: color-mix(in srgb, var(--primary) 55%, #E5E7EB);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);
  }

  .pwd-wrap{ position:relative; }

  /* ===== Botón ojito ===== */
  #togglePwd{
    --btn-size: 36px;
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    width:var(--btn-size); height:var(--btn-size);
    display:grid; place-items:center;
    border-radius:8px; background:#fff;
    color:#475569; border:1px solid var(--border);
    cursor:pointer;
  }
  #togglePwd svg{ width:18px; height:18px; }
  #togglePwd .eye{ display:block; }
  #togglePwd .eye-off{ display:none; }
  #togglePwd.is-on .eye{ display:none; }
  #togglePwd.is-on .eye-off{ display:block; }

  .soft-line{ height:3px; background:#E8EDF5; border-radius:2px; }

  /* ===== Botón Entrar ===== */
  .btn-cta{
    width:100%; height:50px; border-radius:8px; display:grid; place-items:center; position:relative;
    background: color-mix(in srgb, var(--primary) 88%, #0F172A);
    color:#fff; font-weight:700; letter-spacing:.04rem;
    box-shadow: 0 10px 26px color-mix(in srgb, var(--primary) 26%, transparent);
    transition: transform .05s ease;
  }
  .btn-cta:hover{ transform: translateY(-1px); }
  .btn-cta[disabled]{ opacity:.6; cursor:not-allowed; transform:none; }
  .btn-label{ transition:opacity .15s; }
  .btn-spinner{
    position:absolute; width:16px; height:16px; border-radius:50%;
    border:2px solid rgba(255,255,255,.55); border-top-color:#fff;
    display:none; animation: spin 1s linear infinite;
  }
  .btn-cta.loading .btn-label{ opacity:0; } .btn-cta.loading .btn-spinner{ display:block; }
  @keyframes spin{ to{ transform:rotate(360deg) } }

  /* ===== Debajo del botón ===== */
  .aux-under{
    display:flex; justify-content:space-between; align-items:center; gap:12px;
    margin-top:10px; color:#475569; font-size:.95rem;
  }
  .checkbox-row{ display:flex; align-items:center; gap:8px; }
  .link-muted{ color:#64748B; text-decoration:none; }
  .link-muted:hover{ text-decoration:underline; }

  /* ===== Responsive ===== */
  @media (max-width: 560px){
    .glass{ max-width: 92vw; }
    .aux-under{
      flex-direction:column;
      align-items:center; 
      justify-content:center;
      text-align:center;
      gap:10px;
    }
    .checkbox-row{ justify-content:center; }
    .link-muted{ display:block; }
  }

  /* Avisos y footer */
  .alert{ background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 12px; border-radius:8px; margin: 0 var(--pad) 8px; }
  .auth-footer{
    position:fixed; left:50%; transform:translateX(-50%); bottom:12px; z-index:70;
    background:rgba(255,255,255,.92); border:1px solid var(--border); border-radius:12px;
    padding:6px 12px; font-size:.82rem; color:#334155; box-shadow: var(--shadow-sm);
  }
</style>

<div class="auth-hero"
     style="--login-bg: url('https://wallpapers.com/images/high/santiago-chile-orange-sky-ldrwipi9e2qpzf7k.webp');">

  <div class="glass">
    <div class="glass-head">
      <div class="glass-icon" aria-hidden="true">
        <!-- Puedes ocultarlo con --show-icon:0 en .glass o reemplazar por tu logo SVG -->
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="8" r="3.25" stroke="currentColor" stroke-width="1.5"/>
          <path d="M5 19.25c0-3.25 3.2-5.25 7-5.25s7 2 7 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="glass-title">Inicia sesión</h2>
    </div>

    @if ($errors->any())
      <div class="alert">⚠️ {{ $errors->first() }}</div>
    @endif

    <form id="formLogin" method="POST" action="{{ route('login.do') }}" class="glass-body">
      @csrf

      <label class="form-label" for="email">Email</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required class="input" autocomplete="email" autofocus>

      <label class="form-label" for="password">Contraseña</label>
      <div class="pwd-wrap">
        <input id="password" name="password" type="password" required class="input" autocomplete="current-password">
        <!-- Botón ojo -->
        <button type="button" id="togglePwd" aria-label="Mostrar u ocultar contraseña" title="Mostrar/Ocultar">
          <!-- ojo abierto -->
          <svg class="eye" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke="currentColor" stroke-width="1.6" />
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
          </svg>
          <!-- ojo tachado -->
          <svg class="eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.5 0 10 6 10 6a18.7 18.7 0 0 1-4.1 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <path d="M6.1 8.2A18.9 18.9 0 0 0 2 11s3.5 6 10 6c1.2 0 2.3-.2 3.3-.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
          </svg>
        </button>
      </div>

      <div class="soft-line" aria-hidden="true"></div>

      <button id="btnLogin" class="btn btn-cta" type="submit" disabled>
        <span class="btn-label">Entrar</span>
        <span class="btn-spinner" aria-hidden="true"></span>
      </button>

      <div class="aux-under">
        <label class="checkbox-row">
          <input type="checkbox" name="remember"><span>Recordarme</span>
        </label>
        @if (Route::has('password.request'))
          <a href="{{ route('password.request') }}" class="link-muted">¿Olvidaste tu contraseña?</a>
        @endif
      </div>
    </form>
  </div>
</div>

<footer class="auth-footer"><strong>MACROACTIVA</strong> © 2025</footer>

<script>
(function(){
  const form = document.getElementById('formLogin');
  const email = document.getElementById('email');
  const pwd   = document.getElementById('password');
  const btn   = document.getElementById('togglePwd');
  const cta   = document.getElementById('btnLogin');

  /* Toggle ojo + tipo de input */
  btn?.addEventListener('click', function(){
    const show = pwd.type !== 'text';
    pwd.type = show ? 'text' : 'password';
    this.classList.toggle('is-on', show); // cambia el icono (ojo ↔ ojo tachado)
  });

  /* Habilitar CTA sólo con email/pass válidos */
  const validEmail = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  function toggleCTA(){ cta.disabled = !(validEmail(email.value.trim()) && pwd.value.trim().length>0); }
  email?.addEventListener('input', toggleCTA);
  pwd?.addEventListener('input', toggleCTA);

  /* Loading + anti doble submit */
  form?.addEventListener('submit', (e)=>{
    if(cta.disabled){ e.preventDefault(); return; }
    cta.classList.add('loading'); cta.disabled = true;
  });

  toggleCTA();
}());
</script>
@endsection
    