<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RutValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/^\d{1,9}-[0-9Kk]$/', $value)) { $fail('RUT inválido.'); return; }
        [$base,$dv] = explode('-', $value);
        $m=2; $s=0;
        foreach(array_reverse(str_split($base)) as $d){ $s += $d*$m; $m = $m==7?2:$m+1; }
        $r = 11 - ($s % 11);
        $calc = $r==11 ? '0' : ($r==10 ? 'K' : (string)$r);
        if (strtoupper($dv) !== $calc) $fail('RUT inválido.');
    }
}
