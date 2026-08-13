<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OrderPaymentFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.payment_failed_subject', ['id' => $this->order->id]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-failed', with: [
            // Signed, because the customer clicks this out of an inbox with no session.
            'retryUrl' => URL::temporarySignedRoute(
                'checkout.retry',
                now()->addDays(7),
                ['order' => $this->order->id],
            ),
        ]);
    }
}
