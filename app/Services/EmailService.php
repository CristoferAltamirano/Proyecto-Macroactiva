<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AvisoCobroMail;
use App\Mail\ReciboPagoMail;
use App\Mail\AlertaMoraMail;

class EmailService
{
    /** Obtiene email y nombre de residente vigente (o copropietario si no hay) para una unidad */
    public static function contactoUnidad(int $idUnidad): ?array
    {
        $res = DB::table('residencia as r')
            ->join('usuario as u','u.id_usuario','=','r.id_usuario')
            ->where('r.id_unidad',$idUnidad)->whereNull('r.hasta')
            ->orderByDesc('r.desde')->select('u.email','u.nombres','u.apellidos')->first();
        if ($res) return ['email'=>$res->email, 'nombre'=>$res->nombres.' '.$res->apellidos];

        $cop = DB::table('copropietario as c')
            ->join('usuario as u','u.id_usuario','=','c.id_usuario')
            ->where('c.id_unidad',$idUnidad)->whereNull('c.hasta')
            ->orderByDesc('c.desde')->select('u.email','u.nombres','u.apellidos')->first();
        if ($cop) return ['email'=>$cop->email, 'nombre'=>$cop->nombres.' '.$cop->apellidos];

        return null;
    }

    public static function enviarAvisoCobro(int $idCobro): void
    {
        $c = DB::table('cobro')->where('id_cobro',$idCobro)->first();
        if(!$c) return;

        $contacto = self::contactoUnidad((int)$c->id_unidad);
        if(!$contacto || empty($contacto['email'])) return;

        $condo = DB::table('unidad as u')->join('grupo as g','g.id_grupo','=','u.id_grupo')
                ->join('condominio as c2','c2.id_condominio','=','g.id_condominio')
                ->where('u.id_unidad',$c->id_unidad)->select('c2.nombre','u.codigo')->first();

        $data = [
            'nombre'=>$contacto['nombre'],
            'unidad'=>$condo?->codigo ?? 'Unidad',
            'periodo'=>$c->periodo,
            'monto'=> (float)$c->saldo,
            'url_pdf'=> route('cobro.aviso.pdf',$c->id_cobro),
            'condominio'=> $condo?->nombre ?? 'Condominio'
        ];
        Mail::to($contacto['email'])->send(new AvisoCobroMail($data));
    }

    public static function enviarReciboPago(int $idPago): void
    {
        $p = DB::table('pago')->where('id_pago',$idPago)->first();
        if(!$p) return;

        $contacto = self::contactoUnidad((int)$p->id_unidad);
        if(!$contacto || empty($contacto['email'])) return;

        $condo = DB::table('unidad as u')->join('grupo as g','g.id_grupo','=','u.id_grupo')
                ->join('condominio as c2','c2.id_condominio','=','g.id_condominio')
                ->where('u.id_unidad',$p->id_unidad)->select('c2.nombre','u.codigo')->first();

        $data = [
            'nombre'=>$contacto['nombre'],'unidad'=>$condo?->codigo ?? 'Unidad',
            'periodo'=>$p->periodo,'monto'=>(float)$p->monto,
            'url_recibo'=> route('pagos.recibo.pdf',$p->id_pago),
            'condominio'=>$condo?->nombre ?? 'Condominio',
            'fecha'=>$p->fecha_pago
        ];
        Mail::to($contacto['email'])->send(new ReciboPagoMail($data));
    }

    public static function enviarAlertaMora(int $idCobro): void
    {
        $c = DB::table('cobro')->where('id_cobro',$idCobro)->first();
        if(!$c || (float)$c->saldo<=0) return;

        $contacto = self::contactoUnidad((int)$c->id_unidad);
        if(!$contacto || empty($contacto['email'])) return;

        $condo = DB::table('unidad as u')->join('grupo as g','g.id_grupo','=','u.id_grupo')
                ->join('condominio as c2','c2.id_condominio','=','g.id_condominio')
                ->where('u.id_unidad',$c->id_unidad)->select('c2.nombre','u.codigo')->first();

        $data = [
            'nombre'=>$contacto['nombre'],'unidad'=>$condo?->codigo ?? 'Unidad',
            'periodo'=>$c->periodo,'saldo'=>(float)$c->saldo,
            'condominio'=>$condo?->nombre ?? 'Condominio',
            'vencimiento'=> null,
        ];
        Mail::to($contacto['email'])->send(new AlertaMoraMail($data));
    }
}
