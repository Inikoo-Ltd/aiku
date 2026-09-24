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

    @if($boxes->isNotEmpty())
        <table class="meta-info">
            <tr>
                <td>
                    <strong>{{ __("Delivery Note") }}:</strong> {{ $deliveryNote->reference }}<br>
                    <strong>{{ __("Boxes") }}:</strong> {{ $numberBoxes }}
                </td>
                <td style="text-align: right;">
                    <strong>{{ __("Deliver to") }}:</strong><br>
                    @if($deliveryNote->company_name){{ $deliveryNote->company_name }}<br>@endif
                    @if($deliveryNote->contact_name){{ $deliveryNote->contact_name }}<br>@endif
                    {!! nl2br(e($deliveryAddress ?? '')) !!}
                </td>
            </tr>
        </table>

        @foreach($boxes as $box => $rows)
            @php $parcel = $deliveryNote->parcels[$box - 1] ?? null; @endphp
            <h3 style="margin: 25px 0 5px 0;">
                {{ __("Box :box of :boxes", ['box' => $box, 'boxes' => $numberBoxes]) }}
                @if($parcel)
                    <span style="font-weight: normal; font-size: 12px; color: #777;">
                        {{ $parcel['weight'] ?? '' }} kg
                        @if(!empty($parcel['dimensions']))
                            · {{ implode('x', $parcel['dimensions']) }} cm
                        @endif
                    </span>
                @endif
            </h3>
            <table class="items">
                <thead>
                    <tr>
                        <th width="25%">{{ __("Product Code") }}</th>
                        <th width="60%">{{ __("Product Name") }}</th>
                        <th width="15%" class="text-center">{{ __("Quantity") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['item']->orgStock->code ?? '' }}</td>
                            <td>
                                {{ $row['item']->orgStock->name ?? '' }}
                                @if(($row['item']->orgStock->packed_in ?? 1) > 1)
                                    [Pack of {{ $row['item']->orgStock->packed_in }}]
                                @endif
                            </td>
                            <td class="text-center">{{ (float) $row['quantity'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" style="text-align: right;">{{ __("Total in box :box", ['box' => $box]) }}</th>
                        <th class="text-center">{{ (float) $rows->sum('quantity') }}</th>
                    </tr>
                </tfoot>
            </table>
        @endforeach
    @else
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
    @endif

    <div class="footer">
    </div>

</body>
</html>