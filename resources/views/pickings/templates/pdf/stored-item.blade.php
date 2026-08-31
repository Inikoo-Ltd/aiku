<style>
    .root {
        width: 100%;
        height: 100%;
        font-size: 10mm;
        padding: 0;
        margin: 0;
        font-family: "Arial", "Helvetica Neue", Helvetica, sans-serif;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .labels td {
        font-size: 4mm;
        color: #000;
        text-align: center;
        padding: 4mm 2mm;
        border-top: 0.2mm solid #000;
    }

    .data td {
        text-align: center;
        font-size: 15mm;
        font-weight: bold;
        padding: 2mm 1mm;
        border-bottom: 0.2mm solid #000;
    }

    .barcode {
        text-align: center;
        margin: 10mm 4mm;
        padding: 4mm 2mm;
    }

    .footer td {
        font-size: 3mm;
        color: #000;
        text-align: center;
        padding-top: 10mm;
    }

    body, html {
        width: 297mm;
        height: 210mm;
        margin: 0;
        padding: 0;
    }

</style>

<div class="root">
    <table>
        <tr class="labels">
            <td colspan="2">{{ __('Customer') }}</td>
        </tr>
        <tr class="data">
            <td colspan="2">{{ $customer->name }} ({{ $customer->id }})</td>
        </tr>

        <tr class="labels">
            <td colspan="2">{{ __('Stored item reference') }}</td>
        </tr>
        <tr class="data">
            <td colspan="2">{{ $storedItem->reference }}</td>
        </tr>

        <div class="barcode">
            <barcode code="{{ $storedItem->reference }}" type="C128B"></barcode>
        </div>

        @if($storedItem->name)
            <tr class="labels">
                <td colspan="2">{{ __('Name') }}</td>
            </tr>
            <tr class="data">
                <td colspan="2">{{ $storedItem->name }}</td>
            </tr>
        @endif
    </table>

    <table>
        <tr class="footer">
            <td colspan="2">{{ __('Stored by') }} {{ $shop->name }}</td>
        </tr>
    </table>
</div>
