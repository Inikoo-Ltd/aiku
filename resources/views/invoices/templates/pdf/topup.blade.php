<html>
<head>
    <title>{{ $topup->slug }}</title>
    <style>
        @page {
            size: 8.27in 11.69in; /* <length>{1,2} | auto | portrait | landscape */
            /* 'em' 'ex' and % are not allowed; length values are width height */
            margin-top: 15%; /* <any of the usual CSS values for margins> */
            /*(% of page-box width for LR, of height for TB) */
            margin-bottom: 13%;
            margin-right: 8%;
            margin-left: 8%;
            margin-header: 1mm; /* <any of the usual CSS values for margins> */
            margin-footer: 5mm; /* <any of the usual CSS values for margins> */
            marks: 'cross'; /*crop | cross | none*/
            header: myheader;
            footer: myfooter;
            /* background: ...
            background-image: ...
            background-position ...
            background-repeat ...
            background-color ...
            background-gradient: ... */
        }

        body {
            font-family: sans-serif;
            font-size: 10pt;
        }

        p {
            margin: 0pt;
        }

        h1 {
            font-size: 14pt
        }

        td {
            vertical-align: top;
        }

        .items td {
            border-left: 0.1mm solid #000000;
            border-right: 0.1mm solid #000000;
            border-bottom: 0.1mm solid #cfcfcf;
            padding-bottom: 4px;
            padding-top: 5px;
        }


        .items tbody.out_of_stock td {
            color: #777;
            font-style: italic
        }

        .items tbody.totals td {
            text-align: right;
            border: 0.1mm solid #222;
        }

        .items tr.total_net td {
            border-top: 0.3mm solid #000;
        }

        .items tr.total td {
            border-top: 0.3mm solid #000;
            border-bottom: 0.3mm solid #000;
        }

        .items tr.last td {

            border-bottom: 0.1mm solid #000000;
        }

        table thead td, table tr.title td {
            background-color: #EEEEEE;
            text-align: center;
            border: 0.1mm solid #000000;
        }

        .items td.blanktotal {
            background-color: #FFFFFF;
            border: 0mm none #000000;
            border-top: 0.1mm solid #000000;
            border-right: 0.1mm solid #000000;
        }


        div.inline {
            float: left;
        }

        .clearBoth {
            clear: both;
        }

        .hide {
            display: none
        }
    </style>
</head>
<body>
<htmlpageheader name="myheader">
    <br><br>
    <table width="100%" style="font-size: 9pt;">
        <tr>
            <td style="width:250px;padding-left:10px;">
                {{$shop->organisation->name}}
                <div style="font-size:7pt">
                    {{$shop->name}}
                </div>
                <div style="font-size:7pt">
                    {{$shop->address->address_line_1}}
                </div>
                <div style="font-size:7pt">
                    {{$shop->address->address_line_2}}
                </div>
                <div style="font-size:7pt">
                    {{$shop->address->locality}} {{$shop->address->postal_code}}
                </div>
                <div style="font-size:7pt">
                    www.{{$shop->website->domain}}
                </div>
            </td>

            <td style="text-align: right;">{{ __('Proforma Number') }}<br/>
                <b>{{ $topup->reference }}</b>
            </td>

        </tr>
    </table>
</htmlpageheader>

<sethtmlpageheader name="myheader" value="on" show-this-page="1"/>
<sethtmlpagefooter name="myfooter" value="on"/>

<table width="100%" style="margin-top: 40px">
    <tr>
        <td>
            <h1>
                {{__('Topup')}} {{ $topup->reference }}
            </h1>
        </td>
        <td style="text-align: right">
            <div>
                {{ __('Date') }}: <b>{{ $topup->created_at->format('j F Y') }}</b>
            </div>
        </td>
    </tr>
