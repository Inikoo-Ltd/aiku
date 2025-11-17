/*
 * author Louis Perez
 * created on 17-11-2025-10h-56m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

<html>
<head>
    <title>{{ $reference }}</title>
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
            header: myHeader;
            footer: myFooter;
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
            margin: 0;
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

        .items td.blank_total {
            background-color: #FFFFFF;
            border: 0 none #000000;
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
<htmlpageheader name="myHeader">
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

            <td style="text-align: right;">{{ __('Topup Reference') }}<br/>
                <b>{{ $reference }}</b>
            </td>

        </tr>
    </table>
</htmlpageheader>

<sethtmlpageheader name="myHeader" value="on" show-this-page="1"/>
<sethtmlpagefooter name="myFooter" value="on"/>

<table width="100%" style="margin-top: 40px">
    <tr>
        <td>
            <h1>
                {{__('Credit Account Top-Up Confirmation')}}
            </h1>
        </td>
    </tr>
</table>
<table width="100%" style="font-family: sans-serif; margin-top: 20px" cellpadding="0">
    <tr>
        <td width="50%" style="vertical-align:bottom;border: 0 solid #888888;">
            <div>
                <div>
                    {{ __('Customer') }}: <b>{{ $customer->name }}</b>
                    ({{ $customer->reference }})
                </div>
                <div>
                    <span class="address_label">{{ __('Email') }}:</span> <span
                        class="address_value">{{ $customer->email }}</span>
                </div>

                <div>
                    <span class="address_label">{{ __('Phone') }}:</span> <span
                        class="address_value">{{ $customer->phone }}</span>
                </div>
                @if($customer->identity_document_number)
                    {{__('Registration Number')}}: {{$customer->identity_document_number}}
                @endif
                @if($customer->taxNumber && $customer->taxNumber->status == 'valid')
                    <div>
                        <span class="address_label">{{ __('Tax Number') }}:</span> <span
                            class="address_value">{{ $customer->taxNumber->number }}</span>
                    </div>
                @endif
            </div>
        </td>
        {{-- <td width="50%" style="vertical-align:bottom;border: 0 solid #888888;text-align: right">

        </td> --}}
    </tr>
</table>
@php
    $deliveryAddress = $customer->deliveryAddress()->first();
@endphp
<table width="100%" style="font-family: sans-serif;" cellpadding="10">
    <tr>
        @if($deliveryAddress)
            <td width="45%" style="border: 0.1mm solid #888888;"><span
                    style="font-size: 7pt; color: #555555; font-family: sans-serif;">{{ __('Billing address') }}:</span>
                <div>
                    {{ $deliveryAddress->address_line_1 }}
                </div>
                <div>
                    {{ $deliveryAddress->address_line_2 }}
                </div>
                <div>
                    {{ $deliveryAddress->administrative_area }}
                </div>
                <div>
                    {{ $deliveryAddress->locality }}
                </div>
                <div>
                    {{ $deliveryAddress->postal_code }}
                </div>
                <div>
                    {{ $deliveryAddress->country->name }}
                </div>
            </td>
            <td width="10%">&nbsp;</td>
        @endif
    </tr>
</table>

<br>

{{ __('Here is your receipt on the Account Balance Top-up') }}

<br><br>

<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
        <tr>
            <td style="width:14%;text-align:right">{{ __('Amount') }}</td>
            <td style="text-align:left" colspan="2">{{ __('Description') }}</td>
            <td style="text-align:left" colspan="2">{{ __('Payment Method') }}</td>
            <td style="text-align:left">{{ __('Date of Transaction') }}</td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="text-align:right">{{ $topup->currency->symbol . $topup->amount }}</td>
            <td style="text-align:left" colspan="2">{{ __('Credit Account Balance Top-Up') }}</td>
            <td style="text-align:left" colspan="2">{{ $payment->paymentAccount->name }}</td>
            <td style="text-align:left">{{ $topup->created_at }}</td>
        </tr>
    </tbody>
</table>

<br>
<br>

*<span style="font-style:italic">&nbsp;{{ __('This is a prepayment for future purchases. A VAT invoice will be issued upon delivery of goods.') }}</span>

<br>
<br>

@if($shop->footer)
    <div>
        {!! $shop->footer !!}
    </div>
@endif

<htmlpagefooter name="myFooter">
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
