<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Proforma Invoice') }} {{ $purchaseOrder->reference }}</title>
    @include('procurement.templates.pdf.purchase-order-styles')
</head>
<body>
@php($singleCurrency = $totals->count() <= 1 ? ($totals->keys()->first() ?? $purchaseOrder->currency->code) : null)
@php($price = fn ($value) => number_format((float)$value, max(2, strlen(rtrim(substr(strrchr(number_format((float)$value, 4, '.', ''), '.'), 1), '0')))))
@php($quantity = fn ($value) => rtrim(rtrim(number_format((float)$value, 3), '0'), '.'))
@php($data = $purchaseOrder->data ?? [])
<htmlpagefooter name="footer">
    <div style="text-align: right; font-size: 8pt; color: #777;">{{ __('Proforma Invoice') }} {{ $purchaseOrder->reference }} · {{ __('Page :page of :pages', ['page' => '{PAGENO}', 'pages' => '{nbpg}']) }}</div>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

<table class="header">
    <tr>
        <td>
            <h1>{{ $seller?->name ?? $purchaseOrder->parent_name }}</h1>
            @if($seller?->address)
                <div class="muted">{!! nl2br(e($seller->address->formatted_address)) !!}</div>
            @endif
            <div class="muted">
                @if($seller?->phone){{ $seller->phone }}@endif
                @if($seller?->phone && $seller?->email) · @endif
                @if($seller?->email){{ $seller->email }}@endif
            </div>
        </td>
        <td class="right">
            <h1>{{ __('Proforma Invoice') }}</h1>
            <div><strong>{{ $purchaseOrder->reference }}</strong></div>
            <div class="muted">{{ __('Date') }}: {{ now()->translatedFormat('j M Y') }}</div>
            <div class="muted">{{ __('Purchase order date') }}: {{ ($purchaseOrder->submitted_at ?? $purchaseOrder->date)?->translatedFormat('j M Y') }}</div>
            <div class="muted">{{ __('Currency') }}: {{ $purchaseOrder->currency->code }}</div>
        </td>
    </tr>
</table>

<table class="blocks">
    <tr>
        <td>
            <div class="label">{{ __('Bill to') }}</div>
            <strong>{{ $buyer->name }}</strong><br>
            @if($buyer->address){!! nl2br(e($buyer->address->formatted_address)) !!}<br>@endif
            @if($buyer->email){{ $buyer->email }}<br>@endif
            @if($buyer->phone){{ $buyer->phone }}@endif
        </td>
        <td>
            <div class="label">{{ __('Deliver to') }}</div>
            {!! nl2br(e($deliveryAddress ?? $buyer->name)) !!}
        </td>
        <td>
            @if($purchaseOrder->isAgentOrder())
                <div class="label">{{ __('Supplier') }}</div>
                {{ trim($purchaseOrder->parent_code.' '.$purchaseOrder->parent_name) }}<br><br>
            @endif
            @if(!empty($data['incoterm']) || !empty($data['port_of_export']) || !empty($data['port_of_import']) || !empty($data['payment_terms']) || $purchaseOrder->estimated_received_at)
                <div class="label">{{ __('Terms') }}</div>
            @endif
            @if(!empty($data['incoterm'])){{ __('Incoterm') }}: {{ $data['incoterm'] }}<br>@endif
            @if(!empty($data['port_of_export'])){{ __('Port of export') }}: {{ $data['port_of_export'] }}<br>@endif
            @if(!empty($data['port_of_import'])){{ __('Port of import') }}: {{ $data['port_of_import'] }}<br>@endif
            @if(!empty($data['payment_terms'])){{ __('Payment') }}: {{ $data['payment_terms'] }}<br>@endif
            @if($purchaseOrder->estimated_received_at){{ __('Expected by') }}: {{ $purchaseOrder->estimated_received_at->translatedFormat('j M Y') }}@endif
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width: 4%">#</th>
        <th style="width: 14%">{{ __('Code') }}</th>
        <th>{{ __('Description') }}</th>
        <th class="right" style="width: 9%">{{ __('Units / carton') }}</th>
        <th class="right" style="width: 9%">{{ __('Cartons') }}</th>
        <th class="right" style="width: 8%">{{ __('Units') }}</th>
        <th class="right" style="width: 11%">{{ __('Unit price') }}{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
        <th class="right" style="width: 16%">{{ __('Amount') }}{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $index => $line)
        @include('procurement.templates.pdf.purchase-order-line')
    @endforeach
    @foreach($totals as $currencyCode => $total)
        <tr>
            <td colspan="7" class="right" style="font-weight: bold; border-bottom: none;">{{ __('Total') }} {{ $currencyCode }}</td>
            <td class="right" style="font-weight: bold; white-space: nowrap; border-top: 1px solid #bbb; border-bottom: none;">{{ number_format($total, 2) }}{{ $singleCurrency ? '' : ' '.$currencyCode }}</td>
        </tr>
    @endforeach
    @if($depositAmount > 0)
        <tr>
            <td colspan="7" class="right" style="border-bottom: none;">{{ __('Deposit due') }} {{ $purchaseOrder->currency->code }}</td>
            <td class="right" style="white-space: nowrap; border-bottom: none;">{{ number_format($depositAmount, 2) }}</td>
        </tr>
    @endif
    </tbody>
</table>

@if($agreedTerms->isNotEmpty())
    <div class="terms">
        <div class="label">{{ __('Agreed terms') }}</div>
        <table class="agreed-terms">
            @foreach($agreedTerms as $agreedTerm)
                <tr>
                    <td class="agreed-term">{{ $agreedTerm->term_type->label() }}{{ $agreedTerm->product_code ? ' ('.$agreedTerm->product_code.')' : '' }}</td>
                    <td>{{ $agreedTerm->valueLabel() }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

<div class="terms muted">{{ __('This proforma invoice is issued for the purchase order above to request payment. It is not a tax invoice.') }}</div>
</body>
</html>
