{{--
    The unit label on a long, low stock such as 125 x 37. The wording runs down the left with the
    rule under the name alone, the barcode stands between it and the picture, and the picture takes
    the full height of the label, as Aurora laid it out.
--}}

@php
    $hasImage   = $show['image'] && $label['image_path'];
    $hasBarcode = (bool) $label['barcode']['number'];

    $lines = array_values(array_filter([
        $show['made_in'] ? $label['made_in'] : null,
        $show['manufactured_by'] ? $label['manufactured_by'] : null,
    ]));
@endphp

<table border="0" cellpadding="0" cellspacing="0"
       style="width: 100%; margin: 0; padding: 0; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; color: #000;">
    <tr>
        <td width="{{ $scale['text_width'] }}%" valign="top" style="padding-right: {{ $scale['gap'] * 2 }}mm;">
            <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; line-height: 1.2; font-size: {{ $scale['body'] }}pt;">
                <tr>
                    <td style="font-size: {{ $scale['code'] }}pt; font-weight: bold; line-height: 1; padding-top: {{ $scale['gap'] * 2 }}mm; padding-bottom: {{ $scale['gap'] }}mm;">{{ $label['code'] }}</td>
                </tr>

                @if ($label['name'])
                    <tr>
                        <td style="font-size: {{ $scale['name'] }}pt; line-height: 1.15; border-bottom: 0.2mm solid #000; padding-bottom: {{ $scale['gap'] }}mm;">{{ $label['name'] }}</td>
                    </tr>
                @endif

                @foreach ($lines as $index => $line)
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt; padding-bottom: {{ $scale['line_gap'] }}mm;@if ($index === 0) padding-top: {{ $scale['gap'] * 2.5 }}mm;@endif">{{ $line }}</td>
                    </tr>
                @endforeach

                @if ($show['custom_text'] && filled($customText))
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt; padding-top: {{ $scale['gap'] * 1.5 }}mm; padding-bottom: {{ $scale['line_gap'] }}mm;">{{ $customText }}</td>
                    </tr>
                @endif

                @if ($show['signature'] && $label['signature'])
                    @foreach (explode("\n", $label['signature']) as $index => $signatureLine)
                        <tr>
                            <td style="font-size: {{ $scale['signature'] }}pt; padding-bottom: {{ $scale['line_gap'] }}mm;@if ($index === 0) padding-top: {{ $scale['gap'] * 1.5 }}mm;@endif">{{ $signatureLine }}</td>
                        </tr>
                    @endforeach
                @endif

                @if ($show['weight'] && $label['weight'])
                    <tr>
                        <td style="font-size: {{ $scale['body'] }}pt; padding-top: {{ $scale['gap'] * 1.5 }}mm;">{{ __('Weight') }}: {{ $label['weight'] }}</td>
                    </tr>
                @endif
            </table>
        </td>

        @if ($hasBarcode)
            <td width="{{ $scale['barcode_width'] }}%" valign="middle" align="center">
                <barcode code="{{ $label['barcode']['number'] }}"
                         type="{{ $label['barcode']['type'] }}"
                         size="{{ $scale['barcode'] }}"
                         height="{{ $scale['barcode_height'] }}"/>
            </td>
        @endif

        @if ($hasImage)
            <td width="{{ $scale['image_width'] }}%" valign="middle" align="right">
                <img src="{{ $label['image_path'] }}" width="{{ $scale['image'] }}" height="{{ $scale['image'] }}"/>
            </td>
        @endif
    </tr>
</table>
