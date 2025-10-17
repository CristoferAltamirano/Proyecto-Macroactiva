<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\AuditoriaService;

class TrabajadorController extends Controller
{
    /** Panel: formulario + últimos */
    public function index()
    {
        $condos = DB::table('condominio')->orderBy('nombre')->get();

        // === Últimos trabajadores
        $q = DB::table('trabajador as t');
        if (Schema::hasTable('condominio')) {
            $q->leftJoin('condominio as c', 'c.id_condominio', '=', 't.id_condominio')
              ->addSelect('c.nombre as condominio');
        }

        $trabajadores = $q->addSelect('t.*')
            ->orderByDesc('t.id_trabajador')
            ->limit(60)
            ->get();

        // === Últimos contratos (autodetección de tabla/columnas)
        $contratos = collect();
        $table = $this->guessContratoTable();
        if ($table) {
            $cols = Schema::getColumnListing($table);

            $pk     = $this->firstCol($cols, ['id_contrato','id','id_cto','id_contrato_trabajador']);
            $fkTrab = $this->firstCol($cols, ['id_trabajador','trabajador_id']);
            $inicio = $this->firstCol($cols, ['inicio','fecha_inicio','f_inicio']);
            $termin = $this->firstCol($cols, ['termino','fecha_termino','f_termino']);
            $tipo   = $this->firstCol($cols, ['tipo','tipo_contrato']);
            $sueldo = $this->firstCol($cols, ['sueldo_base','sueldo']);
            $jorna  = $this->firstCol($cols, ['jornada']);
            $docurl = $this->firstCol($cols, ['doc_url','documento_url']);

            $cqb = DB::table($table.' as ct')
                ->leftJoin('trabajador as t', 't.id_trabajador', '=', 'ct.'.$fkTrab)
                ->leftJoin('condominio as c', 'c.id_condominio', '=', 't.id_condominio')
                ->selectRaw('ct.'.$pk.' as id_contrato')
                ->addSelect(
                    DB::raw('COALESCE(ct.'.$inicio.", NULL) as inicio"),
                    DB::raw('COALESCE(ct.'.$termin.", NULL) as termino"),
                    DB::raw('COALESCE(ct.'.$tipo.", NULL) as tipo"),
                    DB::raw('COALESCE(ct.'.$sueldo.", 0) as sueldo_base"),
                    DB::raw('COALESCE(ct.'.$jorna.", NULL) as jornada"),
                    DB::raw('COALESCE(ct.'.$docurl.", NULL) as doc_url"),
                    't.id_trabajador','t.nombres','t.apellidos',
                    DB::raw('c.nombre as condominio')
                )
                ->orderByDesc('ct.'.$pk)
                ->limit(60);

            $contratos = $cqb->get();
        }

        return view('trabajadores_index', [
            'condos'       => $condos,
            'trabajadores' => $trabajadores,
            'contratos'    => $contratos,
        ]);
    }

    /** Crear trabajador */
    public function store(Request $r)
    {
        $data = $r->validate([
            'id_condominio' => ['required','integer','min:1'],
            'tipo'          => ['nullable','string','max:30'],
            'nombres'       => ['required','string','max:80'],
            'apellidos'     => ['nullable','string','max:80'],
            'cargo'         => ['nullable','string','max:60'],
            'email'         => ['nullable','email','max:120'],
            'telefono'      => ['nullable','string','max:30'],
            'rut_base'      => ['required','string','max:15'],
            'rut_dv'        => ['nullable','string','max:1'],
        ]);

        // Saneo RUT
        $rutBase = preg_replace('/[^0-9]/', '', (string)$data['rut_base']);
        if ($rutBase === '' || !ctype_digit($rutBase)) {
            return redirect()->route('admin.trab.panel')->with('err','RUT base inválido.')->withInput();
        }
        $dvCalc = $this->rutDv($rutBase);
        $dv     = strtoupper((string)($data['rut_dv'] ?? ''));
        if ($dv === '') $dv = $dvCalc;
        elseif ($dv !== $dvCalc) {
            return redirect()->route('admin.trab.panel')->with('err',"DV incorrecto. Para $rutBase corresponde $dvCalc.")->withInput();
        }

        if (!Schema::hasTable('trabajador')) {
            return redirect()->route('admin.trab.panel')->with('err','No existe tabla trabajador.')->withInput();
        }

        $cols = Schema::getColumnListing('trabajador');
        $row  = [];
        $set = function(string $col, $val) use (&$row,$cols){ if (in_array($col,$cols,true)) $row[$col] = $val; };

        $set('id_condominio', (int)$data['id_condominio']);
        $set('tipo',          $data['tipo'] ?? 'empleado');
        $set('nombres',       $data['nombres']);
        $set('apellidos',     $data['apellidos'] ?? null);
        $set('cargo',         $data['cargo'] ?? null);
        $set('email',         $data['email'] ?? null);
        $set('telefono',      $data['telefono'] ?? null);
        $set('rut_base',      $rutBase);
        $set('rut_dv',        $dv);
        $set('created_at',    now());
        $set('updated_at',    now());

        try {
            $id = DB::table('trabajador')->insertGetId($row);
            AuditoriaService::log('trabajador', $id, 'CREAR', $row);
            return redirect()->route('admin.trab.panel')->with('ok', "Trabajador #$id creado correctamente.");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            AuditoriaService::log('trabajador', 0, 'ERROR_CREAR', ['msg'=>$msg,'payload'=>$row]);
            if (stripos($msg, 'RUT') !== false || stripos($msg,'1644') !== false) {
                return redirect()->route('admin.trab.panel')->with('err','RUT inválido según validación de BD.')->withInput();
            }
            if (stripos($msg,'duplicate') !== false || stripos($msg,'1062') !== false) {
                return redirect()->route('admin.trab.panel')->with('err','Ya existe un trabajador con ese RUT.')->withInput();
            }
            return redirect()->route('admin.trab.panel')->with('err','No se pudo crear el trabajador: '.$msg)->withInput();
        }
    }

    /** DV módulo 11 */
    private function rutDv(string $rutBase): string
    {
        $s = 1; $m = 0;
        for (; $rutBase; $rutBase = intval($rutBase/10)) {
            $s = ($s + $rutBase % 10 * (9 - $m++ % 6)) % 11;
        }
        return $s === 0 ? 'K' : (string)($s - 1);
    }

    /** Helpers para contratos */
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
        // devuelve una columna segura que exista para evitar SQL inválido
        return $cols[0] ?? 'id';
    }
}
