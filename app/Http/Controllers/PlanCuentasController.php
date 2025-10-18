<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanCuentasController extends Controller
{
    public function index()
    {
        $cuentas = DB::table('cuenta_contable')->orderBy('codigo')->get();
        return view('admin_cuentas', compact('cuentas'));
    }

    public function create()
    {
        // No es necesario, el formulario está en la vista index
        return redirect()->route('cuentas.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40', 'unique:cuenta_contable,codigo'],
            'nombre' => ['required', 'string', 'max:80'],
        ]);

        DB::table('cuenta_contable')->insert($data);

        return redirect()->route('cuentas.index')->with('success', 'Cuenta creada correctamente.');
    }

    public function edit($id)
    {
        $cuentas = DB::table('cuenta_contable')->orderBy('codigo')->get();
        $cuenta_a_editar = DB::table('cuenta_contable')->where('id_cta_contable', $id)->first();

        if (!$cuenta_a_editar) {
            return redirect()->route('cuentas.index')->with('error', 'La cuenta que intentas editar no existe.');
        }

        return view('admin_cuentas', compact('cuentas', 'cuenta_a_editar'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40', Rule::unique('cuenta_contable')->ignore($id, 'id_cta_contable')],
            'nombre' => ['required', 'string', 'max:80'],
        ]);

        DB::table('cuenta_contable')->where('id_cta_contable', $id)->update($data);

        return redirect()->route('cuentas.index')->with('success', 'Cuenta actualizada correctamente.');
    }

    public function destroy($id)
    {
        $ref = DB::table('libro_movimiento')->where('id_cta_contable', $id)->exists();
        if ($ref) {
            return back()->with('error', 'No se puede eliminar: la cuenta tiene movimientos asociados.');
        }

        DB::table('cuenta_contable')->where('id_cta_contable', $id)->delete();
        return redirect()->route('cuentas.index')->with('success', 'Cuenta eliminada correctamente.');
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
        return response($csv, 200, [
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>"attachment; filename=\"$fn\"",
        ]);
    }

    public function importCsv(Request $request)
    {
        $v = $request->validate([
            'archivo' => ['required','file','mimes:csv,txt'],
            'modo'    => ['required','in:insert,upsert,replace'],
        ]);

        $fh = fopen($request->file('archivo')->getRealPath(),'r');
        if (!$fh) return back()->with('error','No se pudo leer el archivo.');

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
            $hasMov = DB::table('libro_movimiento')->exists();
            if ($hasMov) return back()->with('error','No se puede REEMPLAZAR: hay movimientos contables.');
            DB::table('cuenta_contable')->truncate();
        }

        rewind($fh);
        fgetcsv($fh, 0, $delimiter); // Saltar encabezado

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