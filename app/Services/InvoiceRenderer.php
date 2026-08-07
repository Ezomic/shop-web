<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Invoices are rendered on demand rather than stored as files, so there is no second artefact to
 * back up or keep in sync. Everything printed comes off the order snapshot, so editing a product
 * or changing the VAT rate never alters an invoice that was already issued.
 */
class InvoiceRenderer
{
    public function pdf(Order $order): PdfDocument
    {
        $order->loadMissing('customer', 'items');

        return Pdf::loadView('invoices.order', ['order' => $order])->setPaper('a4');
    }

    public function filename(Order $order): string
    {
        return 'invoice-'.($order->invoice_number ?? $order->id).'.pdf';
    }
}
