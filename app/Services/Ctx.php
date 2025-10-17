<?php

namespace App\Services;

class Ctx
{
    public static function condoId(): ?int
    {
        $v = session('ctx_condominio');
        return $v ? (int)$v : null;
    }
}
