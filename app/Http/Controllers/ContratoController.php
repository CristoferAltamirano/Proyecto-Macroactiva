<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\AuditoriaService;

class ContratoController extends Controller
{
    public function store(Request $r)
    {
        $d = $r->validate([
            'id_trabajador' => ['required','integer','min:1'],
            'tipo_contrato' => ['nullable','string','max:30'],
            'inicio'        => ['required','date'],
            'termino'       => ['nullable','date','after_or_equal:inicio'],
            'sueldo_base'   => ['nullable','numeric','min:0'],
            'jornada'       => ['nullable','string','max:30'],
            'doc_url'       => ['nullable','url','max:255'],
        ]);

        $table = $this->guessContratoTable();
        if (!$table) {
            return redirect()->route('admin.trab.panel')->with('err','No existe tabla de contratos.');
        }

        $cols = Schema::getColumnListing($table);
        $row  = [];
        $set = function(string $col, $val) use (&$row,$cols){ if (in_array($col,$cols,true)) $row[$col] = $val; };

        // FK trabajador
        if (in_array('id_trabajador',$cols,true)) $row['id_trabajador'] = (int)$d['id_trabajador'];
        elseif (in_array('trabajador_id',$cols,true)) $row['trabajador_id'] = (int)$d['id_trabajador'];

        // Tipo
        $set('tipo',          $d['tipo_contrato'] ?? 'indefinido');
        $set('tipo_contrato', $d['tipo_contrato'] ?? 'indefinido');

        // Fechas
        $set('inicio',        $d['inicio']);
        $set('fecha_inicio',  $d['inicio']);
        $set('termino',       $d['termino'] ?? null);
        $set('fecha_termino', $d['termino'] ?? null);

        // Sueldo / jornada / doc
        $set('sueldo_base',   $d['sueldo_base'] ?? 0);
        $set('sueldo',        $d['sueldo_base'] ?? 0);
        $set('jornada',       $d['jornada'] ?? 'Completa');
        $set('doc_url',       $d['doc_url'] ?? null);
        $set('documento_url', $d['doc_url'] ?? null);

        // timestamps por si la tabla los exige
        $set('created_at', now());
        $set('updated_at', now());

        // Determinar PK para insertGetId
        $pk = $this->firstCol($cols, ['id_contrato','id','id_cto','id_contrato_trabajador']);

        try {
            // insertGetId puede recibir el nombre de la PK
            $id = DB::table($table)->insertGetId($row, $pk);
        } catch (\Throwable $e) {
            // último intento: insert + lastInsertId
            try {
                DB::table($table)->insert($row);
                $id = DB::getPdo()->lastInsertId();
            } catch (\Throwable $e2) {
                AuditoriaService::log('contrato', 0, 'ERROR_CREAR', ['msg'=>$e2->getMessage(),'row'=>$row,'table'=>$table]);
                return redirect()->route('admin.trab.panel')->with('err','No se pudo registrar el contrato: '.$e2->getMessage())->withInput();
            }
        }

        AuditoriaService::log('contrato', (int)$id, 'CREAR', ['table'=>$table,'row'=>$row]);

        return redirect()->route('admin.trab.panel')->with('ok',"Contrato #$id registrado.");
    }

    private function guessContratoTable(): ?string
    {
        foreach (['contrato_trabajador','trabajador_contrato','contrato','contratos'] as $t) {
            if (Schema::hasTable($t)) return $t;
        }
        return null;
    }
    private function firstCol(array $cols, array $cands): string
    {
        foreach ($cands as $c) if (in_array($c,$cols,true)) return $c;
        return $cols[0] ?? 'id';
    }
}
