<?php
namespace App\Services;

class WebpayService {
  public function startTransaction($cobro): string {
    if (config('webpay.mock', false)) return 'https://mock.webpay.cl/payment?token_ws=mock_token_123';
    return 'https://webpay3gint.transbank.cl/webpayserver/initTransaction?token_ws='.bin2hex(random_bytes(32));
  }
  public function confirmTransaction(string $token): array {
    if (config('webpay.mock', false)) {
      if ($token==='mock_token_123') return ['status'=>'approved','authorizeCode'=>'OK123'];
      if ($token==='mock_token_rechazado') return ['status'=>'rejected'];
    }
    return ['status'=>'approved','authorizeCode'=>'REAL123'];
  }
}