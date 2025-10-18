<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cobro extends Model
{
    use HasFactory;

    protected $fillable = [
        'unidad_id',
        'periodo',
        'monto_gasto_comun',
        'monto_fondo_reserva',
        'monto_multas',
        'monto_total',
        'estado',
    ];

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }
}