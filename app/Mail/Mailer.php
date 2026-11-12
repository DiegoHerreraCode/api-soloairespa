<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class Mailer extends Mailable
{
    use Queueable, SerializesModels;
    protected string $asunto;
    protected string $vista;
    protected array $data;
    protected array $adjuntos;

    public function __construct($asunto, $vista, $data, $adjuntos = [])
    {
        $this->asunto = $asunto;
        $this->vista = $vista;
        $this->data = $data;
        $this->adjuntos = $adjuntos;
    }

    /*

       public function __construct(
        protected string $asunto,
        protected string $vista,
        protected array $data,
        protected array $adjuntos = []
    ) {}

    */

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->vista,
            with: $this->data,
        );
    }

    public function attachments(): array
    {
        $adjuntos = [];

        if (!empty($this->adjuntos)) {
            $adjuntos = array_map(function ($adjunto) {
                if (is_array($adjunto) && isset($adjunto['data'])) {
                    return Attachment::fromData(
                        fn () => $adjunto['data'],
                        $adjunto['name'] ?? 'documento.pdf'
                    )->withMime($adjunto['mime'] ?? 'application/pdf');
                }

                if (is_string($adjunto)) {
                    return Attachment::fromPath($adjunto);
                }

                return $adjunto;
            }, $this->adjuntos);
        }

        return $adjuntos;
    }
}
