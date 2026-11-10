<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 11pt; }
        td { vertical-align: top; }
        .items td { border: 0.1mm solid #000; padding: 6px 8px; }
        .items thead td { background-color: #EEEEEE; }
    </style>
</head>
<body>
@foreach($slips as $slip)
    @if(!$loop->first)<pagebreak/>@endif
    <div style="font-size: 20pt; font-weight: bold;">{{ $slip['order']->reference }}</div>
    <div style="font-size: 13pt; margin-top: 2mm;">{{ $slip['order']->shop->name }}</div>
    <div style="margin-top: 2mm;">{{ __('Customer') }}: <b>{{ $slip['order']->customer?->name }}</b></div>
    @if($slip['order']->deliveryAddress?->contact_name || $slip['order']->deliveryAddress?->organization)
        <div>{{ __('Deliver to') }}: {{ collect([$slip['order']->deliveryAddress->contact_name, $slip['order']->deliveryAddress->organization])->filter()->implode(', ') }}</div>
    @endif
    <div style="margin-top: 2mm; font-size: 9pt; color: #555;">{{ __('Filled on :job_order', ['job_order' => $jobOrder->reference]) }} · {{ __('Printed') }}: {{ $printedAt }}</div>

    <table class="items" width="100%" style="margin-top: 5mm; border-collapse: collapse;">
        <thead>
        <tr>
            <td style="width: 35%;">{{ __('Code') }}</td>
            <td>{{ __('Description') }}</td>
            <td style="width: 15%; text-align: right;">{{ __('Quantity') }}</td>
        </tr>
        </thead>
        <tbody>
        @foreach($slip['lines'] as $line)
            <tr>
                <td>{{ $line['code'] }}</td>
                <td>{{ $line['name'] }}</td>
                <td style="text-align: right; font-weight: bold;">{{ $line['quantity'] + 0 }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endforeach
</body>
</html>
