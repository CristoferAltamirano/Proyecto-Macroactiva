<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_gasto';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'condominio_id',
        'periodo',
        'id_gasto_categ',
        'neto',
        'iva',
        'descripcion',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_venc' => 'date',
    ];

    public function getTotalAttribute()
    {
        // The 'total' column is a generated column in the database.
        // This accessor provides a fallback for when the model instance
        // hasn't been refreshed from the database.
        return round(($this->attributes['neto'] ?? 0) + ($this->attributes['iva'] ?? 0), 2);
    }
}