@php
    $caption = 'font-size: 5pt; color: #333;';
    $value   = 'font-weight: bold; line-height: 1.1;';

    $referenceWidthMm   = 36;
    $referenceFontSize  = min(11, round($referenceWidthMm / (max(1, mb_strlen($label['code'])) * 0.72 * 0.3528), 1));
@endphp

<table border="0" cellpadding="0" cellspacing="0" style="width: 100%; font-family: dejavusans; color: #000; text-align: center;">
    <tr>
        <td colspan="5">
            <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
                <tr>
                    <td width="40%" align="center" style="{{ $caption }}">Reference</td>
                    <td width="20%" align="center" style="{{ $caption }}">Units per pack</td>
                    <td width="20%" align="center" style="{{ $caption }}">Packs per carton</td>
                    <td width="20%" align="center" style="{{ $caption }}">Units per carton</td>
                </tr>
                <tr>
                    <td align="center" style="{{ $value }} font-size: {{ $referenceFontSize }}pt; white-space: nowrap;">{{ $label['code'] }}</td>
                    <td align="center" style="{{ $value }} font-size: 11pt;">{{ $label['units_per_pack'] }}</td>
                    <td align="center" style="{{ $value }} font-size: 11pt;">{{ $label['packs_per_carton'] }}</td>
                    <td align="center" style="{{ $value }} font-size: 11pt;">{{ $label['units_per_carton'] }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="5" align="center" style="border-top: 0.1mm solid #999; padding-top: 0.6mm; font-size: 9pt; font-weight: bold;">{{ \Illuminate\Support\Str::limit($label['name'], 70) }}</td>
    </tr>
    <tr>
        <td colspan="5" align="center" style="font-size: 5pt;">ES {{ $label['names']['es'] }} · SK {{ $label['names']['sk'] }}</td>
    </tr>
    @if ($label['material'])
        <tr>
            <td colspan="5" align="center" style="font-size: 5pt; padding-bottom: 0.6mm;">Material: {{ \Illuminate\Support\Str::limit($label['material'], 120) }}</td>
        </tr>
    @endif
    <tr>
        <td width="28%" align="center" style="border-top: 0.1mm solid #999; padding-top: 0.6mm; {{ $caption }}">Batch / Lote / Šarža</td>
        <td width="18%" align="center" style="border-top: 0.1mm solid #999; padding-top: 0.6mm; {{ $caption }}">Net weight</td>
        <td width="18%" align="center" style="border-top: 0.1mm solid #999; padding-top: 0.6mm; {{ $caption }}">Gross weight</td>
        <td width="14%" align="center" style="border-top: 0.1mm solid #999; padding-top: 0.6mm; {{ $caption }}">Origin</td>
        <td width="22%" align="center" style="border-top: 0.1mm solid #999; padding-top: 0.6mm; {{ $caption }}">HS code</td>
    </tr>
    <tr>
        <td align="center" style="{{ $value }} font-size: 8.5pt;">{{ $label['batch'] }}</td>
        <td align="center" style="{{ $value }} font-size: 8.5pt;">{{ $label['weights']['net'] ?? '–' }}</td>
        <td align="center" style="{{ $value }} font-size: 8.5pt;">{{ $label['weights']['gross'] ?? '–' }}</td>
        <td align="center" style="{{ $value }} font-size: 8.5pt;">{{ $label['origin'] ?? '–' }}</td>
        <td align="center" style="{{ $value }} font-size: 8.5pt;">{{ $label['hs_code'] ?? '–' }}</td>
    </tr>
    <tr>
        <td colspan="5" align="center" style="padding-top: 1.5mm;">
            <img src="{{ $barcode['uri'] }}" style="width: {{ $barcode['width'] }}; height: {{ $barcode['height'] }};"/>
        </td>
    </tr>
    <tr>
        <td colspan="5" align="center" style="font-size: 7.5pt;">{{ $label['hri'] }}</td>
    </tr>
    <tr>
        <td colspan="5" align="center" style="font-size: 5pt; padding-bottom: 0.6mm;">Contains {{ $label['units_per_carton'] }} × EAN {{ $label['unit_ean13'] }} · {{ $label['made_in'] }}</td>
    </tr>
    @include('labels.templates.pdf.org_stock.unified.operators', ['columns' => 5, 'size' => 4.3, 'withRole' => false])
</table>
