<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Unidad extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'unidades';
    protected $primaryKey = 'id_unidad';

    protected $fillable = [
        'id_grupo',
        'codigo',
        'coef_prop',
        'propietario',
        'rut_propietario',
        'email_propietario',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->rut_propietario;
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }
}