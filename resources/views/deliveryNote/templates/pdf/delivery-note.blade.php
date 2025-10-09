<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Note - {{ $deliveryNote->reference }}</title>
    <style>
        body { font-family: sans-serif; font-size: 8pt; }
        h1 { font-size: 14pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 8px; border: 0.1mm solid #000; }
        .header, .footer { font-size: 9pt; text-align: center; }
        .items td { border: 0.1mm solid #000; }
        .items thead td { background-color: #EEEEEE; text-align: center; }
        .totals td { text-align: right; border: 0.1mm solid #000; }
        .total-row td { font-weight: bold; text-align: right; border-top: 0.3mm solid #000; }
    </style>
</head>
<body>

<!-- Header Section -->
<div class="header">
    <h1>Delivery Note</h1>
    <p>Delivery Note No: {{ $deliveryNote->reference }} | Date: {{ \Carbon\Carbon::parse($deliveryNote->date)->format('jS F, Y') }}</p>
</div>

<!-- Company and Customer Details -->
<table>
    <tr>
        <td style="width: 50%;">
            <strong>From:</strong> <br>
            {{$deliveryNote->shop->name}} <br>
            {!! nl2br($shopAddress ?? 'Delivery Address') !!}<br>
            @if($shop->phone)
            Phone: {{$shop->phone}} <br>
            @endif
            @if($shop->email)
            Email: {{$shop->email}}
            @endif
        </td>
        <td style="width: 50%;">
            <strong>To:</strong> <br>
            {{ $customer->name ?? 'Customer Name' }}<br>
            {!! nl2br($deliveryAddress ?? 'Delivery Address') !!}<br>
        </td>
    </tr>
</table>

<!-- Delivery Details -->
<table>
    <tr>
        <td><strong>Order Number:</strong> {{ $order->reference }}</td>
        <td><strong>Issued Date:</strong> {{ \Carbon\Carbon::parse($order->in_warehouse_at)->format('jS F, Y') }}</td>
    </tr>
</table>

<!-- Items Section -->
<table class="items">
    <thead>
        <tr>
            <td>Item Code</td>
            <td>Item Name</td>
            <td>Required</td>
            <td>Picked</td>
            <td>Packed</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
        <tr>
            <td>{{ $item->orgStock->code }}</td>
            <td>{{ $item->orgStock->name }}</td>
            <td>{{ number_format($item->quantity_required,0) }}</td>
            <td></td>
            <td></td>
        </tr>
        @endforeach
    </tbody>
</table>

<!-- Footer Section -->
<div class="footer">

</div>

</body>
</html>
