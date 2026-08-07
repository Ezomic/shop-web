@php
    $money = fn (int $cents): string => '€ '.number_format($cents / 100, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>EU VAT threshold</title></head>
<body style="font-family: sans-serif; color: #111; max-width: 600px; margin: 0 auto; padding: 24px;">

@if ($isHardLimit)
    <h1 style="font-size: 20px;">Cross border sales have passed the EU VAT threshold</h1>
    <p>
        Sales to customers outside {{ config('shop.vat.home_country') }} reached
        <strong>{{ $money($total) }}</strong> in {{ $year }}, past the
        {{ $money($threshold) }} threshold.
    </p>
    <p>
        From here VAT is due at the rate of the customer's country rather than the Dutch rate, and
        that has to be declared through the Union One Stop Shop. The shop is still charging a
        single rate, so this needs attention now rather than at the next return.
    </p>
@else
    <h1 style="font-size: 20px;">Cross border sales are approaching the EU VAT threshold</h1>
    <p>
        Sales to customers outside {{ config('shop.vat.home_country') }} reached
        <strong>{{ $money($total) }}</strong> in {{ $year }}. The threshold that forces
        destination based VAT and an OSS registration is {{ $money(config('shop.vat.threshold')) }}.
    </p>
    <p>
        This is the warning shot, deliberately early, so the work can be done before it matters
        rather than discovered afterwards.
    </p>
@endif

<p style="color: #666; font-size: 12px; margin-top: 32px;">
    Background and the plan are on SHOP-18. You will not get this message again for
    {{ $money($threshold) }} in {{ $year }}.
</p>

</body>
</html>
