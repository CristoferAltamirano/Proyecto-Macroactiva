<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Rules\RutValido;
use App\Services\AuditoriaService;

class ProveedorController extends Controller
{
    /**
     * Devuelve el nombre de la columna de condominio en 'proveedor' (si existe).
     * Prioriza 'id_condominio' y luego 'condominio_id'. Si no existe, retorna null.
     */
    private function proveedorCondoColumn(): ?string
    {
        if (!Schema::hasTable('proveedor')) return null;

        if (Schema::hasColumn('proveedor', 'id_condominio')) {
            return 'id_condominio';
        }
        if (Schema::hasColumn('proveedor', 'condominio_id')) {
            return 'condominio_id';
        }
        return null;
    }

    public function index()
    {
        $yo    = auth()->user();
        $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
        $isSA  = $rol === 'super_admin';
        $ctxId = (int) (session('ctx_condo_id') ?? 0);

        $condoCol = $this->proveedorCondoColumn();

        // SELECT *: si existen columnas nuevas, la vista podrá mostrarlas.
        $q = DB::table('proveedor');

        // Anti info cruzada: admins solo ven su condominio si hay columna disponible
        if (!$isSA && $ctxId > 0 && $condoCol) {
            $q->where($condoCol, $ctxId);
        }

        $proveedores = $q->orderBy('nombre')
            ->limit(100)
            ->get();

        return view('proveedores_index', compact('proveedores'));
    }

    public function store(Request $r)
    {
        $yo    = auth()->user();
        $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
        $isSA  = $rol === 'super_admin';
        $ctxId = (int) (session('ctx_condo_id') ?? 0);

        // Validación base + campos nuevos (opcionales)
        $d = $r->validate([
            'tipo'                => ['required','in:persona,empresa'],
            'rut_base'            => ['required','integer','min:1'],
            'rut_dv'              => ['required', new RutValido('rut_base')],
            'nombre'              => ['required','string','max:140'],
            'giro'                => ['nullable','string','max:140'],
            'email'               => ['nullable','email','max:140'],
            'telefono'            => ['nullable','string','max:40'],

            // Contacto de empresa
            'contacto_nombre'     => ['nullable','string','max:140'],
            'contacto_email'      => ['nullable','email','max:140'],
            'contacto_telefono'   => ['nullable','string','max:40'],

            // Persona que atiende (ejecutivo)
            'ejecutivo_nombre'    => ['nullable','string','max:140'],
            'ejecutivo_email'     => ['nullable','email','max:140'],
            'ejecutivo_telefono'  => ['nullable','string','max:40'],
        ]);

        // Normalizaciones suaves
        $d['rut_dv'] = strtoupper(trim($d['rut_dv']));
        if (!empty($d['email']))           $d['email']           = strtolower(trim($d['email']));
        if (!empty($d['contacto_email']))  $d['contacto_email']  = strtolower(trim($d['contacto_email']));
        if (!empty($d['ejecutivo_email'])) $d['ejecutivo_email'] = strtolower(trim($d['ejecutivo_email']));

        // Descubrimos si la tabla tiene columna de condominio
        $condoCol = $this->proveedorCondoColumn();

        // Columnas reales de la tabla
        $cols = Schema::getColumnListing('proveedor');

        // Payload sólo con columnas existentes
        $payload = array_intersect_key($d, array_flip($cols));

        // Si es ADMIN y existe columna de condominio, setear automáticamente el ctx
        if (!$isSA && $condoCol && $ctxId > 0) {
            $payload[$condoCol] = $ctxId;
        }

        // Armamos llave de upsert:
        // - por defecto: rut_base + rut_dv
        // - si hay columna de condominio y viene seteada, la incluimos para permitir mismo RUT en diferentes condominios
        $match = [
            'rut_base' => $d['rut_base'],
            'rut_dv'   => $d['rut_dv'],
        ];
        if ($condoCol && array_key_exists($condoCol, $payload)) {
            $match[$condoCol] = $payload[$condoCol];
        }

        // Upsert
        DB::table('proveedor')->updateOrInsert($match, $payload);

        // Obtener ID para auditoría (respetando match con condominio si aplica)
        $idQuery = DB::table('proveedor')
            ->where('rut_base', $d['rut_base'])
            ->where('rut_dv',   $d['rut_dv']);

        if ($condoCol && array_key_exists($condoCol, $payload)) {
            $idQuery->where($condoCol, $payload[$condoCol]);
        }

        $id = (int) $idQuery->value('id_proveedor');

        // Auditoría
        AuditoriaService::log('proveedor', $id, 'GUARDAR', $payload);

        return back()->with('ok', 'Proveedor guardado.');
    }
}
