<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Label</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
        }

        .label-wrapper {
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }

        /* ─── SHIPPING LABEL ─── */
        .shipping-label {
            width: 105mm;
            border: 0.4mm solid #000;
            background: #fff;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }

        /* Header row */
        .sl-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 3mm 4mm 2mm;
            border-bottom: 0.3mm solid #000;
        }
        .sl-service-name { line-height: 1.1; }
        .sl-service-name .tracked { font-size: 6mm; font-weight: 900; }
        .sl-service-name .tracked span { font-size: 10mm; font-weight: 900; margin-left: 1mm; }
        .sl-service-name .no-sig { font-size: 3.5mm; color: #c8442a; font-weight: 700; }
        .sl-logo-block { text-align: right; }
        .sl-logo-block .delivered-by { font-size: 2.5mm; color: #555; margin-bottom: 1mm; }
        .royal-mail-logo {
            border: 0.3mm solid #ccc;
            padding: 1.5mm 2mm;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5mm;
        }
        .rm-crown { font-size: 5mm; }
        .rm-text { font-size: 3mm; font-weight: 900; color: #c8442a; letter-spacing: 0.3mm; }
        .sl-logo-block .postage-note { font-size: 2.2mm; color: #333; margin-top: 1mm; }

        /* Sort codes row */
        .sl-sort-codes {
            display: flex;
            border-bottom: 0.3mm solid #000;
        }
        .sort-cell {
            padding: 2mm 4mm;
            font-size: 6mm;
            font-weight: 900;
            flex: 1;
            display: flex;
            align-items: center;
        }
        .sort-cell.dark {
            background: #000;
            color: #fff;
            justify-content: center;
        }
        .sort-cell.light {
            color: #000;
        }
        .sl-parcel-info {
            margin-left: auto;
            text-align: right;
            padding: 2mm 4mm;
            border-left: 0.3mm solid #000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            font-size: 2.5mm;
        }
        .sl-parcel-info .parcel-label { font-weight: 700; border-bottom: 0.2mm solid #000; padding-bottom: 1mm; margin-bottom: 1mm; }

        /* Barcode area */
        .sl-barcode-area {
            padding: 3mm 4mm;
            border-bottom: 0.3mm solid #000;
        }
        .sl-tracking-ref {
            font-size: 2.5mm;
            color: #c8442a;
            margin-bottom: 1.5mm;
            letter-spacing: 0.5mm;
        }
        .sl-barcodes-row {
            display: flex;
            gap: 4mm;
            align-items: center;
        }
        .sl-qr { width: 20mm; height: 20mm; border: 0.2mm solid #ccc; display: flex; align-items: center; justify-content: center; }
        .sl-qr svg { width: 100%; height: 100%; }
        .sl-barcode-1d { flex: 1; display: flex; flex-direction: column; align-items: center; }
        .barcode-svg { width: 100%; height: 14mm; }
        .sl-barcode-ref { font-size: 2.5mm; color: #c8442a; margin-top: 1mm; letter-spacing: 0.3mm; }

        /* Delivery address */
        .sl-address {
            padding: 3mm 4mm;
            border-bottom: 0.3mm solid #000;
            display: flex;
            gap: 2mm;
        }
        .sl-address-main { flex: 1; font-size: 3mm; line-height: 1.6; }
        .sl-address-main .recipient-name { font-size: 3.5mm; font-weight: 700; }
        .sl-return-address {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            font-size: 2mm;
            color: #333;
            line-height: 1.4;
            white-space: nowrap;
        }
        .sl-return-address .return-label { font-weight: 700; }

        /* Photo Only banner */
        .sl-photo-banner {
            background: #000;
            color: #fff;
            font-size: 5mm;
            font-weight: 900;
            padding: 1.5mm 4mm;
            border-bottom: 0.3mm solid #000;
        }

        /* Footer barcode */
        .sl-footer {
            padding: 2mm 4mm 3mm;
            display: flex;
            flex-direction: column;
            gap: 1mm;
        }
        .sl-customer-ref { font-size: 2.5mm; color: #333; }
        .sl-customer-ref span { font-weight: 700; }
        .sl-qty { font-size: 2.5mm; }
        .sl-footer-barcode { display: flex; flex-direction: column; align-items: flex-end; }
        .footer-barcode-svg { width: 45mm; height: 12mm; }
        .footer-barcode-ref { font-size: 2.5mm; text-align: center; width: 45mm; letter-spacing: 0.3mm; }

        /* ─── PRODUCT LABEL ─── */
        .product-label {
            width: 148mm;
            border: 0.4mm solid #000;
            background: #fff;
        }

        .pl-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pl-table td {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            color: #000;
        }

        .pl-top td {
            font-size: 2.8mm;
            text-align: center;
            vertical-align: bottom;
            padding: 2mm 5mm 2mm 5mm;
        }

        .pl-labels td {
            font-size: 2.5mm;
            color: #000;
            text-align: center;
            vertical-align: bottom;
            padding: 3mm 5mm 0 5mm;
            border-top: 0.1mm solid #000;
        }

        .pl-data td {
            font-size: 3mm;
            text-align: center;
            vertical-align: bottom;
            padding: 1mm 5mm 3mm 5mm;
            border-bottom: 0.1mm solid #000;
        }

        .pl-barcode-row td {
            text-align: center;
            padding: 2mm 0 1mm 0;
        }

        .pl-barcode-ref td {
            font-size: 2.8mm;
            text-align: center;
            padding: 0 5mm 2mm 5mm;
            border-bottom: 0.1mm solid #000;
            font-weight: 700;
            letter-spacing: 0.3mm;
        }

        .pl-image-cell {
            text-align: center;
            vertical-align: middle;
            border-left: 0.1mm solid #000;
            padding: 2mm;
            width: 28mm;
        }

        .pl-image-cell img {
            max-height: 25mm;
            max-width: 25mm;
            object-fit: contain;
            border-radius: 50%;
        }

        /* Barcode rendering */
        .barcode-bars {
            display: flex;
            height: 100%;
            align-items: stretch;
            gap: 0;
        }
        .bar { background: #000; }
        .gap { background: #fff; }
    </style>
</head>
<body>
<div class="label-wrapper">

    <!-- ══════════════════════════════════════════
         AW TRACKED 48 SHIPPING LABEL
    ══════════════════════════════════════════ -->
    <div class="shipping-label">

        <!-- Header -->
        <div class="sl-header">
            <div class="sl-service-name">
                <div class="tracked">Tracked</div>
            </div>
            <div class="sl-logo-block">
                <div class="royal-mail-logo">
                    <div class="rm-crown"><img src="{{ $customer_logo }}" alt="Customer Logo"></div>
                    <div class="rm-text">{{ $customer_name }}</div>
                </div>
            </div>
        </div>

        <!-- Sort codes -->
        <div class="sl-sort-codes">
            <div class="sl-parcel-info">
                <div class="parcel-label">Parcel</div>
                <div>14g</div>
            </div>
        </div>

        <!-- Delivery address -->
        <div class="sl-address">
            <div>
                <div class="sl-customer-ref">Delivery reference:<br><span>{{ $deliveryNoteRef }}</span></div>
            {{--<div class="sl-qty" style="margin-top:2mm;">1×</div>--}}
            </div>
            <div class="sl-address-main">
                <div class="recipient-name">{{ $customer_name }}</div>
                <div>{{ $website }}</div>
                <div>{{ $delivery_address }}</div>
            </div>
            {{--<div class="sl-return-address">
                <span class="return-label">Return Address</span> Pickandchoose19 · Affinity Park · Europa Drive · Sheffield · S9 1XT
            </div>--}}
        </div>
    </div>
</div>
</body>
</html>
