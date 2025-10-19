<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GastoCategoria extends Model
{
    use HasFactory;

    protected $table = 'gasto_categoria';
    protected $primaryKey = 'id_gasto_categ';
}