<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Delivery - {{ $stockDelivery->reference }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { margin: 0; font-size: 26px; text-transform: uppercase; }
        .meta-info { width: 100%; margin-bottom: 25px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .meta-info td { padding: 5px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border: 1px solid #ddd; padding: 12px 10px; text-align: left; }
        table.items th { background-color: #f4f4f4; text-transform: uppercase; font-size: 12px; }
        .text-center { text-align: center; }
        .tick-box { width: 24px; height: 24px; border: 1px solid #333; }
        .footer { margin-top: 40px; font-size: 12px; color: #777; text-align: center; font-style: italic; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ __("Goods In") }}</h1>
    </div>

    <table class="meta-info">
        <tr>
            <td>
                <strong>{{ __("Reference") }}:</strong> {{ $stockDelivery->reference }}<br>
                <strong>{{ __("Supplier / Agent") }}:</strong> {{ $stockDelivery->parent_name }}<br>
                <strong>{{ __("Organisation") }}:</strong> {{ $stockDelivery->organisation->name }}
            </td>
            <td style="text-align: right;">
                <strong>{{ __("State") }}:</strong> {{ \App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum::labels()[$stockDelivery->state->value] ?? $stockDelivery->state->value }}<br>
                <strong>{{ __("Date") }}:</strong> {{ $stockDelivery->date ? $stockDelivery->date->format('jS F, Y') : 'N/A' }}<br>
                <strong>{{ __("Date Printed") }}:</strong> {{ \Carbon\Carbon::now()->format('jS F, Y') }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="15%">{{ __("Code") }}</th>
                <th width="35%">{{ __("Name") }}</th>
                <th width="15%" class="text-center">{{ __("Ordered") }}</th>
                <th width="15%" class="text-center">{{ __("Checked") }}</th>
                <th width="10%" class="text-center">{{ __("Placed") }}</th>
                <th width="10%" class="text-center">{{ __("Tick") }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->orgStock->code ?? 'Unknown Code' }}</td>
                    <td>{{ $item->orgStock->name ?? 'Unknown Name' }}</td>
                    <td class="text-center">{{ number_format((float) $item->unit_quantity, 0) }}</td>
                    <td class="text-center">{{ number_format((float) $item->unit_quantity_checked, 0) }}</td>
                    <td class="text-center">{{ number_format((float) $item->unit_quantity_placed, 0) }}</td>
                    <td class="text-center"><div class="tick-box"></div></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
    </div>

</body>
</html>
