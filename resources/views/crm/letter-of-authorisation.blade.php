<html>
<head>
    <style>
        @page {
            size: 8.27in 11.69in;
            margin-top: 12mm;
            margin-bottom: 35mm;
            margin-left: 20mm;
            margin-right: 20mm;
            margin-footer: 8mm;
            footer: letterFooter;
        }

        body {
            font-family: sans-serif;
            font-size: 11pt;
            line-height: 1.4;
        }

        p {
            margin: 0 0 10px 0;
        }

        .footer {
            font-size: 8pt;
            color: #666666;
        }
    </style>
</head>
<body>
<htmlpagefooter name="letterFooter">
    <div class="footer">{!! $footer !!}</div>
</htmlpagefooter>

@if($logoPath)
    <img src="{{ $logoPath }}" style="height: 25mm; margin-bottom: 10mm" alt="">
@endif

{!! $body !!}

@if($signaturePath)
    <img src="{{ $signaturePath }}" style="height: 20mm; margin-top: 4mm" alt="">
@endif

@if($signatory)
    <p style="margin-top: 4mm">{{ __('Contact') }}: {{ $signatory }}</p>
@endif
</body>
</html>
