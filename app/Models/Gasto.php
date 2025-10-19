<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    use HasFactory;

    // Si tu PK real es 'id', elimina esta línea. Si es 'id_gasto', déjala.
    protected $primaryKey = 'id_gasto';

    protected $fillable = [
        'condominio_id',
        'periodo',
        'id_gasto_categ',
        'neto',
        'iva',
        'descripcion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_venc' => 'date',
    ];

    public function getTotalAttribute()
    {
        return round(($this->attributes['neto'] ?? 0) + ($this->attributes['iva'] ?? 0), 2);
    }
}
