<?php

namespace App\Mail\Transport;

use GuzzleHttp\Client;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class SendGridApiTransport extends AbstractTransport
{
    protected Client $client;
    protected string $key;

    public function __construct(string $key)
    {
        parent::__construct();
        $this->key = $key;
        $this->client = new Client();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $personalizations = [];
        $toAddresses = [];
        foreach ($email->getTo() as $address) {
            $toAddresses[] = [
                'email' => $address->getAddress(),
                'name' => $address->getName() ?: null,
            ];
        }

        $p = ['to' => $toAddresses];

        if ($email->getCc()) {
            $ccAddresses = [];
            foreach ($email->getCc() as $address) {
                $ccAddresses[] = [
                    'email' => $address->getAddress(),
                    'name' => $address->getName() ?: null,
                ];
            }
            $p['cc'] = $ccAddresses;
        }

        if ($email->getBcc()) {
            $bccAddresses = [];
            foreach ($email->getBcc() as $address) {
                $bccAddresses[] = [
                    'email' => $address->getAddress(),
                    'name' => $address->getName() ?: null,
                ];
            }
            $p['bcc'] = $bccAddresses;
        }

        $p['subject'] = $email->getSubject();
        $personalizations[] = array_filter($p);

        $fromAddress = $email->getFrom()[0] ?? null;

        $content = [];
        if ($email->getTextBody()) {
            $content[] = [
                'type' => 'text/plain',
                'value' => $email->getTextBody(),
            ];
        }

        if ($email->getHtmlBody()) {
            $content[] = [
                'type' => 'text/html',
                'value' => $email->getHtmlBody(),
            ];
        }

        if (empty($content)) {
            $content[] = [
                'type' => 'text/html',
                'value' => ' ',
            ];
        }

        $payload = [
            'personalizations' => $personalizations,
            'from' => [
                'email' => $fromAddress ? $fromAddress->getAddress() : config('mail.from.address'),
                'name' => $fromAddress ? ($fromAddress->getName() ?: config('mail.from.name')) : config('mail.from.name'),
            ],
            'content' => $content,
        ];

        // Adjuntos
        if (!empty($email->getAttachments())) {
            $attachments = [];
            foreach ($email->getAttachments() as $attachment) {
                $bodyContent = '';
                try {
                    $bodyContent = $attachment->getBody();
                } catch (\Throwable $e) {
                    try {
                        $bodyContent = $attachment->bodyToString();
                    } catch (\Throwable $e2) {
                        $bodyContent = '';
                    }
                }

                // Si getBody() devolvió una cadena cruda o si ya venía codificada en base64
                // Verificamos si ya está codificada en base64 para no hacer doble encode
                $base64Content = '';
                if (!empty($bodyContent)) {
                    $decoded = base64_decode(trim($bodyContent), true);
                    if ($decoded !== false && base64_encode($decoded) === trim($bodyContent)) {
                        // Ya es una cadena base64 válida (como la que produce Symfony DataPart por defecto)
                        $base64Content = trim($bodyContent);
                    } else {
                        // Son bytes crudos binarios
                        $base64Content = base64_encode($bodyContent);
                    }
                }

                $attachments[] = [
                    'content' => $base64Content,
                    'filename' => $attachment->getFilename(),
                    'type' => $attachment->getContentType() ?: 'application/pdf',
                    'disposition' => 'attachment',
                ];
            }
            $payload['attachments'] = $attachments;
        }

        $this->client->post('https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->key,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
    }

    public function __toString(): string
    {
        return 'sendgrid-api';
    }
}

