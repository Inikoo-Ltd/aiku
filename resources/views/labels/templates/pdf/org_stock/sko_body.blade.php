{{--
* Author Louis Perez
* Created on 23-09-2026-11h-34m
* GitHub: https://github.com/louis-perez
* Copyright 2026
--}}

{{--
    One outer packing label: what the box is, how many are in it, and the barcode that identifies
    the box itself. It carries no origin, manufacturer, weight or signature, because the SKO label
    is read across a warehouse rather than by a customer holding the product.

    Four parts of six go to the wording and two to the picture, with the barcode run across the full
    width beneath both. Every gap sits on a cell: mPDF ignores padding on a span or on a block
    inside a table cell, which is also why the code's panel is its own shrink-to-fit table.
--}}

@php
    $hasImage   = $show['image'] && $label['image_path'];
    $hasBarcode = filled($skoBarcode ?? null);
    $columns    = $hasImage ? 2 : 1;
@endphp

<table border="0" cellpadding="0" cellspacing="0"
       style="width: 100%; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; color: #000;">
    <tr>
        <td width="{{ $scale['text_width'] }}%" valign="top" align="center" style="font-size: {{ $scale['name'] }}pt; line-height: 1;">
            <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
                <tr>
                    <td align="center" style="padding-bottom: {{ $scale['gap'] }}mm;">
                        <table border="0" cellpadding="0" cellspacing="0" align="center">
                            <tr>
                                <td style="background-color: #000; color: #fff; font-weight: bold; font-size: {{ $scale['code'] }}pt; line-height: 1; padding: {{ $scale['code_padding_y'] }}mm {{ $scale['code_padding_x'] }}mm;">{{ $label['code'] }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @if ($label['name'])
                    <tr>
                        <td align="center" style="font-size: {{ $scale['name'] }}pt; line-height: 1.3;">@if ($label['packed_in'] > 1)<b>{{ $label['packed_in'] }}x</b> @endif{{ $label['name'] }}</td>
                    </tr>
                @endif

                @if ($show['custom_text'] && filled($customText))
                    <tr>
                        <td align="center" style="font-size: {{ $scale['name'] }}pt; line-height: 1.3; padding-top: {{ $scale['gap'] }}mm;">{{ $customText }}</td>
                    </tr>
                @endif
            </table>
        </td>

        @if ($hasImage)
            <td width="{{ $scale['image_width'] }}%" valign="middle" align="right">
                <img src="{{ $label['image_path'] }}" width="{{ $scale['image'] }}" height="{{ $scale['image'] }}"/>
            </td>
        @endif
    </tr>

    @if ($hasBarcode)
        <tr>
            {{-- mPDF stops a shade short of the picture's full height, so the cell carries a small
                 clearance to keep the number off the feet of the bars. --}}
            <td colspan="{{ $columns }}" align="center" style="padding-top: {{ $scale['gap'] }}mm; padding-bottom: {{ $scale['gap'] }}mm;">
                <img src="{{ $skoBarcode['uri'] }}" width="{{ $skoBarcode['width'] }}" height="{{ $skoBarcode['height'] }}"/>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $columns }}" align="center" style="font-size: {{ $scale['name'] }}pt; line-height: 1;">{{ $label['barcode']['number'] }}</td>
        </tr>
    @endif
</table>
