{{--
* Author: Artha <artha@aw-advantage.com>
* Created: Fri, 23 Jun 2023 11:43:59 Central Indonesia Time, Sanur, Bali, Indonesia
* Copyright (c) 2023, Raul A Perusquia Flores
*/
--}}

<html>
<head>
    <title>{{ $filename }}</title>
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
        .barcode {
            padding: 1.5mm;
            margin: 0;
            vertical-align: top;
            color: #000000;
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
                {{ $shop->name }}
                @if ($shop->address)
                <div style="font-size:7pt">
                    {!! $shop->address->getHtml() !!}
                </div>
                @endif
                <div style="font-size:7pt">
                    {{ $shop->website['domain'] }}
                </div>
            </td>

            <td style="text-align: right;">
                <div>
                    <barcode code="{{ 'pad-'.$delivery->slug }}" type="C128B" />
                </div>
                <div>
                    <b>{{ 'pad-'.$delivery->slug }}</b>
                </div>
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
                {{ __('Pallet Delivery') }}
            </h1>
        </td>
        <td style="text-align: right">
            @if(in_array($delivery->state, [\App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum::RECEIVED, \App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum::BOOKED_IN]))
            <div>
                {{ __('Received Date') }}: <b>{{ $delivery->received_at->format('j F Y') }}</b>
            </div>
            @endif
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
                @if($customer->phone)
                    <div>
                        <span class="address_label">{{ __('Phone') }}:</span> <span class="address_value">{{ $customer->phone }}</span>
                    </div>
                @endif
            </div>
        </td>
        <td width="50%" style="vertical-align:bottom;border: 0 solid #888888;text-align: right">
            <div style="text-align:right;">
                {{ __('State') }}: <b>{{ $delivery->state->labels()[$delivery->state->value] }}</b>
            </div>
        </td>
    </tr>
</table>

<br>

<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
    <tr>
        <td style="width:20%; text-align:left">{{ __('Reference') }}</td>
        <td style="width:50%; text-align:left">{{ __("Pallet Reference (Customer's)") }}</td>
        <td style="text-align:right">{{ __('Notes') }}</td>
    </tr>
    </thead>
    <tbody>

    @foreach($delivery->pallets as $pallet)
    <tr class="@if($loop->last) last @endif">
        <td style="text-align:left">{{ $pallet->reference }}</td>
        <td style="text-align:left">{{ $pallet->customer_reference }}</td>
        <td style="text-align:left">{{ $pallet->notes }}</td>
    </tr>
    @endforeach

    </tbody>

</table>

<htmlpagefooter name="myFooter">
    <div
        style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; margin-top: 120px"></div>
    <table width="100%">
        <tr>
        <tr>
            <td width="33%" style="color:#000;text-align: left;">
                <small>{{ $shop->name }}<br> {{ __('VAT Number') }}:
                    <b>{{ $shop->identity_document_number }}</b>
                    <br>
                    {{ __('Registration Number') }}: {{ $shop->identity_document_number }}</small>
            </td>
            <td width="33%" style="color:#000;text-align: center">
                {{ __('Page') }} {PAGENO} {{ __('of') }} {nbpg}
            </td>
            <td width="34%" style="text-align: right;">
                <small>{{ $shop->phone }}<br>
                    {{ $shop->email }}
                </small>
            </td>
        </tr>
    </table>
    <br><br>
</htmlpagefooter>
</body>
</html>
