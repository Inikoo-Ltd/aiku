/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-11h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SKU Return - {{ $palletReturn->reference }}</title>
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
    <h1>SKU Return</h1>
    <p>SKU Return No: {{ $palletReturn->reference }} | Date: {{ \Carbon\Carbon::parse($palletReturn->confirmed_at)->format('Y-m-d') }}</p>
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
            {{ $deliveryAddress ?? 'Delivery Address' }}<br>
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
<table class="items">
    <thead>
        <tr>
            <td>Location</td>
            <td>Pallet</td>
            <td>SKU</td>
            <td>Quantity Ordered</td>
            <td>Picked</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
        <tr>
            <td>{{ $item->pallet->location?->code ?? 'n/a' }}</td>
            <td>{{ $item->pallet->reference }} <small>({{ $item->pallet->customer_reference }})</small></td>
            <td>{{ $item->storedItem->reference }} <small>({{ $item->storedItem->name }})</small></td>
            <td>{{ number_format($item->quantity_ordered,0) }}</td>
            <td></td>
        </tr>
        @endforeach
    </tbody>
</table>

<!-- Footer Section -->
<div class="footer"> <p>Thank you for your hard work and attention to detail!</p> 
<p>If you have any questions or need further information about this order, please don't hesitate to contact us at {{$shop->phone}} or  <a href="mailto:{{$shop->email}}">{{$shop->email}}</a>.</p> </div>

</body>
</html>
