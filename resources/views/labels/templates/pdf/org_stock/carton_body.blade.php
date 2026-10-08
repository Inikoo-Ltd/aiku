@php
    $carton     = $label['carton'];
    $hasImage   = $show['image'] && $label['image_path'];
    $hasBarcode = filled($cartonBarcode ?? null);
    $caption    = 'font-size: '.$scale['caption'].'pt; padding: 0.8mm 1mm 0 1mm; border-top: 0.1mm solid #000;';
    $value      = 'font-size: '.$scale['value'].'pt; font-weight: bold; padding: 0.2mm 1mm 0.8mm 1mm; border-bottom: 0.1mm solid #000;';
@endphp

<table border="0" cellpadding="0" cellspacing="0"
       style="width: 100%; border-collapse: collapse; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; color: #000; text-align: center;">
    <tr>
        <td colspan="4" align="center" style="font-size: {{ $scale['header'] }}pt; padding-bottom: 0.5mm;">{{ __('Commercialised by :name', ['name' => $carton['commercialised_by']]) }}</td>
        @if ($hasImage)
            <td rowspan="6" width="{{ $scale['image_width'] }}%" align="center" valign="middle">
                <img src="{{ $label['image_path'] }}" width="{{ $scale['image'] }}" height="{{ $scale['image'] }}"/>
            </td>
        @endif
    </tr>
    <tr>
        <td align="center" style="{{ $caption }}">{{ __('Reference') }}</td>
        <td align="center" style="{{ $caption }}">{{ __('Units per pack') }}</td>
        <td align="center" style="{{ $caption }}">{{ __('Packs per carton') }}</td>
        <td align="center" style="{{ $caption }}">{{ __('Units per carton') }}</td>
    </tr>
    <tr>
        <td align="center" style="{{ $value }}">{{ $label['code'] }}</td>
        <td align="center" style="{{ $value }}">{{ $carton['units_per_pack'] ?? '' }}</td>
        <td align="center" style="{{ $value }}">{{ $carton['packs_per_carton'] ?? '' }}</td>
        <td align="center" style="{{ $value }}">{{ $carton['units_per_carton'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="4" align="center" style="{{ $caption }}">{{ __('Unit description') }}</td>
    </tr>
    <tr>
        <td colspan="4" align="center" style="{{ $value }}">{{ \Illuminate\Support\Str::limit($carton['description'], 120) }}</td>
    </tr>
    @if ($show['materials'])
        <tr>
            <td colspan="4" align="center" style="{{ $caption }}">{{ __('Materials') }}</td>
        </tr>
        <tr>
            <td colspan="4" align="center" style="{{ $value }} font-weight: normal; font-size: {{ $scale['small'] }}pt;">{{ \Illuminate\Support\Str::limit($label['materials'], 300) }}</td>
        </tr>
    @endif
    <tr>
        <td align="center" style="{{ $caption }}">{{ __('Batch code') }}</td>
        <td align="center" style="{{ $caption }}">{{ __('Net weight') }}</td>
        <td align="center" style="{{ $caption }}">{{ __('Gross weight') }}</td>
        <td align="center" style="{{ $caption }}">{{ __('Origin') }}</td>
    </tr>
    <tr>
        <td align="center" style="{{ $value }}">{{ $carton['batch_code'] ?? '' }}</td>
        <td align="center" style="{{ $value }}">{{ $carton['net_weight'] ?? '' }}</td>
        <td align="center" style="{{ $value }}">{{ $carton['gross_weight'] ?? '' }}</td>
        <td align="center" style="{{ $value }}">{{ $carton['origin'] ?? '' }}</td>
    </tr>
    @if ($hasBarcode)
        <tr>
            <td colspan="4" align="center" style="padding-top: 1.5mm;">
                <img src="{{ $cartonBarcode['uri'] }}" width="{{ $cartonBarcode['width'] }}" height="{{ $cartonBarcode['height'] }}"/>
            </td>
        </tr>
        <tr>
            <td colspan="4" align="center" style="font-size: {{ $scale['caption'] }}pt; padding-top: 0.5mm;">{{ $label['barcode']['number'] }}</td>
        </tr>
    @endif
    @if ($show['custom_text'] && filled($customText))
        <tr>
            <td colspan="4" align="center" style="font-size: {{ $scale['small'] }}pt; padding-top: 1mm;">{{ $customText }}</td>
        </tr>
    @endif
    @if ($carton['signature'])
        <tr>
            <td colspan="4" align="center" style="font-size: {{ $scale['caption'] }}pt; padding-top: 1mm;">{{ $carton['signature'] }}</td>
        </tr>
    @endif
</table>
