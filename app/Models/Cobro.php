<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cobro extends Model
{
    use HasFactory;

    protected $fillable = [
        'unidad_id', 'periodo', 'monto_gasto_comun', 'monto_fondo_reserva',
        'monto_multas', 'monto_total', 'estado'
    ];

    protected $casts = [
        'periodo' => 'date',
    ];

    /**
     * Define la relación: Un Cobro pertenece a una Unidad.
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }
}