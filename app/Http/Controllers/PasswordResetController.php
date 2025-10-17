<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\ResetPasswordMail;
use App\Services\AuditService;

class PasswordResetController extends Controller
{
    public function showForgot() {
        return view('auth_forgot');
    }

    public function sendLink(Request $r) {
        $d = $r->validate(['email'=>['required','email']]);
        $email = strtolower($d['email']);

        $user = DB::table('usuario')->where('email',$email)->first();
        if (!$user) return back()->with('ok','Si el correo existe, se envió un enlace de reset.');

        // Genera token simple y guarda hash
        $plain = Str::random(48);
        $hash  = hash('sha256', $plain);

        DB::table('password_reset_token')->where('email',$email)->delete();
        DB::table('password_reset_token')->insert([
            'email'      => $email,
            'token'      => $hash,
            'expires_at' => Carbon::now()->addMinutes(30),
            'created_at' => now(),
        ]);

        $link = route('password.reset.form', ['email'=>$email, 'token'=>$plain]);
        try {
            Mail::to($email)->send(new ResetPasswordMail($link));
            AuditService::log('usuario', (int)$user->id_usuario, 'PWD_RESET_LINK', ['email'=>$email]);
            return back()->with('ok','Revisa tu correo: enviamos un enlace de recuperación.');
        } catch (\Throwable $e) {
            // Fallback MVP: mostramos el link (útil en dev/sin SMTP)
            return back()->with('ok','No se pudo enviar correo. Usa este enlace temporal: '.$link);
        }
    }

    public function showReset(Request $r) {
        $email = $r->query('email');
        $token = $r->query('token');
        abort_unless($email && $token, 404);
        return view('auth_reset', compact('email','token'));
    }

    public function doReset(Request $r) {
        $d = $r->validate([
            'email' => ['required','email'],
            'token' => ['required','string'],
            'password' => ['required','string','min:6','confirmed'],
        ]);

        $row = DB::table('password_reset_token')->where('email', strtolower($d['email']))->first();
        if (!$row) return back()->with('error','Token inválido o expirado.');

        $ok = (hash('sha256', $d['token']) === $row->token) && (Carbon::parse($row->expires_at)->isFuture());
        if (!$ok) return back()->with('error','Token inválido o expirado.');

        // Actualiza contraseña
        $u = DB::table('usuario')->where('email', strtolower($d['email']))->first();
        if (!$u) return back()->with('error','Usuario no encontrado.');
        DB::table('usuario')->where('id_usuario', $u->id_usuario)->update(['pass_hash'=>Hash::make($d['password'])]);

        // Limpia tokens y audita
        DB::table('password_reset_token')->where('email', strtolower($d['email']))->delete();
        AuditService::log('usuario', (int)$u->id_usuario, 'PWD_RESET', []);

        return redirect()->route('login')->with('ok','Contraseña actualizada. Inicia sesión.');
    }
}
