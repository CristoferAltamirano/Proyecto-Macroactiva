<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReciboPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data // ['nombre','unidad','periodo','monto','url_recibo','condominio','fecha']
    ) {}

    public function build()
    {
        return $this->subject('Recibo de pago')
            ->view('emails.recibo_pago');
    }
}
