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
                {{ $shop->name }}
                <div style="font-size:7pt">
                    {{ $shop->company_name }}
                </div>
                <div style="font-size:7pt">
                    {{ $shop->address['address_line_1'] }}
                </div>
                <div style="font-size:7pt">
                    {{ $shop->address['address_line_2'] }}
                </div>
                <div style="font-size:7pt">
                    {{ $shop->address['dependent_locality'] }}
                </div>
                <div style="font-size:7pt">
                    {{ $shop->address['locality'] }}
                </div>
                <div style="font-size:7pt">
                    {{ $shop->website['domain'] }}
                </div>
            </td>

            <td style="text-align: right;">
                <div>
                    <barcode code="{{ 'par-'.$return->slug }}" type="C128B" class="barcode" />
                </div>
                <div>
                    <b>{{ 'par-'.$return->slug }}</b>
                </div>
            </td>

        </tr>
    </table>
</htmlpageheader>

<sethtmlpageheader name="myheader" value="on" show-this-page="1"/>
<sethtmlpagefooter name="myfooter" value="on"/>

<br><br><br><br><br>

<table width="100%" style="margin-top: 40px">
    <tr>
        <td>
            <h1>
                Pallet Return
            </h1>
        </td>
        <td style="text-align: right">
            @if($return->state == \App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum::DISPATCHED)
                <div>
                    Dispatched Date: <b>{{ $return->dispatched_at->format('j F Y') }}</b>
                </div>
            @endif
        </td>
    </tr>
</table>
<table width="100%" style="font-family: sans-serif; margin-top: 20px" cellpadding="0">
    <tr>
        <td width="50%" style="vertical-align:bottom;border: 0mm solid #888888;">
            <div>
                <div>
                    Customer: <b>{{ $customer->name }}</b>
                    ({{ $customer->reference }})
                </div>
                @if($customer->phone)
                    <div>
                        <span class="address_label">Phone:</span> <span class="address_value">{{ $customer->phone }}</span>
                    </div>
                @endif
            </div>
        </td>
        <td width="50%" style="vertical-align:bottom;border: 0mm solid #888888;text-align: right">
            <div style="text-align:right;">
                State: <b>{{ $return->state->labels()[$return->state->value] }}</b>
            </div>
        </td>
    </tr>
</table>
<table width="100%" style="font-family: sans-serif;" cellpadding="10">
    <tr>
        <td width="45%" style="border: 0.1mm solid #888888;">
            <span style="font-size: 7pt; color: #555555; font-family: sans-serif;">Delivery address:</span>
            <div>
                {{ $return->deliveryAddress?->address_line_1 }}
            </div>
            <div>
                {{ $return->deliveryAddress?->address_line }}
            </div>
            <div>
                {{ $return->deliveryAddress?->locality }}
            </div>
            <div>
                {{ $return->deliveryAddress?->country->name }}
            </div>
        </td>
        <td width="10%">&nbsp;</td>
        <td width="45%">&nbsp;</td>
    </tr>
</table>
<br>


<p>Pallets</p>
<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
    <tr>
        <td style="width:20%; text-align:left">Reference</td>
        <td style="width:50%; text-align:left">Pallet Reference (Customer's)</td>
        <td style="text-align:right">Notes</td>
    </tr>
    </thead>
    <tbody>

    @foreach($return->pallets as $pallet)
        <tr class="@if($loop->last) last @endif">
            <td style="text-align:left">{{ $pallet->reference }}</td>
            <td style="text-align:left">{{ $pallet->customer_reference }}</td>
            <td style="text-align:left">{{ $pallet->notes }}</td>
        </tr>
    @endforeach

    </tbody>

</table>
<br>
<br>

<p>Services</p>
<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
    <tr>
        <td style="width:20%; text-align:left">Code</td>
        <td style="width:50%; text-align:left">Service Name</td>
        <td style="text-align:right">Price</td>
    </tr>
    </thead>
    <tbody>

    @foreach($return->services as $service)
        <tr class="@if($loop->last) last @endif">
            <td style="text-align:left">{{ $service->code }}</td>
            <td style="text-align:left">{{ $service->name }}</td>
            <td style="text-align:left">{{ $service->currency?->symbol . $service->price }}</td>
        </tr>
    @endforeach

    </tbody>

</table>
<br>
<br>

<p>Physical Goods</p>
<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
    <tr>
        <td style="width:20%; text-align:left">Code</td>
        <td style="width:50%; text-align:left">Physical Good Name</td>
        <td style="text-align:right">Price</td>
    </tr>
    </thead>
    <tbody>

    @foreach($return->physicalGoods as $physicalGood)
        <tr class="@if($loop->last) last @endif">
            <td style="text-align:left">{{ $physicalGood->code }}</td>
            <td style="text-align:left">{{ $physicalGood->name }}</td>
            <td style="text-align:left">{{ $physicalGood->currency?->symbol . $physicalGood->price }}</td>
        </tr>
    @endforeach

    </tbody>

</table>

<br>
<br>
<br>

<htmlpagefooter name="myfooter">
    <div
        style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; margin-top: 120px"></div>
    <table width="100%">
        <tr>
        <tr>
            <td width="33%" style="color:#000;text-align: left;">
                <small>{{ $shop->name }}<br> VAT Number:
                    <b>{{ $shop->identity_document_number }}</b>
                    <br>
                    Registration Number: {{ $shop->identity_document_number }}</small>
            </td>
            <td width="33%" style="color:#000;text-align: center">
                Page 1 of 1
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
