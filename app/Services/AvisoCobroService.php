<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AvisoCobroService
{
    /** Devuelve el email primario del residente; si no hay, del copropietario. */
    public static function emailUnidad(int $idUnidad): ?string
    {
        $hoy = now()->toDateString();
        $res = DB::table('residencia as r')->join('usuario as u','u.id_usuario','=','r.id_usuario')
            ->where('r.id_unidad',$idUnidad)->where(function($q){$q->whereNull('r.hasta')->orWhere('r.hasta','>=',now()->toDateString());})
            ->orderByDesc('r.desde')->select('u.email')->first();
        if ($res && $res->email) return strtolower($res->email);

        $cp = DB::table('copropietario as c')->join('usuario as u','u.id_usuario','=','c.id_usuario')
            ->where('c.id_unidad',$idUnidad)->where(function($q){$q->whereNull('c.hasta')->orWhere('c.hasta','>=',now()->toDateString());})
            ->orderByDesc('c.desde')->select('u.email')->first();
        return $cp? strtolower($cp->email): null;
    }

    public static function rowsAviso(string $periodo, ?int $idCondo=null)
    {
        $q = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->where('c.periodo',$periodo)
            ->select('c.*','u.codigo as unidad','co.nombre as condominio','g.id_condominio');
        if ($idCondo) $q->where('g.id_condominio',$idCondo);
        return $q->orderBy('co.nombre')->orderBy('u.codigo')->get();
    }
}