</table>
<table width="100%" style="font-family: sans-serif; margin-top: 20px" cellpadding="0">
    <tr>
        <td width="50%" style="vertical-align:bottom;border: 0mm solid #888888;">
            <div>
                @if($hide_payment_status)
                    <div>
                        {{ __('Payment State') }}:
                        <b>{{ $topup->pay_status->labels()[$topup->pay_status->value] }}</b>
                    </div>
                @endif
                <div>
                    {{ __('Customer') }}: <b>{{ $topup->customer['name'] }}</b>
                    ({{ $topup->customer['reference'] }})
                </div>

                <div>
                    <span class="address_label">{{ __('Email') }}:</span> <span
                        class="address_value">{{ $topup->customer['email'] }}</span>
                </div>

                <div>
                    <span class="address_label">{{ __('Phone') }}:</span> <span
                        class="address_value">{{ $topup->customer['phone'] }}</span>
                </div>
                @if($topup->tax_number  && $topup->tax_number_valid)
                    <div>
                        <span class="address_label">{{ __('Tax Number') }}:</span> <span
                            class="address_value">{{ $topup->tax_number }}</span>
                    </div>
                @endif
            </div>
        </td>
        <td width="50%" style="vertical-align:bottom;border: 0mm solid #888888;text-align: right">

        </td>
    </tr>
</table>
<table width="100%" style="font-family: sans-serif;" cellpadding="10">
    <tr>
        @if($topup->address)
            <td width="45%" style="border: 0.1mm solid #888888;"><span
                    style="font-size: 7pt; color: #555555; font-family: sans-serif;">{{ __('Billing address') }}:</span>
                <div>
                    {{ $topup->address->address_line_1 }}
                </div>
                <div>
                    {{ $topup->address->address_line_2 }}
                </div>
                <div>
                    {{ $topup->address->administrative_area }}
                </div>
                <div>
                    {{ $topup->address->locality }}
                </div>
                <div>
                    {{ $topup->address->postal_code }}
                </div>
                <div>
                    {{ $topup->address->country->name }}
                </div>
            </td>
            <td width="10%">&nbsp;</td>
        @endif

    </tr>
</table>
<br>

<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
    <tr>
        <td style="width:14%;text-align:right">{{ __('Amount') }}</td>
        <td style="text-align:left" colspan="2">{{ __('Description') }}</td>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td style="text-align:right">{{ $topup->currency->symbol . $topup->amount }}</td>
        <td style="text-align:left" colspan="2">{{ __('Topup to balance') }}</td>
    </tr>
    </tbody>
</table>

<br>
<br>

@if($topup->payments->count() >0)
    <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
        <tr class="title">
            <td colspan="5">{{ __('Payments') }}</td>
        </tr>

        <tr class="title">
            <td style="width:20%;text-align:left">{{ __('Method') }}</td>
            <td style="text-align:right">{{ __('Date') }}</td>
            <td style="text-align:left">{{ __('Status') }}</td>
            <td style="text-align:left">{{ __('Reference') }}</td>
            <td style="text-align:right; width:14%;">{{ __('Amount') }}</td>
        </tr>

        <tbody>
        @foreach($topup->payments as $payment)
            <tr class="@if($loop->last) last @endif">
                <td style="text-align:left">
                    {{ $payment->paymentAccount['name'] }}
                </td>
                <td style="text-align:right">
                    {{ $payment->updated_at->format('F j, Y H:i a') }}
                </td>
                <td style="text-align:left">{{ $payment->state->labels()[$payment->state->value] }}</td>
                <td style="text-align:left">{{ $payment->reference }}</td>
                <td style="text-align:right">{{ $topup->currency->symbol . $payment->amount }}</td>
            </tr>
        @endforeach
        </tbody>

    </table>
@endif
<br>
<br>

@if($topup->footer)
    <div>
        {!! $topup->footer !!}
    </div>
@endif

<htmlpagefooter name="myfooter">
    <div
        style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 5mm; margin-top: 5mm;"></div>
    <table width="100%">
        <tr>
        <tr>
            <td width="33%" style="color:#000;text-align: left;">
                <small>
                    {{$shop->name}}<br>
                    @if($shop->taxNumber)
                        {{__('VAT Number')}}:<b>{{$shop->taxNumber?->getFormattedTaxNumber()}}</b><br>
                    @endif
                    @if($shop->identity_document_number)
                        {{__('Registration Number')}}: {{$shop->identity_document_number}}
                    @endif
                </small>
            </td>
            <td width="33%" style="color:#000;text-align: center">
                {{ __('Page') }} {PAGENO} {{ __('of') }} {nbpg}
            </td>
            <td width="34%" style="text-align: right;">
                <small>{{$shop->phone}}<br>
                    {{$shop->email}}
                </small>
            </td>
        </tr>
    </table>
    <br><br>
</htmlpagefooter>
</body>
</html>
