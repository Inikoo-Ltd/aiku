<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __("Invoice") }} {{ $invoice->reference }}</title>
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
        .total td { font-weight: bold; font-size: 14px; border-bottom: none; padding-top: 12px; }
    </style>
</head>
<body>
    <table style="width: 100%">
        <tr>
            <td>
                <h1>{{ __("Invoice") }}</h1>
                <strong>{{ $invoice->reference }}</strong>
            </td>
            <td class="num">
                <strong>{{ __("Date") }}:</strong> {{ $invoice->date->format('j F Y') }}<br>
                <strong>{{ __("Container") }}:</strong> {{ $invoice->stockDelivery->reference }}<br>
                <strong>{{ __("Currency") }}:</strong> {{ $invoice->currency->code }}
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <span class="muted">{{ __("From") }}</span><br>
                <strong>{{ $invoice->agent->organisation->name }}</strong><br>
                {{ $invoice->agent->organisation->address?->formatted_address }}
            </td>
            <td>
                <span class="muted">{{ __("Bill to") }}</span><br>
                <strong>{{ $invoice->organisation->name }}</strong><br>
                {{ $invoice->organisation->address?->formatted_address }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="15%">{{ __("Code") }}</th>
                <th width="40%">{{ __("Description") }}</th>
                <th width="12%" class="num">{{ __("Quantity") }}</th>
                <th width="13%" class="num">{{ __("Unit price") }}</th>
                <th width="20%" class="num">{{ __("Amount") }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
                <tr>
                    <td>{{ $line['code'] }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td class="num">{{ number_format($line['quantity'], $line['quantity'] == floor($line['quantity']) ? 0 : 2) }}</td>
                    <td class="num">{{ number_format($line['unit_price'], 2) }}</td>
                    <td class="num">{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
            @if(count($invoice->charges))
                <tr>
                    <td colspan="4" class="num">{{ __("Goods") }}</td>
                    <td class="num">{{ number_format((float) $invoice->goods_amount, 2) }}</td>
                </tr>
                @foreach($invoice->charges as $charge)
                    <tr>
                        <td colspan="4" class="num">{{ $charge['description'] }}</td>
                        <td class="num">{{ number_format($charge['amount'], 2) }}</td>
                    </tr>
                @endforeach
            @endif
            <tr class="total">
                <td colspan="4" class="num">{{ __("Total") }} {{ $invoice->currency->code }}</td>
                <td class="num">{{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
            @php($advancePayments = $invoice->advancePayments())
            @if(count($advancePayments))
                @foreach($advancePayments as $payment)
                    <tr>
                        <td colspan="4" class="num">{{ $payment['type'] === 'deposit' ? __('Less deposit') : __('Less payment') }} {{ $payment['reference'] }} {{ $payment['date'] }}</td>
                        <td class="num">-{{ number_format($payment['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="4" class="num">{{ __("Balance due") }} {{ $invoice->currency->code }}</td>
                    <td class="num">{{ number_format($invoice->balanceDue(), 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
