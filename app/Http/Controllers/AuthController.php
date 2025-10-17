<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin(){ return view('login'); }

    public function doLogin(Request $r)
    {
        $data = $r->validate(['email'=>['required','email'],'password'=>['required','min:6']]);
        $u = Usuario::where('email',$data['email'])->first();
        if(!$u || !Hash::check($data['password'],$u->pass_hash) || !$u->activo){
            return back()->withErrors(['email'=>'Credenciales inválidas o usuario inactivo.'])->withInput();
        }
        Auth::login($u, $r->boolean('remember')); $r->session()->regenerate();
        return redirect()->route('panel');
    }

    public function logout(Request $r)
    {
        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken();
        return redirect()->route('login');
    }
}
