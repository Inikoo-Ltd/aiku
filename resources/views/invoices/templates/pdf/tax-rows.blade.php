@php($taxBreakdown = $document->printedTaxBreakdown($taxBreakdownOverride ?? null))

@foreach($taxBreakdown as $tax)
    <tr>
        <td style="border:none" colspan="{{ $fillerColspan ?? 4 }}"></td>
        <td class="totals" colspan="{{ $labelColspan ?? 1 }}">
            {{ __('Tax') }}
            <br><small>{{ $tax['name'] }}
                ({{__('rate')}}:{{percentage($tax['rate'],1)}})
                @if(count($taxBreakdown) > 1 && $tax['net_amount'] != 0)
                    {{ __('on') }} {{ $document->currency->symbol . number_format($tax['net_amount'], 2, '.', '') }}
                @endif
            </small>
        </td>
        <td class="totals">{{ $document->currency->symbol . number_format($tax['tax_amount'], 2, '.', '') }}</td>
    </tr>
@endforeach
