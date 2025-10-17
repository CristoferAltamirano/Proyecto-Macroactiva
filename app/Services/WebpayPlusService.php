<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WebpayPlusService
{
    /**
     * Crea transacción. Si existe el SDK de Transbank, lo usa; si no,
     * hace un fallback de integración simple (para desarrollo).
     */
    public static function create(string $buyOrder, string $sessionId, int|float $amount, string $returnUrl): array
    {
        // SDK oficial disponible
        if (class_exists(\Transbank\Webpay\WebpayPlus\Transaction::class)) {
            $tx = new \Transbank\Webpay\WebpayPlus\Transaction;
            $resp = $tx->create($buyOrder, $sessionId, (int) round($amount), $returnUrl);
            return ['url' => $resp->getUrl(), 'token' => $resp->getToken()];
        }

        // Fallback (integración / demo)
        $token = 'TEST-' . bin2hex(random_bytes(12));
        $url = route('webpay.return');
        Log::info("[WebpayDemo] create", compact('buyOrder','sessionId','amount','token'));
        return ['url'=>$url, 'token'=>$token];
    }

    /**
     * Commit/confirm de token. Devuelve arreglo estándar.
     * Si no hay SDK, aprueba todo token TEST-* como éxito.
     */
    public static function commit(string $token): array
    {
        if (class_exists(\Transbank\Webpay\WebpayPlus\Transaction::class)) {
            $tx = new \Transbank\Webpay\WebpayPlus\Transaction;
            $resp = $tx->commit($token);

            return [
                'status'            => $resp->getStatus(),                 // "AUTHORIZED" / "FAILED"
                'responseCode'      => $resp->getResponseCode(),           // 0 ok
                'buyOrder'          => $resp->getBuyOrder(),
                'amount'            => $resp->getAmount(),
                'authorizationCode' => $resp->getAuthorizationCode(),
                'paymentTypeCode'   => $resp->getPaymentTypeCode(),
                'installments'      => $resp->getInstallmentsNumber(),
                'cardNumber'        => method_exists($resp,'getCardNumber') ? $resp->getCardNumber() : null,
            ];
        }

        // Fallback demo
        $ok = str_starts_with($token, 'TEST-');
        return [
            'status'            => $ok ? 'AUTHORIZED' : 'FAILED',
            'responseCode'      => $ok ? 0 : -1,
            'buyOrder'          => 'BO-'.$token,
            'amount'            => 0,
            'authorizationCode' => $ok ? '999999' : null,
            'paymentTypeCode'   => 'VD',
            'installments'      => 0,
            'cardNumber'        => null,
        ];
    }
}
