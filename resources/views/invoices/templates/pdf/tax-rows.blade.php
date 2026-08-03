@php($taxBreakdown = $document->taxBreakdown())

@forelse($taxBreakdown as $tax)
    <tr>
        <td style="border:none" colspan="4"></td>
        <td class="totals">
            {{ __('Tax') }}
            <br><small>{{ $tax['name'] }}
                ({{__('rate')}}:{{percentage($tax['rate'],1)}})
                @if(count($taxBreakdown) > 1)
                    {{ __('on') }} {{ $document->currency->symbol . $tax['net_amount'] }}
                @endif
            </small>
        </td>
        <td class="totals">{{ $document->currency->symbol . $tax['tax_amount'] }}</td>
    </tr>
@empty
    <tr>
        <td style="border:none" colspan="4"></td>
        <td class="totals">
            {{ __('Tax') }}
            <br><small>{{$document->taxCategory->name}}
                ({{__('rate')}}:{{percentage($document->taxCategory->rate,1)}})
            </small>
        </td>
        <td class="totals">{{ $document->currency->symbol . $document->tax_amount }}</td>
    </tr>
@endforelse
