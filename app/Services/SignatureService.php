<?php

namespace App\Services;

class SignatureService
{
    public static function make(array $payload): string {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) $key = base64_decode(substr($key,7));
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $key, true)), '+/', '-_'), '=');
    }

    public static function verify(array $payload, string $sig): bool {
        return hash_equals(self::make($payload), $sig);
    }
}
