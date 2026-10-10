<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $reference }}</title>
    @include('procurement.templates.pdf.purchase-order-styles')
</head>
<body>
@foreach($purchaseOrders as $order)
    @if(!$loop->first)
        <pagebreak resetpagenum="1" />
    @endif
    @include('procurement.templates.pdf.purchase-order-content', $order)
@endforeach
</body>
</html>
