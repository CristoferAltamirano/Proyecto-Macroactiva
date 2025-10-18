<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobro_id',
        'unidad_id',
        'monto',
        'fecha_pago',
        'metodo_pago',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    /**
     * Un Pago pertenece a un Cobro.
     */
    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class);
    }

    /**
     * Un Pago pertenece a una Unidad.
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }
}