<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr>
        <td style="padding:28px 32px;font-size:15px;line-height:1.6;">
            <p style="margin:0 0 16px;">{{ __('Dear :name,', ['name' => $supplierName]) }}</p>
            <p style="margin:0 0 16px;">{{ __('Please find attached our purchase order :reference.', ['reference' => $reference]) }}</p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px;">
                <tr><td style="padding:2px 16px 2px 0;color:#6b7280;">{{ __('Reference') }}</td><td style="padding:2px 0;font-weight:600;">{{ $reference }}</td></tr>
                <tr><td style="padding:2px 16px 2px 0;color:#6b7280;">{{ __('Date') }}</td><td style="padding:2px 0;">{{ $date->format('d M Y') }}</td></tr>
                <tr><td style="padding:2px 16px 2px 0;color:#6b7280;">{{ __('Items') }}</td><td style="padding:2px 0;">{{ $numberItems }}</td></tr>
            </table>
            @if(!empty($supplierOrders))
                <p style="margin:0 0 8px;">{{ __('It has one order for each supplier:') }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px;">
                    @foreach($supplierOrders as $supplierOrder)
                        <tr><td style="padding:2px 16px 2px 0;font-weight:600;">{{ $supplierOrder['reference'] }}</td><td style="padding:2px 0;color:#6b7280;">{{ $supplierOrder['supplier'] }}</td></tr>
                    @endforeach
                </table>
            @endif
            <p style="margin:0 0 16px;">{{ __('Please confirm the order, prices and the expected dispatch date by replying to this email.') }}</p>
            <p style="margin:0;">{{ __('Kind regards,') }}<br>{{ $organisationName }}</p>
        </td>
    </tr>
</table>
</body>
</html>
