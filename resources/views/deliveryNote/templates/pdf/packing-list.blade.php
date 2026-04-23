<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Packing List - {{ $deliveryNote->reference }}</title>
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
        .footer { margin-top: 40px; font-size: 12px; color: #777; text-align: center; font-style: italic; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ __("Packing List") }}</h1>
    </div>

    <table class="meta-info">
        <tr>
            <td><strong>{{ __("Order Ref") }}:</strong> {{ $order ? $order->reference : $deliveryNote->slug }}</td>
            <td style="text-align: right;">
                @if(isset($order) && !is_null($order->collection_address_id))
                    <strong>{{ __("Collected Date") }}:</strong> {{ $order->dispatched_at ? \Carbon\Carbon::parse($order->dispatched_at)->format('jS F, Y') : 'N/A' }}<br>
                @elseif(isset($order) && $order->dispatched_at)
                    <strong>{{ __("Date Dispatched") }}:</strong> {{ \Carbon\Carbon::parse($order->dispatched_at)->format('jS F, Y') }}<br>
                @endif
                <strong>{{ __("Date Printed") }}:</strong> {{ \Carbon\Carbon::now()->format('jS F, Y') }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="25%">{{ __("Product Code") }}</th>
                <th width="60%">{{ __("Product Name") }}</th>
                <th width="15%" class="text-center">{{ __("Packed") }}</th>
            </tr>
        </thead>
        <tbody>
            @php $totalQty = 0; @endphp
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->orgStock->code ?? 'Unknown Code' }}</td>
                    <td>
                        {{ $item->orgStock->name ?? 'Unknown Name' }}
                        @if(isset($item->orgStock->packed_in) && $item->orgStock->packed_in > 1)
                            [Pack of {{ $item->orgStock->packed_in }}]
                        @endif
                    </td>
                    <td>{{ number_format((float) ($item->quantity_packed ?? 0), 0) }}</td>
                </tr>
                @php $totalQty += (float) ($item->quantity_packed ?? 0); @endphp
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align: right;">{{ __("Total Items Packed") }}</th>
                <th class="text-center" style="font-size: 14px;">{{ number_format($totalQty, 0) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
    </div>

</body>
</html>