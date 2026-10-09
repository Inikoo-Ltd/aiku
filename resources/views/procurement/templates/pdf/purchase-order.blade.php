<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $purchaseOrder->reference }}</title>
    @include('procurement.templates.pdf.purchase-order-styles')
</head>
<body>
@include('procurement.templates.pdf.purchase-order-content')
</body>
</html>
