/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-11h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pallet Return - {{ $palletReturn->reference }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 8px; border: 0.1mm solid #000; }
        .header, .footer { font-size: 9pt; text-align: center; }
        .address { font-size: 9pt; color: #555; }
        .items td { border: 0.1mm solid #000; }
        .items thead td { background-color: #EEEEEE; text-align: center; }
        .totals td { text-align: right; border: 0.1mm solid #000; }
        .total-row td { font-weight: bold; text-align: right; border-top: 0.3mm solid #000; }
    </style>
</head>
<body>

<!-- Header Section -->
<div class="header">
    <h1>Pallet Return</h1>
    <p>Pallet Return No: {{ $palletReturn->reference }} | Date: {{ \Carbon\Carbon::parse($palletReturn->confirmed_at)->format('Y-m-d') }}</p>
</div>

<!-- Company and Customer Details -->
<table>
    <tr>
        <td style="width: 50%;">
            <strong>From:</strong> <br>
            {{ $shop->name }} <br>
            {{ $shop->address->formatted_address }}<br>
            Phone: {{ $shop->phone }} <br>
            Email: {{ $shop->email }}
        </td>
        <td style="width: 50%;">
            <strong>To:</strong> <br>
            {{ $customer->name ?? 'Customer Name' }}<br>
            @if($palletReturn->is_collection)
                <br> 
                {{ __('Set for collection on Warehouse') }}: <br>
                {{ $shop->address->formatted_address }}<br>
            @else
                {{ $deliveryAddress ?? 'Delivery Address' }}<br>
            @endif
        </td>
    </tr>
</table>

<!-- Delivery Details -->
<table>
    <tr>
        <td><strong>Issued Date:</strong> {{ \Carbon\Carbon::parse($palletReturn->picking_at)->format('Y-m-d') }}</td>
    </tr>
</table>

<!-- Items Section -->
<table class="items" style="border-collapse: collapse;">
    <thead>
        <tr>
            <td rowspan="2" class="text-align:left; width: fit-content; vertical-align:middle">
                Location
            </td>
            <td rowspan="2" class="text-align:left; width: 30%; vertical-align:middle">
                Pallet
            </td>
            <td colspan="2" class="text-align:left; vertical-align:middle">
                SKU's
            </td>
            <td rowspan="2" class="text-align:left; vertical-align:middle">
                Picked
            </td>
        </tr>
        <tr>
            <td class="text-align:left; vertical-align: middle">
                SKU Reference
            </td>
            <td class="text-align:left; vertical-align: middle">
                Quantity
            </td>
        </tr>
    </thead>
    <tbody>
        @foreach ($pallets as $pallet)
            @php
                $rowSpan = $pallet->storedItems->count() + 1;
            @endphp
            <tr>
                <td rowspan="{{ $rowSpan }}" class="text-align:left; width: fit-content; vertical-align:middle">
                    {{ $pallet->location?->code ?? 'n/a' }}
                </td>
                <td rowspan="{{ $rowSpan }}" class="text-align:left; width: fit-content; vertical-align:middle">
                    {{ $pallet->reference }} <small>({{ $pallet->customer_reference }})</small>
                </td>
                @if($pallet->storedItems->isEmpty())
                    <td colspan="2">
                        -
                    </td>
                    <td rowspan="{{ $rowSpan }}" class="text-align:left; width: fit-content; vertical-align:middle">
                    </td>
                @endif
            </tr>
            @foreach($pallet->storedItems as $storedItem)
                <tr>
                    <td style="width: 35%;">
                       <div>
                            {{ $storedItem->name }}
                       </div>
                       <div style="color: #555555">
                            ({{ $storedItem->reference }})
                       </div>
                    </td>
                    <td>
                        {{ $storedItem->total_quantity }}
                    </td>
                    @if($loop->first)
                        <td rowspan="{{ $rowSpan - 1}}" class="text-align:left; width: fit-content; vertical-align:middle">
                        </td>
                    @endif
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

<!-- Footer Section -->
<div class="footer"> <p>Thank you for your hard work and attention to detail!</p> 
<p>If you have any questions or need further information about this order, please don't hesitate to contact us at {{$shop->phone}} or  <a href="mailto:{{$shop->email}}">{{$shop->email}}</a>.</p> </div>

</body>
</html>
