<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaMoraMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data // ['nombre','unidad','periodo','saldo','condominio','vencimiento']
    ) {}

    public function build()
    {
        return $this->subject('Recordatorio de pago (mora)')
            ->view('emails.alerta_mora');
    }
}
