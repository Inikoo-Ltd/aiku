@php($singleCurrency = $totals->count() <= 1 ? ($totals->keys()->first() ?? $purchaseOrder->currency->code) : null)
@php($price = fn ($value) => number_format((float)$value, max(2, strlen(rtrim(substr(strrchr(number_format((float)$value, 4, '.', ''), '.'), 1), '0')))))
@php($quantity = fn ($value) => rtrim(rtrim(number_format((float)$value, 3), '0'), '.'))
<htmlpagefooter name="footer{{ $purchaseOrder->id }}">
    <div style="text-align: right; font-size: 8pt; color: #777;">{{ $purchaseOrder->reference }} · Page {PAGENO} of {nbpg}</div>
</htmlpagefooter>
<sethtmlpagefooter name="footer{{ $purchaseOrder->id }}" value="on" />

<table class="header">
    <tr>
        <td>
            <h1>{{ $organisation->name }}</h1>
            @if($organisation->address)
                <div class="muted">{!! nl2br(e($organisation->address->formatted_address)) !!}</div>
            @endif
            <div class="muted">
                @if($organisation->phone){{ $organisation->phone }}@endif
                @if($organisation->phone && $organisation->email) · @endif
                @if($organisation->email){{ $organisation->email }}@endif
            </div>
        </td>
        <td class="right">
            <h1>Purchase Order</h1>
            <div><strong>{{ $purchaseOrder->reference }}</strong></div>
            <div class="muted">Date: {{ ($purchaseOrder->submitted_at ?? $purchaseOrder->date)?->format('j M Y') }}</div>
            <div class="muted">Currency: {{ $purchaseOrder->currency->code }}</div>
        </td>
    </tr>
</table>

<table class="blocks">
    <tr>
        <td>
            <div class="label">{{ $purchaseOrder->isAgentOrder() && !$isAgentCopy ? 'Agent' : 'Supplier' }}</div>
            <strong>{{ $counterparty?->name ?? $purchaseOrder->parent_name }}</strong><br>
            @if($purchaseOrder->isAgentOrder() && !$isAgentCopy)For supplier {{ $purchaseOrder->parent_code }} {{ $purchaseOrder->parent_name }}<br>@endif
            @if($counterparty?->contact_name && $counterparty->contact_name !== $counterparty->name){{ $counterparty->contact_name }}<br>@endif
            @if($counterparty?->address){!! nl2br(e($counterparty->address->formatted_address)) !!}<br>@endif
            @if($counterparty?->email){{ $counterparty->email }}<br>@endif
            @if($counterparty?->phone){{ $counterparty->phone }}@endif
        </td>
        <td>
            <div class="label">Deliver to</div>
            {!! nl2br(e($deliveryAddress ?? $organisation->name)) !!}
        </td>
        @php($data = $purchaseOrder->data ?? [])
        <td>
            @if(!empty($data['incoterm']) || !empty($data['port_of_export']) || !empty($data['port_of_import']) || !empty($data['payment_terms']) || $purchaseOrder->estimated_received_at)
            <div class="label">Terms</div>
            @endif
            @if(!empty($data['incoterm']))Incoterm: {{ $data['incoterm'] }}<br>@endif
            @if(!empty($data['port_of_export']))Port of export: {{ $data['port_of_export'] }}<br>@endif
            @if(!empty($data['port_of_import']))Port of import: {{ $data['port_of_import'] }}<br>@endif
            @if(!empty($data['payment_terms']))Payment: {{ $data['payment_terms'] }}<br>@endif
            @if($purchaseOrder->estimated_received_at)Expected by: {{ $purchaseOrder->estimated_received_at->format('j M Y') }}@endif
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width: 4%">#</th>
        @if($images !== null)
            <th style="width: 10%">Image</th>
        @endif
        <th style="width: 14%">Code</th>
        <th>Description</th>
        <th class="right" style="width: 9%">Units / carton</th>
        <th class="right" style="width: 9%">Cartons</th>
        <th class="right" style="width: 8%">Units</th>
        <th class="right" style="width: 11%">Unit price{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
        <th class="right" style="width: 13%">Amount{{ $singleCurrency ? ' '.$singleCurrency : '' }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $index => $line)
        @include('procurement.templates.pdf.purchase-order-line')
    @endforeach
    </tbody>
</table>

<table class="totals">
    @foreach($totals as $currencyCode => $total)
        <tr class="grand">
            <td>Total {{ $currencyCode }}</td>
            <td class="right">{{ number_format($total, 2) }} {{ $currencyCode }}</td>
        </tr>
    @endforeach
</table>

@if(($agreedTerms ?? collect())->isNotEmpty())
    <div class="terms">
        <div class="label">Agreed terms</div>
        <table class="agreed-terms">
            @foreach($agreedTerms as $agreedTerm)
                <tr>
                    <td class="agreed-term">{{ $agreedTerm->term_type->label() }}{{ $agreedTerm->product_code ? ' ('.$agreedTerm->product_code.')' : '' }}</td>
                    <td>{{ $agreedTerm->valueLabel() }}</td>
                    <td class="right">Agreed {{ $agreedTerm->agreed_on->format('d M Y') }}{{ $agreedTerm->agreed_with ? ' with '.$agreedTerm->agreed_with : '' }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

@if(($agreedNotes ?? collect())->isNotEmpty())
    <div class="terms">
        <div class="label">Notes</div>
        @foreach($agreedNotes as $agreedNote)
            <div style="margin-bottom: 4px;">
                <strong>{{ $agreedNote->title ?? '' }}</strong>
                {!! nl2br(e($agreedNote->body ?? '')) !!}
            </div>
        @endforeach
    </div>
@endif

@if(!empty($data['terms_and_conditions']))
    <div class="terms">
        <div class="label">Terms and conditions</div>
        {!! nl2br(e(trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $data['terms_and_conditions'])))))) !!}
    </div>
@endif
