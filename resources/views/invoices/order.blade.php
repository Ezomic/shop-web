@php
    $supplier = config('shop.supplier');
    $money = fn (int $cents): string => '€ '.number_format($cents / 100, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('invoice.title') }} {{ $order->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .lines th { text-align: left; border-bottom: 1px solid #333; padding: 6px 4px; }
        .lines td { padding: 6px 4px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        .totals td { padding: 3px 4px; }
        .totals .grand { font-weight: bold; border-top: 1px solid #333; }
        .footer { margin-top: 40px; color: #666; font-size: 10px; }
    </style>
</head>
<body>

<table class="meta">
    <tr>
        <td style="width: 55%;">
            <h1>{{ __('invoice.title') }}</h1>
            <div>{{ __('invoice.number') }}: <strong>{{ $order->invoice_number }}</strong></div>
            <div>{{ __('invoice.date') }}: {{ optional($order->invoiced_at)->format('d-m-Y') }}</div>
            <div>{{ __('invoice.order_reference') }}: #{{ $order->id }}</div>
        </td>
        <td>
            <strong>{{ $supplier['name'] }}</strong><br>
            @if ($supplier['address'])<div>{{ $supplier['address'] }}</div>@endif
            @if ($supplier['postcode_city'])<div>{{ $supplier['postcode_city'] }}</div>@endif
            @if ($supplier['country'])<div>{{ $supplier['country'] }}</div>@endif
            @if ($supplier['vat_number'])<div>{{ __('invoice.vat_number') }}: {{ $supplier['vat_number'] }}</div>@endif
            @if ($supplier['coc_number'])<div>{{ __('invoice.coc_number') }}: {{ $supplier['coc_number'] }}</div>@endif
        </td>
    </tr>
</table>

<p style="margin-top: 24px;">
    <strong>{{ __('invoice.billed_to') }}</strong><br>
    {{ $order->customer->name }}<br>
    {{ $order->customer->email }}
</p>

<table class="lines" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('invoice.description') }}</th>
            <th class="right">{{ __('invoice.net') }}</th>
            <th class="right">{{ __('invoice.vat_rate') }}</th>
            <th class="right">{{ __('invoice.vat') }}</th>
            <th class="right">{{ __('invoice.gross') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
        <tr>
            <td>{{ $item->product_name }}</td>
            <td class="right">{{ $money($item->net_price) }}</td>
            <td class="right">{{ $item->vat_rate }}%</td>
            <td class="right">{{ $money($item->vat_amount) }}</td>
            <td class="right">{{ $money($item->net_price + $item->vat_amount) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 12px; width: 45%; margin-left: 55%;">
    @if ($order->discount > 0)
    <tr>
        <td>{{ __('invoice.list_price') }}</td>
        <td class="right">{{ $money($order->subtotal) }}</td>
    </tr>
    <tr>
        <td>{{ __('invoice.discount') }}</td>
        <td class="right">- {{ $money($order->discount) }}</td>
    </tr>
    @endif
    <tr>
        <td>{{ __('invoice.net_total') }}</td>
        <td class="right">{{ $money($order->net_total) }}</td>
    </tr>
    <tr>
        <td>{{ __('invoice.vat') }} {{ $order->vat_rate }}%</td>
        <td class="right">{{ $money($order->vat_amount) }}</td>
    </tr>
    <tr class="grand">
        <td>{{ __('invoice.total') }}</td>
        <td class="right">{{ $money($order->total) }}</td>
    </tr>
</table>

<p class="footer">
    {{ __('invoice.paid_note', ['date' => optional($order->paid_at)->format('d-m-Y')]) }}
    @if ($supplier['email']) {{ $supplier['email'] }} @endif
</p>

</body>
</html>
