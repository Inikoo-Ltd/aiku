<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Claim slip :reference', ['reference' => $claim->reference]) }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; margin: 0; padding: 24px; }
        .slip { max-width: 640px; margin: 0 auto; border: 2px dashed #999; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #666; font-size: 13px; }
        .barcode { margin: 16px 0; }
        .barcode svg { max-width: 100%; height: 60px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        th { text-align: left; white-space: nowrap; border-bottom: 1px solid #ccc; padding: 4px 8px 4px 0; color: #666; font-weight: 600; }
        td { border-bottom: 1px solid #eee; padding: 4px 8px 4px 0; vertical-align: top; }
        .code { white-space: nowrap; }
        .address { white-space: pre-line; font-size: 14px; border: 1px solid #ccc; padding: 12px; margin-top: 8px; }
        .print { margin: 16px auto 0; display: block; padding: 8px 16px; font-size: 14px; cursor: pointer; background: #fff; border: 1px solid #999; border-radius: 4px; }
        @media print { body { padding: 0; } .print { display: none; } }
    </style>
</head>
<body>
<div class="slip">
    <h1>{{ __('Claim slip :reference', ['reference' => $claim->reference]) }}</h1>
    <p class="muted">{{ __('Order :order', ['order' => $claim->order?->reference]) }} · {{ $claim->customer?->name }}</p>

    <div class="barcode">{!! $barcode !!}</div>

    <p>{{ __('Please put this slip in the parcel with the items below, so we can match your return to your claim.') }}</p>

    <table>
        <thead>
        <tr>
            <th>{{ __('Product') }}</th>
            <th>{{ __('Quantity') }}</th>
            <th>{{ __('Reason') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td><span class="code">{{ $line['code'] }}</span> {{ $line['name'] }}</td>
                <td>{{ $line['quantity'] }}</td>
                <td>{{ $line['reason'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($returnAddress)
        <p><strong>{{ __('Send the parcel to:') }}</strong></p>
        <div class="address">{{ $returnTo }}
{{ $returnAddress }}</div>
    @endif
</div>
<button type="button" class="print" onclick="window.print()">{{ __('Print') }}</button>
</body>
</html>
