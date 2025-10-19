<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatMetodoPago extends Model
{
    use HasFactory;

    protected $table = 'cat_metodo_pago';
    protected $primaryKey = 'id_metodo_pago';
    public $timestamps = false;
}