<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'descripcion',
        'monto',
        'tipo',
        'fecha_gasto',
        'periodo_gasto',
        'documento_path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // 👇 ESTA ES LA LÍNEA QUE ARREGLA TODO 👇
        // Le decimos a Laravel que estas columnas son de tipo 'date'.
        'fecha_gasto' => 'date',
        'periodo_gasto' => 'date',
    ];
}