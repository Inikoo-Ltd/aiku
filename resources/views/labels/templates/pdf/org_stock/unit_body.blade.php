{{--
* Author Louis Perez
* Created on 23-09-2026-11h-34m
* GitHub: https://github.com/louis-perez
* Copyright 2026
--}}

{{--
    One unit label. Included by both the single label and the A4 sheet so a sheet of 27 is the same
    artwork 27 times over, not a second layout that drifts away from the first.

    Columns collapse onto the text when their content is switched off, so a label without an image
    lets the barcode and the wording use the room rather than leaving a hole where the picture was.
--}}

@php
    $hasImage   = $show['image'] && $label['image_path'];
    $hasBarcode = (bool) $label['barcode']['number'];

    $lines = array_values(array_filter([
        $show['manufactured_by'] ? $label['manufactured_by'] : null,
        $show['made_in'] ? $label['made_in'] : null,
    ]));
@endphp

{{-- No height on the table: mPDF shares any leftover height out between the rows rather than
     leaving it at the foot, which pushed the code line down from the top edge of the label. --}}
<table border="0" cellpadding="0" cellspacing="0"
       style="width: 100%; margin: 0; padding: 0; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; color: #000;">
    <tr>
        {{-- The cell states the code's font size as well as the span does. Without it mPDF sizes the
             row's line box from the document default, which stamped the same fixed gap above the
             code on every stock and stood out most on the smallest ones. --}}
        <td colspan="3" style="border-bottom: 0.2mm solid #000; padding: 0 0 {{ $scale['gap'] }}mm 0; font-size: {{ $scale['code'] }}pt; line-height: 1;">
            <span style="font-size: {{ $scale['code'] }}pt; font-weight: bold;">{{ $label['code'] }}</span>
            @if ($label['name'])
                <span style="font-size: {{ $scale['name'] }}pt;">{{ $label['name'] }}</span>
            @endif
        </td>
    </tr>

    <tr>
        {{-- mPDF ignores padding and margin on a plain block inside a table cell, but honours it on
             a cell, so the lines of the text column are rows rather than divs. That is what makes the
             spacing below actually appear on the printed label. --}}
        <td width="{{ $scale['text_width'] }}%" valign="top" style="padding-top: {{ $scale['gap'] }}mm; font-size: {{ $scale['body'] }}pt;">
            {{-- mPDF does not carry the cell's font size into a nested table, it falls back to the
                 document default, so every row states its own size. --}}
            <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; line-height: 1.25; font-size: {{ $scale['body'] }}pt;">
                @foreach ($lines as $line)
                    <tr><td style="font-size: {{ $scale['body'] }}pt; padding-bottom: {{ $scale['line_gap'] }}mm;">{{ $line }}</td></tr>
                @endforeach

                @if ($show['custom_text'] && filled($customText))
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt; padding-top: {{ round($scale['gap'] * 1.6, 2) }}mm; padding-bottom: {{ round($scale['gap'] * 0.6, 2) }}mm;">{{ $customText }}</td>
                    </tr>
                @endif

                @if ($show['signature'] && $label['signature'])
                    @foreach (explode("\n", $label['signature']) as $index => $signatureLine)
                        <tr>
                            <td style="font-size: {{ $scale['signature'] }}pt; padding-bottom: {{ $scale['line_gap'] }}mm;@if ($index === 0) padding-top: {{ $scale['gap'] }}mm;@endif">{{ $signatureLine }}</td>
                        </tr>
                    @endforeach
                @endif
            </table>
        </td>

        @if ($hasBarcode)
            <td width="{{ $scale['barcode_width'] }}%" valign="middle" align="center" style="padding-top: {{ $scale['gap'] }}mm;">
                <barcode code="{{ $label['barcode']['number'] }}"
                         type="{{ $label['barcode']['type'] }}"
                         size="{{ $scale['barcode'] }}"
                         height="{{ $scale['barcode_height'] }}"/>
            </td>
        @endif

        @if ($hasImage)
            <td width="{{ $scale['image_width'] }}%" valign="middle" align="right" style="padding-top: {{ $scale['gap'] }}mm;">
                <img src="{{ $label['image_path'] }}" width="{{ $scale['image'] }}" height="{{ $scale['image'] }}"/>
            </td>
        @endif
    </tr>

    @if ($show['weight'] && $label['weight'])
        <tr>
            <td colspan="3" valign="bottom" style="padding-top: {{ $scale['gap'] }}mm; font-size: {{ $scale['body'] }}pt;">
                {{ __('Weight') }}: {{ $label['weight'] }}
            </td>
        </tr>
    @endif
</table>
