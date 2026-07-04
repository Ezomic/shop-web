<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->id }}</title>
</head>
<body style="font-family: sans-serif; color: #111; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 20px;">{{ __('mail.order_paid_heading') }}</h1>
    <p>{{ __('mail.order_paid_intro', ['name' => $order->customer->name]) }}</p>

    <table style="width: 100%; border-collapse: collapse; margin: 24px 0;">
        <thead>
            <tr>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #eee;">Product</th>
                <th style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $item->product_name }}</td>
                <td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">
                    € {{ number_format($item->price / 100, 2, ',', '.') }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if ($order->discount > 0)
            <tr>
                <td style="padding: 8px; text-align: right;">{{ __('shop.discount') }}</td>
                <td style="padding: 8px; text-align: right;">- € {{ number_format($order->discount / 100, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding: 8px; text-align: right; font-weight: bold;">{{ __('shop.total') }}</td>
                <td style="padding: 8px; text-align: right; font-weight: bold;">{{ $order->totalFormatted() }}</td>
            </tr>
        </tfoot>
    </table>

    <h2 style="font-size: 16px;">{{ __('mail.download_links') }}</h2>
    <ul>
        @foreach ($order->items as $item)
        <li style="margin-bottom: 8px;">
            <strong>{{ $item->product_name }}</strong><br>
            <a href="{{ $item->download->url() }}">{{ __('shop.download') }}</a>
        </li>
        @endforeach
    </ul>

    <p style="color: #666; font-size: 12px; margin-top: 32px;">{{ __('mail.order_footer') }}</p>
</body>
</html>
