<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Services\InvoiceRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued on purpose: this is sent from inside the Stripe and Mollie webhooks, and a slow or
 * failing mail host must not turn into a 500 that makes the provider retry the whole delivery.
 */
class OrderPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.order_paid_subject', ['id' => $this->order->id]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-paid',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->order->invoice_number === null) {
            return [];
        }

        $invoices = app(InvoiceRenderer::class);

        return [
            Attachment::fromData(
                fn (): string => $invoices->pdf($this->order)->output(),
                $invoices->filename($this->order),
            )->withMime('application/pdf'),
        ];
    }
}
