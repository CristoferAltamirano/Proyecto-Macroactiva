<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Condominio extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_condominio';
    protected $fillable = ['nombre'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_condominio', 'id_condominio');
    }
}