<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Unidad; // <-- Importante
use Illuminate\Support\Facades\Hash; // <-- Importante

class ResidenteLoginController extends Controller
{
    /**
     * Muestra el formulario de login para el residente.
     */
    public function showLoginForm()
    {
        return view('portal.login');
    }

    /**
     * Maneja el intento de login del residente.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ======================================================================
        // PROTOCOLO DE DIAGNÓSTICO MANUAL
        // ======================================================================

        // 1. Buscamos la unidad por su email.
        $unidad = Unidad::where('email', $credentials['email'])->first();

        // 2. Verificamos si la encontramos Y si la contraseña coincide.
        //    Hash::check() compara el texto plano del formulario con el hash de la BD.
        if ($unidad && Hash::check($credentials['password'], $unidad->password)) {
            
            // 3. Si todo es correcto, iniciamos la sesión manualmente para el guardia 'residente'.
            Auth::guard('residente')->login($unidad);

            $request->session()->regenerate();

            // 4. Redirigimos al dashboard del portal.
            return redirect()->intended(route('portal.dashboard'));
        }

        // 5. Si algo falló (el email no existe o la contraseña no coincide), volvemos con el error.
        return back()->withErrors([
            'email' => 'Las credenciales no coinciden.',
        ])->onlyInput('email');
    }

    /**
     * Cierra la sesión del residente.
     */
    public function logout(Request $request)
    {
        Auth::guard('residente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('portal.login');
    }
}