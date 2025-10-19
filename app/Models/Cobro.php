<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cobro extends Model
{
    use HasFactory;
    protected $primaryKey = 'id_cobro';

    protected $fillable = [
        'id_unidad',
        'periodo',
        'id_cobro_estado',
        'total_cargos',
        'monto_fondo_reserva',
    ];

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'cobro_id', 'id_cobro');
    }

    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }
}