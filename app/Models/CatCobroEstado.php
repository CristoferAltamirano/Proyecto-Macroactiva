<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatCobroEstado extends Model
{
    use HasFactory;

    protected $table = 'cat_cobro_estado';
    protected $primaryKey = 'id_cobro_estado';
    public $timestamps = false;
}