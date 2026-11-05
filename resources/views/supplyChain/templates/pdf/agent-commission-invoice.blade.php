<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __("Commission invoice") }} {{ $month->format('F Y') }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h1 { margin: 0 0 4px; font-size: 24px; text-transform: uppercase; }
        .muted { color: #777; }
        table.parties { width: 100%; margin: 20px 0; }
        table.parties td { vertical-align: top; width: 50%; padding: 0; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border-bottom: 1px solid #ddd; padding: 7px 6px; text-align: left; }
        table.items th { background-color: #f4f4f4; text-transform: uppercase; font-size: 10px; }
        .num, table.items .num { text-align: right; white-space: nowrap; }
        .supplier td { font-weight: bold; background-color: #fafafa; }
        .subtotal td { font-weight: bold; }
        .total td { font-weight: bold; font-size: 14px; border-bottom: none; padding-top: 12px; }
    </style>
</head>
<body>
    @php($rate = rtrim(rtrim(number_format($commission['rate'] * 100, 2), '0'), '.').'%')
    <table style="width: 100%">
        <tr>
            <td>
                <h1>{{ __("Commission invoice") }}</h1>
                <strong>{{ $month->format('F Y') }}</strong>
            </td>
            <td class="num">
                <strong>{{ __("Date") }}:</strong> {{ now()->format('j F Y') }}<br>
                <strong>{{ __("Commission") }}:</strong> {{ $rate }} {{ __("of products") }}
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <span class="muted">{{ __("From") }}</span><br>
                <strong>{{ $organisation->name }}</strong><br>
                {{ $organisation->address?->formatted_address }}
            </td>
            <td class="muted">{{ __("Purchase orders shipped in") }} {{ $month->format('F Y') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="34%">{{ __("Purchase order") }}</th>
                <th width="24%">{{ __("Organisation") }}</th>
                <th width="14%">{{ __("Shipped") }}</th>
                <th width="14%" class="num">{{ __("Products") }}</th>
                <th width="14%" class="num">{{ __("Commission") }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($commission['suppliers'] as $supplier)
                <tr class="supplier">
                    <td colspan="5">{{ $supplier['code'] }} · {{ $supplier['name'] }} ({{ $supplier['currency_code'] }})</td>
                </tr>
                @foreach($supplier['purchase_orders'] as $purchaseOrder)
                    <tr>
                        <td>{{ $purchaseOrder['reference'] }}</td>
                        <td>{{ $purchaseOrder['organisation'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($purchaseOrder['shipped_at'])->format('j M Y') }}</td>
                        <td class="num">{{ number_format($purchaseOrder['products'], 2) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="3" class="num">{{ __("Subtotal") }} {{ $supplier['code'] }} {{ $supplier['currency_code'] }}</td>
                    <td class="num">{{ number_format($supplier['products'], 2) }}</td>
                    <td class="num">{{ number_format($supplier['commission'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">{{ __("No purchase orders shipped this month") }}</td>
                </tr>
            @endforelse
            @foreach($commission['totals'] as $total)
                <tr class="total">
                    <td colspan="3" class="num">{{ __("Total") }} {{ $total['currency_code'] }}</td>
                    <td class="num">{{ number_format($total['products'], 2) }}</td>
                    <td class="num">{{ number_format($total['commission'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
