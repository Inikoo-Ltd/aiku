<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Combined Purchase Order') }} {{ $agent->name }}</title>
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
    <div style="text-align: right; font-size: 8pt; color: #777;">{{ $agent->name }} · {{ $date->translatedFormat('j M Y') }} · {{ __('Page :page of :pages', ['page' => '{PAGENO}', 'pages' => '{nbpg}']) }}</div>
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
            <h1>{{ __('Combined Purchase Order') }}</h1>
            <div><strong>{{ $agent->name }}</strong></div>
            <div class="muted">{{ __('Date') }}: {{ $date->translatedFormat('j M Y') }}</div>
            <div class="muted">{{ __(':count suppliers', ['count' => $sections->count()]) }} · {{ __(':count products', ['count' => $sections->sum(fn ($section) => $section['lines']->count())]) }}</div>
        </td>
    </tr>
</table>

<table class="blocks">
    <tr>
        <td>
            <div class="label">{{ __('Agent') }}</div>
            <strong>{{ $agent->name }}</strong><br>
            @if($agent->organisation?->address){!! nl2br(e($agent->organisation->address->formatted_address)) !!}<br>@endif
            @if($agent->organisation?->email){{ $agent->organisation->email }}<br>@endif
            @if($agent->organisation?->phone){{ $agent->organisation->phone }}@endif
        </td>
        <td>
            <div class="label">{{ __('Deliver to') }}</div>
            {!! nl2br(e($deliveryAddress ?? $organisation->name)) !!}
        </td>
        <td></td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width: 4%">#</th>
        <th style="width: 14%">{{ __('Code') }}</th>
        <th>{{ __('Description') }}</th>
        <th class="right" style="width: 9%">{{ __('Units/ctn') }}</th>
        <th class="right" style="width: 9%">{{ __('Cartons') }}</th>
        <th class="right" style="width: 8%">{{ __('Units') }}</th>
        <th class="right" style="width: 11%">{{ __('Unit price') }}{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
        <th class="right" style="width: 13%">{{ __('Amount') }}{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($sections as $section)
        @php($purchaseOrder = $section['purchaseOrder'])
        <tr class="supplier">
            <td colspan="8">
                {{ $section['supplier']?->code }} {{ $section['supplier']?->name ?? $purchaseOrder->parent_name }}
                @if($section['supplier']?->phone) · {{ $section['supplier']->phone }}@endif
                <span style="font-weight: normal;"> · {{ $purchaseOrder->reference }} · {{ $purchaseOrder->submitted_at?->translatedFormat('j M Y') }}</span>
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
            <td>{{ __('Total') }} {{ $currencyCode }}</td>
            <td class="right">{{ number_format($total, 2) }} {{ $currencyCode }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
