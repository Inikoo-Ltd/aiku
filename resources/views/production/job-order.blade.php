<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        td { vertical-align: top; }
        .items td { border-left: 0.1mm solid #000; border-right: 0.1mm solid #000; border-bottom: 0.1mm solid #cfcfcf; padding: 5px 8px 4px; }
        .items tr.last td { border-bottom: 0.1mm solid #000; }
        .items thead td { background-color: #EEEEEE; border: 0.1mm solid #000; }
    </style>
</head>
<body>
<htmlpageheader name="header">
    <table width="100%" style="font-size: 9pt;">
        <tr>
            <td><span style="font-weight: bold; font-size: 12pt;">{{ $title }}</span></td>
            <td style="text-align: right;">
                @if($sentAt)
                    {{ __('Sent to queue') }}: <b>{{ $sentAt->format('D j M g:ia') }}</b>
                @else
                    <b style="color: tomato;">{{ __('Preview only, not released to floor yet!') }}</b>
                @endif
            </td>
        </tr>
    </table>
</htmlpageheader>
<htmlpagefooter name="footer">
    <table width="100%" style="border-top: 0.1mm solid #000; font-size: 8pt;">
        <tr>
            <td width="33%">{{ __('Printed') }}: {{ $printedAt }}</td>
            <td width="33%" style="text-align: center;">{{ __('Page') }} {PAGENO} {{ __('of') }} {nbpg}</td>
            <td width="34%" style="text-align: right;">{{ $jobOrder->employee?->contact_name }}</td>
        </tr>
    </table>
</htmlpagefooter>
<sethtmlpageheader name="header" value="on" show-this-page="1"/>
<sethtmlpagefooter name="footer" value="on"/>

<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;">
    <thead>
    <tr>
        <td style="width: 12%;">{{ __('Code') }}</td>
        <td>{{ __('Unit description') }}</td>
        <td style="width: 8%; text-align: right;">{{ __('Units') }}</td>
        <td style="width: 7%; text-align: right;">{{ __('SKOs') }}</td>
        <td style="width: 10%;">{{ __('Worker') }}</td>
        <td style="width: 10%; text-align: center;">W</td>
        <td style="width: 5%; text-align: right;">{{ __('QC') }}</td>
    </tr>
    </thead>
    <tbody>
    @foreach($items as $item)
        <tr class="{{ $loop->last ? 'last' : '' }}">
            <td>{{ $item['code'] }}</td>
            <td>{{ $item['description'] }}</td>
            <td style="text-align: right;">{{ $item['units'] }}</td>
            <td style="text-align: right;">{{ $item['skos'] }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
