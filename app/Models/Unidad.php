<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Se asegura que pueda iniciar sesión
use Illuminate\Notifications\Notifiable; // Necesario para modelos "Authenticatable"

class Unidad extends Authenticatable
{
    use HasFactory, Notifiable; // Añadimos Notifiable

    /**
     * El nombre de la tabla asociada con el modelo.
     * @var string
     */
    protected $table = 'unidades';

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'numero',
        'residente',
        'propietario',
        'email',
        'password',
        'telefono',
        'prorrateo',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // 👇 ESTA ES LA LÍNEA MÁGICA QUE ARREGLA EL LOGIN DEL RESIDENTE 👇
        // Le dice a Laravel que la columna 'password' siempre debe ser tratada como un hash.
        return [
            'password' => 'hashed',
        ];
    }
}