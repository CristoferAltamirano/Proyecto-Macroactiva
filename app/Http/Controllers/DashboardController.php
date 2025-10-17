<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function panel(Request $r)
    {
        $u = $r->user();
        if (in_array($u->tipo_usuario, ['super_admin','admin'], true)) {
            return view('panel_admin');
        }
        return view('panel_residente');
    }
}
