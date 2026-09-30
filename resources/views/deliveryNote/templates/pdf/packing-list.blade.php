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
        table.items tr.set-head td { background-color: #eef2f7; font-weight: bold; border-top: 2px solid #555; }
        table.items tr.set-part td { background-color: #f8fafc; font-size: 12px; padding-top: 6px; padding-bottom: 6px; }
        table.items tr.set-last td { border-bottom: 2px solid #555; }
        table.items td.set-left { border-left: 2px solid #555; }
        table.items td.set-right { border-right: 2px solid #555; }
        .set-note { font-size: 11px; font-weight: normal; color: #666; }
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
                        <th width="18%">{{ __("SKO Code") }}</th>
                        <th width="18%">{{ __("Product Code") }}</th>
                        <th width="49%">{{ __("Description") }}</th>
                        <th width="15%" class="text-center">{{ __("Quantity") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['sko_code'] }}</td>
                            <td>{{ $row['product_code'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="text-center">{{ $row['quantity'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align: right;">{{ __("Total in box :box", ['box' => $box]) }}</th>
                        <th class="text-center">{{ (float) $rows->sum('quantity') }}</th>
                    </tr>
                </tfoot>
            </table>
        @endforeach
    @else
    <table class="items">
        <thead>
            <tr>
                <th width="18%">{{ __("SKO Code") }}</th>
                <th width="18%">{{ __("Product Code") }}</th>
                <th width="49%">{{ __("Description") }}</th>
                <th width="15%" class="text-center">{{ __("Quantity") }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                @if($line['components'])
                    <tr class="set-head">
                        <td class="set-left"></td>
                        <td>{{ $line['product_code'] }}</td>
                        <td>
                            {{ $line['description'] }}
                            <br><span class="set-note">{{ __("Set of :count items, packed together", ['count' => count($line['components'])]) }}</span>
                        </td>
                        <td class="text-center set-right">{{ $line['quantity'] }}</td>
                    </tr>
                    @foreach($line['components'] as $component)
                        <tr class="set-part {{ $loop->last ? 'set-last' : '' }}">
                            <td class="set-left">{{ $component['sko_code'] }}</td>
                            <td></td>
                            <td>&#8627; {{ $component['description'] }}</td>
                            <td class="text-center set-right">{{ $component['quantity'] }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>{{ $line['sko_code'] }}</td>
                        <td>{{ $line['product_code'] }}</td>
                        <td>{{ $line['description'] }}</td>
                        <td class="text-center">{{ $line['quantity'] }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" style="text-align: right;">{{ __("Total") }}</th>
                <th class="text-center">{{ $lines->sum('quantity') }}</th>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="footer">
    </div>

</body>
</html>