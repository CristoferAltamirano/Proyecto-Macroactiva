<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_pago';

    protected $fillable = [
        'cobro_id',
        'id_unidad',
        'monto_pagado',
        'fecha_pago',
        'id_metodo_pago',
        'webpay_token',
    ];

    public function cobro()
    {
        return $this->belongsTo(Cobro::class, 'cobro_id', 'id_cobro');
    }
}