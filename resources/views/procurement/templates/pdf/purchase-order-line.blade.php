@php($product = $line->supplierProduct)
@php($unitsPerCarton = (float)($product?->units_per_carton ?: 0))
@php($lineCurrency = $product?->currency?->code ?? $purchaseOrder->currency->code)
<tr>
    <td>{{ $index + 1 }}</td>
    @if($images !== null)
        <td class="image">
            @if(isset($images[$line->id]))
                <img src="{{ $images[$line->id] }}" style="max-width: 48px; max-height: 48px;" alt="" />
            @endif
        </td>
    @endif
    <td>
        {{ $product?->code ?? $line->orgStock?->code }}
        @if($product && $line->orgStock && $line->orgStock->code !== $product->code)
            <br><span class="muted">{{ $line->orgStock->code }}</span>
        @endif
    </td>
    <td>{{ $product?->name ?? $line->orgStock?->name }}</td>
    <td class="right">{{ $unitsPerCarton ? $quantity($unitsPerCarton) : '' }}</td>
    <td class="right">{{ $unitsPerCarton ? $quantity((float)$line->quantity_ordered / $unitsPerCarton) : '' }}</td>
    @if($product?->supplier_unit)
        <td class="right">{{ $quantity((float)$line->quantity_ordered / $product->unitsPerSupplierUnit()) }} {{ $product->supplier_unit->value }}</td>
        <td class="right">{{ $price(($line->unit_cost ?? $product->cost) * $product->unitsPerSupplierUnit()) }} / {{ $product->supplier_unit->value }}</td>
    @else
        <td class="right">{{ $quantity($line->quantity_ordered) }}</td>
        <td class="right">{{ $price($line->unit_cost ?? $product?->cost) }}</td>
    @endif
    <td class="right">{{ number_format((float)$line->net_amount, 2) }}{{ $singleCurrency ? '' : ' '.$lineCurrency }}</td>
</tr>
