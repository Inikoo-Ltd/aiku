/*
 * author Louis Perez
 * created on 07-05-2026-15h-09m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stored Items (SKU) List - {{ $customer->company_name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 8px; border: 0.1mm solid #000; }
        .header, .footer { font-size: 9pt; text-align: center; }
        .address { font-size: 9pt; color: #555; }
        .items td { border: 0.1mm solid #000; }
        .items thead td { background-color: #EEEEEE; text-align: center; }
        .totals td { text-align: right; border: 0.1mm solid #000; }
        .total-row td { font-weight: bold; text-align: right; border-top: 0.3mm solid #000; }
    </style>
</head>
<body>

<!-- Header Section -->
<div class="header">
    <h1> Stored Items (SKU) List </h1>
</div>

<!-- Company and Customer Details -->
<table>
    <tr>
        <td style="width: 50%;">
            Company: {{ $customer->company_name  }}
            <br>
            Phone: {{ $customer->phone ?? '-' }} 
            <br>
            Email: {{ $customer->email ?? '-' }}
        </td>
    </tr>
</table>
<!-- Delivery Details -->
<table>
    <tr>
        <td style='width: 50%'>
            <strong>Issuer:</strong> {{ $user->contact_name }}
        </td>
        <td style='width: 50%; text-align:right'>
            <strong>Issued Date:</strong> {{ now()->format('Y-m-d') }}
        </td>
    </tr>
</table>

<!-- Items Section -->
<table class="items">
    <thead>
        <tr>
            <td style='width:max-content'>
                {{ __("Reference") }}
            </td>
            <td>
                {{ __("Name") }}
            </td>
            <td>
                {{ __("Pallet Locations") }}
            </td>
            <td style='width:150px'>
                {{ __("Current Quantity") }}
            </td>
            <td style='width:max-content'>
                {{ __("Status") }}
            </td>
        </tr>
    </thead>
    <tbody>
        @foreach ($stored_items as $item)
            <tr>
                <td style='width:max-content'>
                    {{ $item->reference }}
                </td>
                <td>
                    {{ $item->name ?? '-'}}
                </td>
                <td style='text-align: right;'>
                    <div style='column-gap: 20px; display: flex'>
                        @forelse($item->pallets as $pallet)
                            <div style='border: 1px solid black; margin-bottom: 2rem;'>
                                &nbsp; {{ $pallet->reference }} | {{ $pallet->pivot->quantity }} Qty &nbsp;
                            </div>
                            @unless($loop->last)
                                &nbsp;
                            @endunless
                        @empty
                            -
                        @endforelse
                    </div>
                </td>
                <td style='width:150px; text-align: right'>
                    {{ (float) $item->total_quantity }}
                </td>
                <td style='width:100px; text-align: right'>
                    {{ $item->state->labelGenerated() }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- Footer Section -->
<div class="footer"> <p>Thank you for your hard work and attention to detail!</p> 
<p>If you have any questions or need further information about your stored items, please don't hesitate to contact us at {{$shop->phone}} or  <a href="mailto:{{$shop->email}}">{{$shop->email}}</a>.</p> </div>

</body>
</html>
