<?php

namespace App\Mail;

use App\Models\Cobro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NuevoCobroDisponible extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * La instancia del cobro.
     *
     * @var \App\Models\Cobro
     */
    public $cobro;

    /**
     * Create a new message instance.
     */
    public function __construct(Cobro $cobro)
    {
        $this->cobro = $cobro;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo Cobro Disponible para tu Unidad',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cobros.nuevo',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}