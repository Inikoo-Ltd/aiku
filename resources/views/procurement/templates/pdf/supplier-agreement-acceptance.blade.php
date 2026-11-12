<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $version->title }} v{{ $version->version }} · {{ $supplier->code }}</title>
    @include('procurement.templates.pdf.purchase-order-styles')
    <style>
        .agreement { font-size: 9pt; line-height: 1.4; }
        .agreement h1, .agreement h2, .agreement h3 { font-size: 10.5pt; margin: 10px 0 4px; }
        .agreement p { margin: 0 0 6px; }
        .proof { margin-top: 14px; border: 1px solid #999; padding: 8px; }
        .proof td { padding: 2px 6px 2px 0; vertical-align: top; }
        .proof td.key { color: #666; width: 34%; }
        .hash { font-family: monospace; font-size: 7.5pt; word-break: break-all; }
    </style>
</head>
<body>
<h1 style="font-size: 14pt; margin: 0;">{{ $version->title }}</h1>
<div class="label">Version {{ $version->version }} · published {{ $version->published_at?->format('d M Y') }}</div>

<div class="agreement">{!! $bodyHtml !!}</div>

<div class="proof">
    <div class="label">Acceptance</div>
    <p>☑ I have read and accept this Supplier Agreement and confirm that I am authorised to accept it on behalf of the supplier.</p>
    <table>
        <tr><td class="key">Supplier legal name</td><td>{{ $acceptance->supplier_legal_name }}</td></tr>
        <tr><td class="key">Authorised person</td><td>{{ $acceptance->authorised_person }}</td></tr>
        <tr><td class="key">Position</td><td>{{ $acceptance->position }}</td></tr>
        <tr><td class="key">Date</td><td>{{ $acceptance->accepted_at->utc()->format('d M Y H:i:s') }} UTC (time received by our server)</td></tr>
        <tr><td class="key">Supplier in Aiku</td><td>{{ $supplier->name }} ({{ $supplier->code }})</td></tr>
        <tr><td class="key">Accepted through</td><td>Supplier portal link {{ $acceptance->shareLink?->reference() ?? '-' }}{{ $acceptance->shareLink?->email ? ' · '.$acceptance->shareLink->email : '' }}</td></tr>
        <tr><td class="key">IP address</td><td>{{ $acceptance->ip ?? '-' }}{{ $acceptance->ipGeolocation?->city ? ' · '.$acceptance->ipGeolocation->city : '' }}{{ $acceptance->country_code ? ' · '.$acceptance->country_code : '' }}</td></tr>
        <tr><td class="key">Language shown</td><td>{{ $acceptance->locale ?? '-' }} (the English text above is the text accepted)</td></tr>
        <tr><td class="key">Browser</td><td>{{ Str::limit($acceptance->user_agent ?? '-', 160) }}</td></tr>
        <tr><td class="key">Text fingerprint (SHA-256)</td><td class="hash">{{ $acceptance->body_hash }}{{ $hashMatches ? '' : ' · DOES NOT MATCH THE TEXT PRINTED' }}</td></tr>
    </table>
</div>
</body>
</html>
