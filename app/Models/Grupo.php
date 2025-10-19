<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_grupo';
    protected $fillable = ['nombre', 'id_condominio', 'tipo'];

    public function condominio()
    {
        return $this->belongsTo(Condominio::class, 'id_condominio', 'id_condominio');
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class, 'id_grupo', 'id_grupo');
    }
}