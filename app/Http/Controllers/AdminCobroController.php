<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CobroService;

class AdminCobroController extends Controller
{
    public function panel()
    {
        $condos = DB::table('condominio')->orderBy('nombre')->get();
        $ultimos = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->join('condominio as co','co.id_condominio','=','g.id_condominio')
            ->select('c.*','u.codigo as unidad','co.nombre as condominio')
            ->orderByDesc('c.emitido_at')->limit(50)->get();

        return view('admin_cobros_panel', compact('condos','ultimos'));
    }

    public function generar(Request $r)
    {
        $data = $r->validate([
            'periodo'=>['required','regex:/^[0-9]{6}$/'],
            'id_condominio'=>['nullable','integer'],
        ]);

        // Generar cobros
        $ids = CobroService::generarDesdeCargos($data['periodo'], $data['id_condominio'] ?? null);

        // Si el servicio devuelve IDs de cobros generados, aplicar hook LedgerService
        if (is_array($ids)) {
            foreach ($ids as $idCobroCreado) {
                \App\Services\LedgerService::asientoCobro($idCobroCreado);
            }
            $n = count($ids);
        } else {
            // Compatibilidad si retorna solo un número
            $n = (int)$ids;
        }

        return back()->with('ok', "Cobros del periodo {$data['periodo']} generados/actualizados para {$n} unidad(es).");
    }

    public function intereses(Request $r)
    {
        $data = $r->validate([
            'periodo'=>['required','regex:/^[0-9]{6}$/'],
            'id_condominio'=>['nullable','integer'],
        ]);

        $n = CobroService::generarIntereses($data['periodo'], $data['id_condominio'] ?? null);

        return back()->with('ok', "Intereses generados para {$n} cobro(s) pendientes.");
    }
}
