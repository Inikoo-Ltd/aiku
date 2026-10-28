<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Combined Purchase Order {{ $agent->name }}</title>
    @include('procurement.templates.pdf.purchase-order-styles')
    <style>
        table.lines th { font-size: 6.5pt; padding: 4px 4px; white-space: nowrap; }
        table.lines td { font-size: 8pt; padding: 3px 4px; }
        table.lines tr.supplier td { background: #e8e8e8; font-weight: bold; border-top: 1px solid #999; border-bottom: 1px solid #999; padding: 4px; page-break-after: avoid; }
    </style>
</head>
<body>
@php($singleCurrency = $totals->count() <= 1 ? $totals->keys()->first() : null)
@php($price = fn ($value) => number_format((float)$value, max(2, strlen(rtrim(substr(strrchr(number_format((float)$value, 4, '.', ''), '.'), 1), '0')))))
@php($quantity = fn ($value) => rtrim(rtrim(number_format((float)$value, 3), '0'), '.'))
@php($images = null)
<htmlpagefooter name="footer">
    <div style="text-align: right; font-size: 8pt; color: #777;">{{ $agent->name }} · {{ $date->format('j M Y') }} · Page {PAGENO} of {nbpg}</div>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

<table class="header">
    <tr>
        <td>
            <h1>{{ $organisation->name }}</h1>
            @if($organisation->address)
                <div class="muted">{!! nl2br(e($organisation->address->formatted_address)) !!}</div>
            @endif
        </td>
        <td class="right">
            <h1>Combined Purchase Order</h1>
            <div><strong>{{ $agent->name }}</strong></div>
            <div class="muted">Date: {{ $date->format('j M Y') }}</div>
            <div class="muted">{{ $sections->count() }} suppliers · {{ $sections->sum(fn ($section) => $section['lines']->count()) }} products</div>
        </td>
    </tr>
</table>

<table class="blocks">
    <tr>
        <td>
            <div class="label">Agent</div>
            <strong>{{ $agent->name }}</strong><br>
            @if($agent->organisation?->address){!! nl2br(e($agent->organisation->address->formatted_address)) !!}<br>@endif
            @if($agent->organisation?->email){{ $agent->organisation->email }}<br>@endif
            @if($agent->organisation?->phone){{ $agent->organisation->phone }}@endif
        </td>
        <td>
            <div class="label">Deliver to</div>
            {!! nl2br(e($deliveryAddress ?? $organisation->name)) !!}
        </td>
        <td></td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width: 4%">#</th>
        <th style="width: 14%">Code</th>
        <th>Description</th>
        <th class="right" style="width: 9%">Units/ctn</th>
        <th class="right" style="width: 9%">Cartons</th>
        <th class="right" style="width: 8%">Units</th>
        <th class="right" style="width: 11%">Unit price{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
        <th class="right" style="width: 13%">Amount{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($sections as $section)
        @php($purchaseOrder = $section['purchaseOrder'])
        <tr class="supplier">
            <td colspan="8">
                {{ $section['supplier']?->code }} {{ $section['supplier']?->name ?? $purchaseOrder->parent_name }}
                @if($section['supplier']?->phone) · {{ $section['supplier']->phone }}@endif
                <span style="font-weight: normal;"> · {{ $purchaseOrder->reference }} · {{ $purchaseOrder->submitted_at?->format('j M Y') }}</span>
            </td>
        </tr>
        @foreach($section['lines'] as $index => $line)
            @include('procurement.templates.pdf.purchase-order-line')
        @endforeach
    @endforeach
    </tbody>
</table>

<table class="totals">
    @foreach($totals as $currencyCode => $total)
        <tr class="grand">
            <td>Total {{ $currencyCode }}</td>
            <td class="right">{{ number_format($total, 2) }} {{ $currencyCode }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
