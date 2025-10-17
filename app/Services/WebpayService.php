<?php

namespace App\Services;

use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\WebpayPlus\WebpayPlus;

class WebpayService
{
    public static function tx(): Transaction
    {
        $env = env('WEBPAY_ENV', 'integration');

        if ($env === 'production') {
            $cc  = env('WEBPAY_COMMERCE_CODE');
            $key = env('WEBPAY_API_KEY');
            WebpayPlus::configureForProduction($cc, $key);
        } else {
            WebpayPlus::configureForIntegration();
        }
        return new Transaction();
    }

    public static function returnUrl(): string
    {
        return env('WEBPAY_RETURN_URL', url('/pagos/webpay/return'));
    }

    public static function notifyUrl(): string
    {
        return env('WEBPAY_NOTIFY_URL', url('/pagos/webpay/notify'));
    }
}
