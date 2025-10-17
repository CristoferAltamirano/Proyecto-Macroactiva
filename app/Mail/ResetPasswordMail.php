<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $link;

    public function __construct(string $link) { $this->link = $link; }

    public function build()
    {
        return $this->subject('Recuperación de contraseña')
            ->view('emails.reset_password')
            ->with(['link'=>$this->link]);
    }
}
