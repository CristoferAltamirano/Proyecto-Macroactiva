<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanCuentasController extends Controller
{
    public function index()
    {
        $cuentas = DB::table('cuenta_contable')->orderBy('codigo')->get();
        return view('admin_cuentas', compact('cuentas'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'id_cta_contable' => ['nullable','integer','min:1'],
            'codigo' => ['required','string','max:40'],
            'nombre' => ['required','string','max:80'],
        ]);

        // Verificar unicidad de código
        $exists = DB::table('cuenta_contable')
            ->where('codigo',$data['codigo'])
            ->when(!empty($data['id_cta_contable']), fn($q)=>$q->where('id_cta_contable','<>',$data['id_cta_contable']))
            ->exists();
        if ($exists) return back()->with('error','El código de cuenta ya existe.');

        if (!empty($data['id_cta_contable'])) {
            DB::table('cuenta_contable')->where('id_cta_contable',$data['id_cta_contable'])
                ->update(['codigo'=>$data['codigo'],'nombre'=>$data['nombre']]);
            return back()->with('ok','Cuenta actualizada.');
        } else {
            DB::table('cuenta_contable')->insert([
                'codigo'=>$data['codigo'],'nombre'=>$data['nombre']
            ]);
            return back()->with('ok','Cuenta creada.');
        }
    }

    public function destroy(int $id)
    {
        // Evitar borrar si está referenciada en libro_movimiento
        $ref = DB::table('libro_movimiento')->where('id_cta_contable',$id)->exists();
        if ($ref) return back()->with('error','No se puede eliminar: la cuenta tiene movimientos.');

        DB::table('cuenta_contable')->where('id_cta_contable',$id)->delete();
        return back()->with('ok','Cuenta eliminada.');
    }

    public function exportCsv()
    {
        $rows = DB::table('cuenta_contable')->orderBy('codigo')->get();
        $csv = "codigo,nombre\n";
        foreach ($rows as $r) {
            $csv .= sprintf("%s,%s\n",
                str_replace([",","\n","\r"]," ",$r->codigo),
                str_replace([",","\n","\r"]," ",$r->nombre)
            );
        }
        $fn = 'plan_cuentas_'.date('Ymd_His').'.csv';
        return response($csv,200,[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>"attachment; filename=\"$fn\"",
        ]);
    }

    public function importCsv(Request $r)
    {
        $v = $r->validate([
            'archivo' => ['required','file','mimes:csv,txt'],
            'modo'    => ['required','in:insert,upsert,replace'],
        ]);

        $fh = fopen($r->file('archivo')->getRealPath(),'r');
        if (!$fh) return back()->with('error','No se pudo leer el archivo.');

        // Detectar separador simple
        $first = fgets($fh);
        $delimiter = (substr_count($first,';') > substr_count($first,',')) ? ';' : ',';
        $headers = str_getcsv($first, $delimiter);
        $map = ['codigo'=>null,'nombre'=>null];
        foreach ($headers as $i=>$h) {
            $h = strtolower(trim($h));
            if (isset($map[$h])) $map[$h] = $i;
        }
        if ($map['codigo']===null || $map['nombre']===null) {
            fclose($fh);
            return back()->with('error','CSV debe tener columnas: codigo, nombre');
        }

        $n=0; $u=0;
        if ($v['modo']==='replace') {
            // cuidado: solo borra si no hay movimientos (para seguridad)
            $hasMov = DB::table('libro_movimiento')->exists();
            if ($hasMov) return back()->with('error','No se puede REPLACE: hay movimientos contables.');
            DB::table('cuenta_contable')->truncate();
        }

        rewind($fh); // volver al inicio completo
        // Saltar encabezado
        fgetcsv($fh, 0, $delimiter);

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $codigo = trim($row[$map['codigo']] ?? '');
            $nombre = trim($row[$map['nombre']] ?? '');
            if ($codigo==='' || $nombre==='') continue;

            $ex = DB::table('cuenta_contable')->where('codigo',$codigo)->first();
            if ($ex) {
                if ($v['modo']!=='insert') {
                    DB::table('cuenta_contable')->where('id_cta_contable',$ex->id_cta_contable)
                        ->update(['nombre'=>$nombre]);
                    $u++;
                }
            } else {
                DB::table('cuenta_contable')->insert(['codigo'=>$codigo,'nombre'=>$nombre]);
                $n++;
            }
        }
        fclose($fh);

        return back()->with('ok', "Importado. Nuevas: $n, Actualizadas: $u");
    }
}
