<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression as Expr;

class CondominioController extends Controller
{
    /** Utilidad: toma la primera columna existente y la selecciona como alias. */
    private function pick(string $table, array $candidates, string $alias): Expr
    {
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) {
                return DB::raw("$c as $alias");
            }
        }
        return DB::raw("NULL as $alias");
    }

    /** Lista + formulario */
    public function index()
    {
        // Select robusto según columnas reales que tenga tu tabla
        $select = [
            DB::raw('id_condominio as id_condominio'),
            DB::raw('nombre as nombre'),
            $this->pick('condominio', ['rut_base'], 'rut_base'),
            $this->pick('condominio', ['rut_dv','dv','digito_verificador'], 'dv'),
            $this->pick('condominio', ['direccion'], 'direccion'),
            $this->pick('condominio', ['comuna'], 'comuna'),
            $this->pick('condominio', ['region'], 'region'),
            $this->pick('condominio', ['email','correo'], 'email'),
            $this->pick('condominio', ['telefono','nro_telefono'], 'telefono'),
            $this->pick('condominio', ['banco'], 'banco'),
            $this->pick('condominio', ['tipo_cuenta'], 'tipo_cuenta'),
            $this->pick('condominio', ['num_cuenta','nro_cuenta'], 'num_cuenta'),
        ];

        $condos = DB::table('condominio')->select($select)
            ->orderByDesc('id_condominio')->get();

        return view('admin_condos', compact('condos'));
    }

    /** Crear / actualizar */
    public function store(Request $r)
    {
        $r->validate([
            'id_condominio' => ['nullable','integer','min:1'],
            'nombre'        => ['required','string','max:120'],
            'rut_base'      => ['nullable','string','max:20'],
            'dv'            => ['nullable','string','max:3'],
            'direccion'     => ['nullable','string','max:180'],
            'comuna'        => ['nullable','string','max:120'],
            'region'        => ['nullable','string','max:120'],
            'email'         => ['nullable','string','max:180'],
            'telefono'      => ['nullable','string','max:60'],
            'banco'         => ['nullable','string','max:120'],
            'tipo_cuenta'   => ['nullable','string','max:40'],
            'num_cuenta'    => ['nullable','string','max:60'],
        ]);

        $T = 'condominio';
        $data = [];

        // Mapeo columna por columna según exista en DB
        $mapDirecto = [
            'nombre'      => 'nombre',
            'rut_base'    => 'rut_base',
            'direccion'   => 'direccion',
            'comuna'      => 'comuna',
            'region'      => 'region',
            'banco'       => 'banco',
            'tipo_cuenta' => 'tipo_cuenta',
        ];
        foreach ($mapDirecto as $in => $col) {
            if (Schema::hasColumn($T, $col)) $data[$col] = $r->input($in);
        }

        // dv: rut_dv | dv | digito_verificador
        foreach (['rut_dv','dv','digito_verificador'] as $col) {
            if (Schema::hasColumn($T, $col)) { $data[$col] = $r->input('dv'); break; }
        }

        // email: email | correo
        foreach (['email','correo'] as $col) {
            if (Schema::hasColumn($T, $col)) { $data[$col] = $r->input('email'); break; }
        }

        // telefono: telefono | nro_telefono
        foreach (['telefono','nro_telefono'] as $col) {
            if (Schema::hasColumn($T, $col)) { $data[$col] = $r->input('telefono'); break; }
        }

        // num_cuenta: num_cuenta | nro_cuenta
        foreach (['num_cuenta','nro_cuenta'] as $col) {
            if (Schema::hasColumn($T, $col)) { $data[$col] = $r->input('num_cuenta'); break; }
        }

        // Guardar (insert/update)
        if ($r->filled('id_condominio')) {
            DB::table($T)->where('id_condominio', $r->id_condominio)->update($data);
            return redirect()->route('admin.condos.panel')->with('ok','Condominio actualizado.');
        } else {
            $id = DB::table($T)->insertGetId($data);
            return redirect()->route('admin.condos.panel')->with('ok','Condominio creado (ID '.$id.').');
        }
    }
}
