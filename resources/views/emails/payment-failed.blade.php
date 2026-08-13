<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><title>{{ __('mail.payment_failed_subject', ['id' => $order->id]) }}</title></head>
<body style="font-family: sans-serif; color: #111; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 20px;">{{ __('mail.payment_failed_heading') }}</h1>

    <p>{{ __('mail.payment_failed_intro', ['name' => $order->customer->name]) }}</p>

    <ul>
        @foreach ($order->items as $item)
        <li>{{ $item->product_name }}</li>
        @endforeach
    </ul>

    <p style="margin: 24px 0;">
        <a href="{{ $retryUrl }}" style="background: #111; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none;">
            {{ __('mail.payment_failed_retry') }}
        </a>
    </p>

    <p style="color: #666; font-size: 12px;">{{ __('mail.payment_failed_footer') }}</p>
</body>
</html>
