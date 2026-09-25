<table style="width: 100%; font-size: 2mm; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;" border="0">
    <tr>
        <td style="border-bottom: 1px solid #000;"><b>{{ $name }}</b></td>
    </tr>
    <tr>
        <td style="height: 1mm;"></td>
    </tr>
    @if($weight)
        <tr>
            <td><b>{{ __('Weight') }}:</b> {{ $weight }} &#8494;</td>
        </tr>
    @endif
    <tr>
        <td><b>{{ __('Materials/Ingredients') }}:</b> {{ $ingredients }}</td>
    </tr>
</table>
