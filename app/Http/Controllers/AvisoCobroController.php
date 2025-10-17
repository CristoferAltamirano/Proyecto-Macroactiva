<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Services\AvisoCobroService;
use App\Services\SignatureService;

class AvisoCobroController extends Controller
{
    public function panel()
    {
        $condos = DB::table('condominio')->orderBy('nombre')->get();
        $corte = now()->format('Ym');
        return view('admin_avisos', compact('condos','corte'));
    }

    /** PDF de un cobro individual */
    public function pdf($idCobro)
    {
        $c = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->where('c.id_cobro',$idCobro)
            ->select('c.*','u.codigo as unidad','co.nombre as condominio')->first();
        abort_unless($c, 404);

        $det = DB::table('cobro_detalle')->where('id_cobro',$c->id_cobro)->orderBy('id_cobro_det')->get();
        $payload = ['id_cobro'=>$c->id_cobro,'periodo'=>$c->periodo,'saldo'=>$c->saldo];
        $sig = SignatureService::make($payload);

        $html = view('aviso_cobro_pdf', ['c'=>$c,'det'=>$det,'sig'=>$sig])->render();
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('letter');
            return $pdf->stream("aviso_{$c->id_cobro}.pdf");
        }
        return response($html,200)->header('Content-Type','text/html');
    }

    /** Envío masivo por periodo + condo (opcional) */
    public function enviarMasivo(Request $r)
    {
        $d = $r->validate([
            'periodo' => ['required','regex:/^[0-9]{6}$/'],
            'id_condominio' => ['nullable','integer','min:1'],
            'solo_pendientes' => ['nullable','boolean'],
        ]);
        $soloPend = (bool)($d['solo_pendientes'] ?? true);

        $rows = AvisoCobroService::rowsAviso($d['periodo'], $d['id_condominio'] ?? null);
        $n=0;
        foreach ($rows as $c) {
            if ($soloPend && (float)$c->saldo<=0) continue;
            $email = \App\Services\AvisoCobroService::emailUnidad($c->id_unidad);
            if (!$email) continue;

            // Genera PDF embebido
            $det = DB::table('cobro_detalle')->where('id_cobro',$c->id_cobro)->orderBy('id_cobro_det')->get();
            $sig = \App\Services\SignatureService::make(['id_cobro'=>$c->id_cobro,'periodo'=>$c->periodo,'saldo'=>$c->saldo]);
            $html = view('aviso_cobro_pdf', ['c'=>$c,'det'=>$det,'sig'=>$sig])->render();

            try {
                \Illuminate\Support\Facades\Mail::send([], [], function($m) use ($email,$c,$html){
                    $m->to($email)
                      ->subject('Aviso de cobro '.$c->periodo.' — '.$c->condominio.' / '.$c->unidad)
                      ->setBody('Adjuntamos su aviso de cobro en PDF.','text/html');
                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('letter');
                        $m->attachData($pdf->output(), "aviso_{$c->periodo}_{$c->unidad}.pdf", ['mime'=>'application/pdf']);
                    } else {
                        $m->attachData($html, "aviso_{$c->periodo}_{$c->unidad}.html", ['mime'=>'text/html']);
                    }
                });
                $n++;
            } catch (\Throwable $e) {
                // ignorar errores individuales (MVP)
            }
        }

        return back()->with('ok', "Se intentó enviar $n avisos (solo pendientes: ".($soloPend?'sí':'no').").");
    }
}
