{{--
* Author: Vika Aqordi
* Created on: 2026-11-04 15:30
* Github: https://github.com/aqordeon
* Copyright: 2026
--}}

{{--
    The unit label on the small stocks, 63 x 29.6 up to 70 x 30, as Aurora printed it: the code and
    the name run the full width with the rule under the name, and below it the wording takes the left
    half while the barcode stands over the right half from the rule down.

    The table keeps itself on one page, so mPDF shrinks it rather than spilling a long address onto a
    second label.
--}}

@php
    $hasImage   = $show['image'] && $label['image_path'];
    $hasBarcode = (bool) $label['barcode']['number'];
    $columns    = 1 + ($hasBarcode ? 1 : 0) + ($hasImage ? 1 : 0);

    $lines = array_values(array_filter([
        $show['made_in'] ? $label['made_in'] : null,
        $show['manufactured_by'] ? $label['manufactured_by'] : null,
    ]));
@endphp

<table border="0" cellpadding="0" cellspacing="0"
       style="width: 100%; margin: 0; padding: 0; page-break-inside: avoid; font-family: {{ $scale['font'] }}; color: #000;">
    <tr>
        <td colspan="{{ $columns }}" style="font-size: {{ $scale['code'] }}pt; font-weight: bold; line-height: 1; padding-top: {{ $scale['code_padding_top'] }}mm; padding-bottom: {{ $scale['code_padding_bottom'] }}mm;">{{ $label['code'] }}</td>
    </tr>

    <tr>
        <td colspan="{{ $columns }}" style="font-size: {{ $scale['name'] }}pt; line-height: 1.15; border-bottom: 0.2mm solid #000; padding-bottom: {{ $scale['gap'] }}mm;">{{ $label['name'] }}</td>
    </tr>

    <tr>
        <td width="{{ $scale['text_width'] }}%" valign="top" style="padding-right: {{ $scale['gap'] * 2 }}mm;">
            <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; line-height: 1.2; font-size: {{ $scale['body'] }}pt;">
                @foreach ($lines as $index => $line)
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt;@if ($index === 0) padding-top: {{ $scale['gap'] * 2.5 }}mm;@endif">{{ $line }}</td>
                    </tr>
                @endforeach

                @if ($show['custom_text'] && filled($customText))
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt; padding-top: {{ $scale['gap'] * 1.5 }}mm;">{{ $customText }}</td>
                    </tr>
                @endif

                @php($isFirstAddressBlock = true)
                @foreach (['signature', 'eu_responsible_person', 'es_importer'] as $addressBlock)
                    @if ($show[$addressBlock] && $label[$addressBlock])
                        @foreach (explode("\n", $label[$addressBlock]) as $index => $addressLine)
                            <tr>
                                <td style="font-size: {{ $scale['signature'] }}pt;@if ($index === 0 && $isFirstAddressBlock) padding-top: {{ $scale['gap'] * 1.5 }}mm;@endif">{{ $addressLine }}</td>
                            </tr>
                        @endforeach
                        @php($isFirstAddressBlock = false)
                    @endif
                @endforeach

                @if ($show['weight'] && $label['weight'])
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt; padding-top: {{ $scale['gap'] * 1.5 }}mm;">{{ __('Weight') }}: {{ $label['weight'] }}</td>
                    </tr>
                @endif
            </table>
        </td>

        @if ($hasBarcode)
            <td width="{{ $scale['barcode_width'] }}%" valign="top" align="center" style="padding-top: {{ $scale['gap'] }}mm;">
                <barcode code="{{ $label['barcode']['number'] }}"
                         type="{{ $label['barcode']['type'] }}"
                         size="{{ $scale['barcode'] }}"
                         height="{{ $scale['barcode_height'] }}"/>
            </td>
        @endif

        @if ($hasImage)
            <td width="{{ $scale['image_width'] }}%" valign="top" align="right" style="padding-top: {{ $scale['gap'] }}mm;">
                <img src="{{ $label['image_path'] }}" width="{{ $scale['image'] }}" height="{{ $scale['image'] }}"/>
            </td>
        @endif
    </tr>
</table>
