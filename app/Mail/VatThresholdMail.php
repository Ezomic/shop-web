<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\Throttled;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VatThresholdMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, Throttled;

    public function __construct(
        public readonly int $threshold,
        public readonly int $total,
        public readonly int $year,
        public readonly bool $isHardLimit,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isHardLimit
                ? 'Cross border sales have passed the EU VAT threshold'
                : 'Cross border sales are approaching the EU VAT threshold',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.vat-threshold');
    }
}
