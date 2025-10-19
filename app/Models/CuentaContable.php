<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaContable extends Model
{
    use HasFactory;

    protected $table = 'cuenta_contable';
    protected $primaryKey = 'id_cta_contable';
}