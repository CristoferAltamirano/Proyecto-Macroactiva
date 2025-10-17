<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;
    const CREATED_AT = 'creado_at';
    const UPDATED_AT = null;

    protected $fillable = ['tipo_usuario','rut_base','rut_dv','nombres','apellidos','email','telefono','direccion','pass_hash','activo'];
    protected $hidden = ['pass_hash'];

    public function getAuthPassword() { return $this->pass_hash; }
    public function is($role): bool { return $this->tipo_usuario === $role; }
}
