<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __("Commission invoice") }} {{ $month->format('F Y') }}</title>
    <style>
        @page {
            margin-top: 12mm;
            margin-bottom: 20mm;
            margin-left: 14mm;
            margin-right: 14mm;
            margin-footer: 8mm;
            footer: invoiceFooter;
        }
        body { font-family: sans-serif; font-size: 9pt; color: #222; }
        td { vertical-align: top; }
        .muted { color: #777; }
        .label { color: #777; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt; }
        .num { text-align: right; white-space: nowrap; }

        .company-name { font-size: 16pt; font-weight: bold; color: #111; }
        .company-details { font-size: 8.5pt; color: #555; line-height: 1.4; }
        .document-title { font-size: 17pt; white-space: nowrap; font-weight: bold; color: #111; text-align: right; text-transform: uppercase; letter-spacing: 1pt; }

        table.meta { border-collapse: collapse; margin-top: 6px; }
        table.meta td { padding: 3px 0 3px 14px; font-size: 9pt; }
        table.meta td.meta-label { color: #777; text-align: right; }
        table.meta td.meta-value { font-weight: bold; text-align: right; }

        .rule { border-top: 0.6mm solid #222; margin: 14px 0 16px; }

        table.parties { width: 100%; border-collapse: separate; border-spacing: 0; }
        table.parties td.party { width: 49%; border: 0.2mm solid #ccc; padding: 10px 12px; background-color: #fafafa; line-height: 1.45; }
        table.parties td.gap { width: 2%; }
        .party-name { font-size: 11pt; font-weight: bold; margin: 3px 0 2px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items thead td { background-color: #222; color: #fff; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; padding: 7px 8px; }
        table.items tbody td { border-bottom: 0.2mm solid #e2e2e2; padding: 6px 8px; }
        table.items tr.supplier td { background-color: #f1f1f1; font-weight: bold; border-bottom: 0.2mm solid #ccc; padding-top: 8px; }
        table.items tr.subtotal td { font-weight: bold; border-bottom: 0.4mm solid #999; }

        table.summary { width: 50%; border-collapse: collapse; margin-top: 18px; }
        table.summary td { padding: 6px 10px; border-bottom: 0.2mm solid #e2e2e2; }
        table.summary tr.exchange td { color: #777; font-size: 8pt; }
        table.summary tr.missing td { color: #b00020; font-weight: bold; }
        table.summary tr.due td { background-color: #222; color: #fff; font-size: 11pt; font-weight: bold; border-bottom: none; }

        .note { margin-top: 26px; padding: 12px 14px; border-left: 1.2mm solid #222; background-color: #f4f4f4; font-size: 10.5pt; }
        .footer { border-top: 0.2mm solid #ccc; padding-top: 5px; font-size: 7.5pt; color: #777; }
    </style>
</head>
<body>
    @php
        $rate = rtrim(rtrim(number_format($commission['rate'] * 100, 2), '0'), '.').'%';
        $money = function (string $currencyCode, float $amount) use ($currencySymbols): string {
            $symbol = $currencySymbols[$currencyCode] ?? $currencyCode;

            return $symbol.(preg_match('/^\p{L}+$/u', $symbol) ? ' ' : '').number_format($amount, 2);
        };
        $commissionDue = $usd
            ? $money('USD', $usd['commission'])
            : collect($commission['totals'])->map(fn ($total) => $money($total['currency_code'], $total['commission']))->implode(' + ');
    @endphp

    <htmlpagefooter name="invoiceFooter">
        <table width="100%" class="footer">
            <tr>
                <td>
                    @if($billedOrganisation)
                        {{ $billedOrganisation->name }}
                        @if($billedOrganisation->address) · {{ str_replace("\n", ', ', $billedOrganisation->address->formatted_address) }}@endif
                    @endif
                </td>
                <td class="num">{{ __("Page") }} {PAGENO} / {nbpg}</td>
            </tr>
        </table>
    </htmlpagefooter>

    <table width="100%">
        <tr>
            <td width="55%">
                @if($billedOrganisation)
                    <div class="company-name">{{ $billedOrganisation->name }}</div>
                    <div class="company-details">
                        {!! nl2br(e($billedOrganisation->address?->formatted_address)) !!}
                        @if($billedOrganisation->phone)<br>{{ __("Tel") }}: {{ $billedOrganisation->phone }}@endif
                        @if($billedOrganisation->email)<br>{{ $billedOrganisation->email }}@endif
                    </div>
                @endif
            </td>
            <td width="45%" class="num">
                <div class="document-title">{{ __("Commission invoice") }}</div>
                <table class="meta" align="right">
                    <tr>
                        <td class="meta-label">{{ __("Date") }}</td>
                        <td class="meta-value">{{ now()->format('j M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">{{ __("Period") }}</td>
                        <td class="meta-value">{{ $month->format('F Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">{{ __("Commission rate") }}</td>
                        <td class="meta-value">{{ $rate }} {{ __("of products") }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="parties">
        <tr>
            <td class="party">
                <div class="label">{{ __("To") }}</div>
                <div class="party-name">{{ $organisation->name }}</div>
                {!! nl2br(e($organisation->address?->formatted_address)) !!}
                @if($organisation->phone)<br>{{ __("Tel") }}: {{ $organisation->phone }}@endif
                @if($organisation->email)<br>{{ $organisation->email }}@endif
            </td>
            <td class="gap"></td>
            <td class="party">
                <div class="label">{{ __("Covering") }}</div>
                <div class="party-name">{{ __("Purchase orders shipped") }}</div>
                {{ $month->startOfMonth()->format('j M Y') }} – {{ $month->endOfMonth()->format('j M Y') }}
                <br><br>
                <div class="label">{{ __("Commission due") }}</div>
                <div class="party-name">{{ $commissionDue ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <td width="32%">{{ __("Purchase order") }}</td>
                <td width="24%">{{ __("Organisation") }}</td>
                <td width="14%">{{ __("Shipped") }}</td>
                <td width="16%" class="num">{{ __("Products") }}</td>
                <td width="14%" class="num">{{ __("Commission") }}</td>
            </tr>
        </thead>
        <tbody>
            @forelse($commission['suppliers'] as $supplier)
                <tr class="supplier">
                    <td colspan="5">{{ $supplier['code'] }} · {{ $supplier['name'] }} <span class="muted">({{ $supplier['currency_code'] }})</span></td>
                </tr>
                @foreach($supplier['purchase_orders'] as $purchaseOrder)
                    <tr>
                        <td>{{ $purchaseOrder['reference'] }}</td>
                        <td>{{ $purchaseOrder['organisation'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($purchaseOrder['shipped_at'])->format('j M Y') }}</td>
                        <td class="num">{{ $money($supplier['currency_code'], $purchaseOrder['products']) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="3" class="num">{{ __("Subtotal") }} {{ $supplier['code'] }}</td>
                    <td class="num">{{ $money($supplier['currency_code'], $supplier['products']) }}</td>
                    <td class="num">{{ $money($supplier['currency_code'], $supplier['commission']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">{{ __("No purchase orders shipped this month") }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if(count($commission['totals']))
        <table class="summary" align="right">
            @foreach($commission['totals'] as $index => $total)
                @php($inUsd = $usd['lines'][$index] ?? null)
                <tr>
                    <td>{{ __("Total products") }}</td>
                    <td class="num">{{ $money($total['currency_code'], $total['products']) }}</td>
                </tr>
                <tr>
                    <td>{{ __("Commission") }} ({{ $rate }})</td>
                    <td class="num">{{ $money($total['currency_code'], $total['commission']) }}</td>
                </tr>
                @if($inUsd && $total['currency_code'] !== 'USD')
                    <tr class="exchange">
                        <td>{{ __("Exchange rate") }} {{ $inUsd['exchange_date']->format('j M Y') }}</td>
                        <td class="num">{{ $money('USD', 1) }} = {{ $money($total['currency_code'], 1 / $inUsd['exchange']) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __("Total products in USD") }}</td>
                        <td class="num">{{ $money('USD', $inUsd['products']) }}</td>
                    </tr>
                @endif
            @endforeach
            @if($usd)
                <tr class="due">
                    <td>{{ __("Commission due") }}</td>
                    <td class="num">{{ $money('USD', $usd['commission']) }}</td>
                </tr>
            @else
                <tr class="missing">
                    <td colspan="2">{{ __("No exchange rate to USD available") }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if(count($commission['totals']))
        <div style="clear: both"></div>
        <div class="note">
            {{ __("Please raise an invoice for this month's commission") }} = <strong>{{ $commissionDue }}</strong>
        </div>
    @endif
</body>
</html>
