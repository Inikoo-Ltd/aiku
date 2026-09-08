{{--
* Author: stewicca <stewicalf@gmail.com>
* Created: Thu, 16 Jul 2026, Bali, Indonesia
* Copyright (c) 2026, Steven Wicca Alfredo
--}}

<table style="width: 100%; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;" border="0">
    <tr>
        <td style="text-align: left; font-weight: bold; font-size: 11pt;">{{ $code }}</td>
    </tr>

    @if($name)
        <tr>
            <td style="text-align: left; font-size: 8pt;">{{ $name }}</td>
        </tr>
    @endif

    <tr>
        <td style="text-align: center; padding-top: 1.5mm;">
            <barcode code="{{ $barcodeNumber }}" type="{{ $barcodeType }}" size="0.85" height="0.85" text="1"/>
        </td>
    </tr>
</table>
