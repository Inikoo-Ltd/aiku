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
                {{$organisation->name}}
                <div style="font-size:7pt">
                    {{$organisation->name}}
                </div>
                <div style="font-size:7pt">
                    {{$organisation->address->address_line_1}}
                </div>
                <div style="font-size:7pt">
                    {{$organisation->address->address_line_2}}
                </div>
                <div style="font-size:7pt">
                    {{$organisation->address->locality}} {{$organisation->address->postal_code}}
                </div>
                <div style="font-size:7pt">
                    www.{{$organisation->email}}
                </div>
            </td>
        </tr>
    </table>
</htmlpageheader>

<sethtmlpageheader name="myheader" value="on" show-this-page="1"/>
<sethtmlpagefooter name="myfooter" value="on"/>

@foreach($employees as $employee)
<table width="100%" style="font-family: sans-serif; margin-top: 20px" cellpadding="0">
    <tr>
        <td width="50%" style="vertical-align:bottom;border: 0mm solid #888888;">
            <div>
                <div>
                    {{ __('Employee') }}</b>
                    ({{ $employee->contact_name }})
                </div>
            </div>
        </td>
    </tr>
</table>
<br>
<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse;" cellpadding="8">
    <thead>
        <tr>
            <td style="width:14%;text-align:left">{{ __('Date') }}</td>
            <td style="text-align:left">{{ __('Start At') }}</td>
            <td style="text-align:left">{{ __('End At') }}</td>
            <td style="text-align:left;width:20% ">{{ __('Break Duration') }}</td>
        </tr>
    </thead>
    <tbody>

    @foreach($employee->timesheets as $timesheet)
        <tr class="@if($loop->last) last @endif">
            <td style="text-align:left">{{ $timesheet->date?->format('Y-m-d') }}</td>
            <td style="text-align:left">{{ $timesheet->start_at?->format('Y-m-d H:i') }}</td>
            <td style="text-align:right">{{ $timesheet->end_at?->format('Y-m-d H:i') }}</td>
            <td style="text-align:right">{{ Carbon\CarbonInterval::seconds($timesheet->breaks_duration)->cascade()->forHumans() }}</td>
        </tr>
    @endforeach

    </tbody>
</table>
<br>
@endforeach
<htmlpagefooter name="myfooter">
    <div>

    </div>
</htmlpagefooter>
</body>
</html>
