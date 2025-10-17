<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AvisoCobroMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data // ['nombre','unidad','periodo','monto','url_pdf','condominio']
    ) {}

    public function build()
    {
        return $this->subject('Aviso de cobro - '.$this->data['periodo'])
            ->view('emails.aviso_cobro');
    }
}
