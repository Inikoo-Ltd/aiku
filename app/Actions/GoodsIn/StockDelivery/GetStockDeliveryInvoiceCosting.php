<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What an invoice reading would do to the delivery's costing, line by line, and every reason it cannot: a
 * reading with any reason is entered by hand, its amounts are never corrected here. Invoice lines are matched to
 * delivery lines by supplier product code (a code on two delivery lines matches neither); the reviewer only says
 * where a line that matched nothing goes, to a line of the delivery or to "invoiced but not in the delivery".
 * The goods cost of a line is the invoice's unit price times what was received, and a line whose cost lands far
 * from what was ordered refuses the reading, which is where invoices counted in packs or cartons show up.
 */
class GetStockDeliveryInvoiceCosting
{
    use AsObject;

    private const float TOLERANCE = 0.01;

    private const float CHECK_SHARE = 0.2;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(StockDelivery $stockDelivery): array
    {
        $invoices = $stockDelivery->attachments()
            ->wherePivotIn('scope', [PurchaseOrderAttachmentScopeEnum::INVOICE->value, PurchaseOrderAttachmentScopeEnum::PROFORMA->value])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return $invoices->map(fn (Media $media) => [
            'media_id'   => $media->id,
            'ulid'       => $media->ulid,
            'name'       => $media->file_name,
            'scope'      => $media->pivot->scope,
            'readRoute'  => [
                'name'       => 'grp.models.stock-delivery.invoice.read',
                'parameters' => ['stockDelivery' => $stockDelivery->id, 'media' => $media->id],
                'method'     => 'post',
            ],
            'applyRoute' => [
                'name'       => 'grp.models.stock-delivery.invoice.apply',
                'parameters' => ['stockDelivery' => $stockDelivery->id, 'media' => $media->id],
                'method'     => 'post',
            ],
            'reading'    => $this->review($stockDelivery, ReadStockDeliveryInvoice::reading($stockDelivery, $media), $media),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $reading
     * @param  array<int, int|null>  $assignments  invoice line index => delivery item id, or null for not in the delivery
     * @return array<string, mixed>|null
     */
    public function review(StockDelivery $stockDelivery, ?array $reading, ?Media $media = null, array $assignments = []): ?array
    {
        if (Arr::get($reading, 'state') !== 'read') {
            return $reading ? Arr::only($reading, ['state', 'error']) : null;
        }

        if (Arr::get($reading, 'kind') !== ReadStockDeliveryInvoice::KIND) {
            return [
                ...Arr::only($reading, ['state', 'read_at', 'applied_at']),
                'problems'  => Arr::get($reading, 'applied_at') ? [] : [__('This invoice was read before readings were checked: read it again.')],
                'can_apply' => false,
            ];
        }

        $checks  = $reading['checks'];
        $applied = (bool) Arr::get($reading, 'applied_at');
        $items   = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with(['supplierProduct:id,code,name,supplier_unit,units_per_supplier_unit', 'orgStock:id,code,name,barcode,unit_barcode,stock_id', 'orgStock.stock:id,carton_barcode'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $matchers = [
            'supplier_code' => self::uniqueBy($items, fn (StockDeliveryItem $item) => [self::normaliseCode($item->supplierProduct?->code)]),
            'sko_code'      => self::uniqueBy($items, fn (StockDeliveryItem $item) => [self::normaliseCode($item->orgStock?->code)]),
            'barcode'       => self::uniqueBy($items, fn (StockDeliveryItem $item) => array_map(
                fn (?string $barcode) => ReadStockDeliveryInvoice::barcode($barcode),
                [$item->orgStock?->unit_barcode, $item->orgStock?->barcode, $item->orgStock?->stock?->carton_barcode]
            )),
        ];

        $codeOwners = [];
        foreach ($items as $item) {
            foreach (array_filter([self::normaliseCode($item->supplierProduct?->code), self::normaliseCode($item->orgStock?->code)]) as $code) {
                $codeOwners[$code][$item->id] = true;
            }
        }

        $invoiced      = [];
        $unmatched     = [];
        $notInDelivery = [];

        foreach ($reading['lines'] as $index => $line) {
            [$item, $matchedBy] = self::match($matchers, $line, $codeOwners);

            if (!$item && array_key_exists($index, $assignments)) {
                if ($assignments[$index] === null) {
                    $notInDelivery[] = ['index' => $index, 'label' => mb_substr(trim(($line['code'] ?? '').' '.($line['description'] ?? '')) ?: __('Invoice line'), 0, 255), 'amount' => $line['amount']];
                    continue;
                }
                $item      = $items->get($assignments[$index]);
                $matchedBy = 'reviewer';
            }

            if (!$item) {
                $unmatched[] = ['index' => $index, ...$line, 'suggested_item_id' => self::suggestByDescription($items, $line['description'] ?? null)];
                continue;
            }

            $invoiced[$item->id]['amount']       = ($invoiced[$item->id]['amount'] ?? 0) + (float) $line['amount'];
            $invoiced[$item->id]['quantity']     = ($invoiced[$item->id]['quantity'] ?? 0) + (float) ($line['quantity'] ?? 0) * ($item->supplierProduct?->unitsPerSupplierUnit() ?? 1);
            $invoiced[$item->id]['matched_by'][] = $matchedBy;
        }

        $deliveryCurrency = $stockDelivery->currency?->code;
        $currency         = $reading['currency'] ? Currency::whereRaw('upper(code) = ?', [strtoupper($reading['currency'])])->first() : null;
        $mismatch         = $currency && $deliveryCurrency && $currency->code !== $deliveryCurrency;
        $exchange         = $mismatch ? GetCurrencyExchange::run($currency, $stockDelivery->currency) : null;
        $linesTotal       = round(array_sum(array_map(fn (array $line) => (float) $line['amount'], $reading['lines'])), 2);
        $chargesTotal     = round(array_sum(array_column($reading['charges'], 'amount')), 2);
        $total            = $reading['total'];
        $money            = fn (?float $amount) => number_format((float) $amount, 2).' '.($currency?->code ?? '');

        $reviewItems = $items->map(function (StockDeliveryItem $item) use ($invoiced, $mismatch, $exchange, $stockDelivery) {
            $quantity = self::receivedQuantity($item, $stockDelivery->state);
            $invoice  = $invoiced[$item->id] ?? null;
            $expected = (float) $item->net_amount;
            $expectedInInvoiceCurrency = $mismatch ? ($exchange ? $expected / $exchange : null) : $expected;
            $proposed = match (true) {
                !$invoice                => null,
                $invoice['quantity'] > 0 => round($invoice['amount'] / $invoice['quantity'] * $quantity, 2),
                default                  => round($invoice['amount'], 2),
            };
            $ordered   = (float) $item->unit_quantity;
            $orderUnit = $expectedInInvoiceCurrency !== null && $ordered > 0 ? $expectedInInvoiceCurrency / $ordered : null;
            $farFrom   = fn (float $value, float $reference) => abs($value - $reference) > max($reference * self::CHECK_SHARE, self::TOLERANCE);
            $needsCheck = match (true) {
                !$invoice || $orderUnit === null => false,
                $invoice['quantity'] > 0         => $farFrom($invoice['amount'] / $invoice['quantity'], $orderUnit),
                default                          => $farFrom($invoice['amount'], $expectedInInvoiceCurrency),
            };

            return [
                'id'               => $item->id,
                'code'             => $item->supplierProduct?->code,
                'name'             => $item->supplierProduct?->name,
                'quantity'         => $quantity,
                'ordered_quantity' => (float) $item->unit_quantity,
                'expected_amount'  => $expected,
                'current_cost'     => (float) ($item->cost_items ?? $item->net_amount),
                'invoice_quantity' => $invoice['quantity'] ?? null,
                'matched_by'       => $invoice ? array_values(array_unique($invoice['matched_by'])) : [],
                'invoice_amount'   => $invoice ? round($invoice['amount'], 2) : null,
                'quantity_differs' => $invoice && abs($invoice['quantity'] - $quantity) > self::TOLERANCE,
                'amount_differs'   => $invoice && $expectedInInvoiceCurrency !== null && abs($invoice['amount'] - $expectedInInvoiceCurrency) > self::TOLERANCE,
                'proposed_cost'    => $proposed,
                'needs_check'      => $needsCheck,
            ];
        })->values();

        $codes        = fn ($rows) => implode(', ', array_slice($rows->pluck('code')->map(fn ($code) => $code ?? '—')->all(), 0, 10));
        $toCheck      = $reviewItems->where('needs_check', true);
        $notInvoiced  = $reviewItems->filter(fn (array $row) => $row['invoice_amount'] === null && $row['quantity'] > 0);
        $recorded     = $stockDelivery->agent_id ? $stockDelivery->agentInvoice()->first() : $stockDelivery->supplierInvoice()->first();
        $enteredOn    = $applied ? null : $this->deliveryWithSameInvoice($stockDelivery, $reading['invoice_number']);
        $typed        = $applied ? null : $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->whereNotNull('received_at')->with('currency')->first();

        $problems = array_keys(array_filter([
            __('This does not look like an invoice for goods.') => !$reading['is_invoice'],
            __('The two readers do not agree on: :fields.', ['fields' => implode(', ', array_map(fn (string $field) => GetStockDeliveryServiceInvoiceReadings::fieldLabels()[$field], $checks['disagreements']))]) => (bool) $checks['disagreements'],
            __('These amounts are not printed in the document: :amounts.', ['amounts' => implode(', ', array_map(fn ($amount) => number_format((float) $amount, 2), $checks['amounts_missing']))]) => (bool) $checks['amounts_missing'],
            __('The document also mentions :references: enter it by hand to split it between the deliveries.', ['references' => implode(', ', $checks['other_deliveries'])]) => (bool) $checks['other_deliveries'],
            __('The invoice has no lines.') => !$reading['lines'],
            __('A line has no amount, or is a credit: enter it by hand.') => collect($reading['lines'])->contains(fn (array $line) => $line['amount'] === null || $line['amount'] < 0),
            __('The invoice has a discount or credit: enter it by hand.') => collect($reading['charges'])->contains(fn (array $charge) => $charge['amount'] < 0),
            __('The document shows no total to check the lines against.') => $total === null,
            __('The lines and charges add up to :sum, the invoice total says :total.', ['sum' => $money($linesTotal + $chargesTotal), 'total' => $money($total)]) => $total !== null && abs($linesTotal + $chargesTotal - $total) > self::TOLERANCE,
            __('The document does not show a currency we know.') => !$currency,
            __('The currency :currency is not printed in the document.', ['currency' => (string) $currency?->code]) => $currency && Arr::get($checks, 'currency_printed') === false,
            __('The document shows no invoice date.') => !GetStockDeliveryServiceInvoiceReadings::isDate($reading['invoice_date']),
            __('The document shows no invoice number, so it cannot be checked against the invoices already entered.') => !$reading['invoice_number'],
            __('The invoice number :reference is not printed in the document.', ['reference' => (string) $reading['invoice_number']]) => Arr::get($checks, 'number_printed') === false,
            __('The invoice :reference is already on the delivery :delivery.', ['reference' => (string) $reading['invoice_number'], 'delivery' => (string) $enteredOn]) => (bool) $enteredOn,
            __('The invoice of this delivery is already entered (:reference): change it with Invoice.', ['reference' => (string) $recorded?->reference]) => !$applied && $recorded && $recorded->source !== StockDeliveryInvoiceSourceEnum::ESTIMATED,
            __('Another invoice read for this delivery is already in its costing.') => !$applied && $media && $this->otherReadingApplied($stockDelivery, $media),
            __('The invoice price of :codes is far from the order price, the invoice may count packs or cartons: enter it by hand.', ['codes' => $codes($toCheck)]) => $toCheck->isNotEmpty(),
            __('These products of the delivery are not on the invoice: :codes. Enter it by hand.', ['codes' => $codes($notInvoiced)]) => $notInvoiced->isNotEmpty() && !$unmatched,
            __('Check what was received before applying the invoice: the costs follow the quantities received.') => !$applied && in_array($stockDelivery->state, [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP, StockDeliveryStateEnum::DISPATCHED, StockDeliveryStateEnum::RECEIVED], true),
            __('This delivery is costed, an accounting manager can change it with Update costing.') => !$applied && (bool) $stockDelivery->is_costed,
            __('This is a partner delivery, which costs itself from the partner order.') => $stockDelivery->parent_type === 'OrgPartner',
            __('This delivery is cancelled or was not received.') => in_array($stockDelivery->state, [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED], true),
        ]));

        return [
            ...Arr::only($reading, ['state', 'read_at', 'applied_at', 'is_invoice', 'issuer', 'invoice_number', 'invoice_date', 'charges', 'total', 'readers']),
            'currency'              => $currency?->code ?? $reading['currency'],
            'delivery_currency'     => $deliveryCurrency,
            'currency_mismatch'     => $mismatch,
            'exchange'              => $exchange,
            'follow_blockers'       => $mismatch ? ChangeStockDeliveryCurrency::blockers($stockDelivery) : [],
            'lines_total'           => $linesTotal,
            'charges_total'         => $chargesTotal,
            'unmatched'             => $unmatched,
            'not_in_delivery'       => $notInDelivery,
            'items'                 => $reviewItems->all(),
            'charge_types'          => [['label' => __('Freight, goes to the shipping'), 'value' => ApplyStockDeliveryInvoiceCosting::FREIGHT], ...StockDeliveryServiceInvoiceTypeEnum::extraOptions()],
            'shipping_typed_by_hand' => (bool) $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->where('from_service_invoices', false)->where('is_na', false)->whereNotNull('amount')->exists(),
            'not_received'          => $this->quantityGap($reviewItems->filter(fn (array $row) => $row['proposed_cost'] !== null && $row['invoice_amount'] - $row['proposed_cost'] > self::TOLERANCE), fn (array $row) => $row['invoice_amount'] - $row['proposed_cost']),
            'not_invoiced'          => $this->quantityGap($reviewItems->filter(fn (array $row) => $row['proposed_cost'] !== null && $row['proposed_cost'] - $row['invoice_amount'] > self::TOLERANCE), fn (array $row) => $row['proposed_cost'] - $row['invoice_amount']),
            'replaces_typed'        => $typed && $typed->amount !== null && (StockDeliveryDocumentChecks::normalise($typed->currency?->code ?? $deliveryCurrency) !== StockDeliveryDocumentChecks::normalise($currency?->code) || abs((float) $typed->amount - (float) $total) > self::TOLERANCE)
                ? ['amount' => (float) $typed->amount, 'currency' => $typed->currency?->code ?? $deliveryCurrency]
                : null,
            'problems'              => $applied ? [] : $problems,
            'needs_manual_check'    => !$checks['text_available'] || $checks['mentions_delivery'] === null,
            'delivery_confirmation' => $checks['mentions_delivery'] === false && !$applied ? GetStockDeliveryServiceInvoiceReadings::deliveryConfirmation($stockDelivery, $reading) : null,
            'can_apply'             => !$problems && !$applied,
        ];
    }

    /**
     * The reading the delivery page explains against the order: always the invoice aiku holds. Once an invoice is
     * entered, the read file whose total is that invoice (an applied reading too: the invoice may have been changed
     * since), else every read invoice file together when they add up to it (an invoice split in two files); a
     * pro-forma, or a file that adds up to something else, is never explained. Before an invoice is entered, the
     * read invoice file when there is only one.
     *
     * @param  array<int, array<string, mixed>>  $invoices  as handle() returns them
     * @return array{total: float, currency: string, currency_mismatch: bool, charges_total: float, items: array<int, array<string, mixed>>, unmatched: array<int, array<string, mixed>>, not_in_delivery: array<int, array<string, mixed>>}|null
     */
    public function explained(StockDelivery $stockDelivery, array $invoices): ?array
    {
        $readings = collect($invoices)
            ->filter(fn (array $invoice) => $invoice['scope'] === PurchaseOrderAttachmentScopeEnum::INVOICE->value)
            ->pluck('reading')
            ->filter(fn (?array $reading) => Arr::get($reading, 'state') === 'read'
                && isset($reading['items'])
                && Arr::get($reading, 'is_invoice')
                && !$reading['currency_mismatch']
                && $reading['total'] !== null)
            ->values();

        $recorded = ($stockDelivery->agent_id ? $stockDelivery->agentInvoice() : $stockDelivery->supplierInvoice())->with('currency')->first();

        if (!$recorded || $recorded->source === StockDeliveryInvoiceSourceEnum::ESTIMATED) {
            return $readings->count() === 1 ? self::explanationOf($readings) : null;
        }

        $readings = $readings->filter(fn (array $reading) => $reading['currency'] === $recorded->currency->code)->values();
        $isRecorded = fn (float $total) => abs($total - (float) $recorded->total_amount) <= self::TOLERANCE;

        if ($same = $readings->first(fn (array $reading) => $isRecorded((float) $reading['total']))) {
            return self::explanationOf(collect([$same]));
        }

        $numbers = $readings->map(fn (array $reading) => StockDeliveryDocumentChecks::normalise($reading['invoice_number']));

        return $readings->count() > 1 && $numbers->unique()->count() === $readings->count() && $isRecorded($readings->sum('total'))
            ? self::explanationOf($readings)
            : null;
    }

    /**
     * One or more reviewed readings as a single invoice: what each delivery line was invoiced across them.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $readings
     * @return array<string, mixed>
     */
    private static function explanationOf(\Illuminate\Support\Collection $readings): array
    {
        $invoicedLines = fn (int $itemId) => $readings
            ->map(fn (array $reading) => collect($reading['items'])->firstWhere('id', $itemId))
            ->filter(fn (?array $line) => $line && $line['invoice_amount'] !== null);

        return [
            'total'             => round($readings->sum('total'), 2),
            'currency'          => $readings->first()['currency'],
            'currency_mismatch' => false,
            'charges_total'     => round($readings->sum('charges_total'), 2),
            'items'             => collect($readings->first()['items'])->map(function (array $item) use ($invoicedLines) {
                $lines = $invoicedLines($item['id']);

                return [
                    ...Arr::only($item, ['id', 'code', 'name', 'quantity', 'ordered_quantity', 'expected_amount']),
                    'invoice_quantity' => $lines->isEmpty() ? null : $lines->sum('invoice_quantity'),
                    'invoice_amount'   => $lines->isEmpty() ? null : round($lines->sum('invoice_amount'), 2),
                ];
            })->all(),
            'unmatched'         => $readings->flatMap(fn (array $reading) => $reading['unmatched'])->values()->all(),
            'not_in_delivery'   => $readings->flatMap(fn (array $reading) => $reading['not_in_delivery'])->values()->all(),
        ];
    }

    /**
     * Lines where what was received differs from what was invoiced, and the money that difference is worth at the
     * invoice price: paid for and not received is for a supplier claim, received and not invoiced is costed anyway.
     *
     * @return array{amount: float, codes: array<int, string|null>}|null
     */
    private function quantityGap(\Illuminate\Support\Collection $rows, callable $amount): ?array
    {
        return $rows->isEmpty() ? null : [
            'amount' => round($rows->sum($amount), 2),
            'codes'  => $rows->pluck('code')->values()->all(),
        ];
    }

    /**
     * What was received of a line: what was put away, else what was counted once the delivery is checked (a line
     * counted as none is none), else what was expected.
     */
    public static function receivedQuantity(StockDeliveryItem $item, StockDeliveryStateEnum $state): float
    {
        return match (true) {
            (float) $item->unit_quantity_placed > 0 => (float) $item->unit_quantity_placed,
            in_array($state, [StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN, StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED], true) => (float) $item->unit_quantity_checked,
            default                                 => (float) $item->unit_quantity,
        };
    }

    /**
     * Another delivery of the same supplier or agent that carries this invoice number.
     */
    private function deliveryWithSameInvoice(StockDelivery $stockDelivery, ?string $invoiceNumber): ?string
    {
        $number = StockDeliveryDocumentChecks::normalise($invoiceNumber);

        return $number === '' ? null : StockDelivery::where('organisation_id', $stockDelivery->organisation_id)
            ->where('parent_type', $stockDelivery->parent_type)
            ->where('parent_id', $stockDelivery->parent_id)
            ->where('id', '!=', $stockDelivery->id)
            ->whereNotIn('state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
            ->whereRaw("lower(regexp_replace(data->>'invoice_number', '[^A-Za-z0-9]', '', 'g')) = ?", [$number])
            ->value('reference');
    }

    private function otherReadingApplied(StockDelivery $stockDelivery, Media $media): bool
    {
        return DB::table('model_has_attachments')
            ->where('model_type', 'StockDelivery')
            ->where('model_id', $stockDelivery->id)
            ->where('media_id', '!=', $media->id)
            ->whereRaw("data->'invoice_reading'->>'kind' is distinct from ?", [ReadStockDeliveryServiceInvoice::KIND])
            ->whereRaw("data->'invoice_reading'->>'applied_at' is not null")
            ->exists();
    }

    /**
     * The invoice line's product, by our supplier product code, then our SKO code, then its barcode (the unit, SKO
     * or carton barcode): the first that names exactly one line of the delivery. A code that is the supplier or SKO
     * code of two lines matches by neither, so it can not land on whichever line happens to own it the other way.
     *
     * @param  array<string, \Illuminate\Support\Collection<string, StockDeliveryItem>>  $matchers
     * @param  array<string, mixed>  $line
     * @param  array<string, array<int, true>>  $codeOwners
     * @return array{0: ?StockDeliveryItem, 1: ?string}
     */
    private static function match(array $matchers, array $line, array $codeOwners): array
    {
        $code = self::normaliseCode($line['code'] ?? null);
        $code = count($codeOwners[$code] ?? []) === 1 ? $code : '';
        $keys = [
            'supplier_code' => $code,
            'sko_code'      => $code,
            'barcode'       => ReadStockDeliveryInvoice::barcode($line['barcode'] ?? null),
        ];

        foreach ($matchers as $by => $items) {
            if ($keys[$by] && $item = $items->get($keys[$by])) {
                return [$item, $by];
            }
        }

        return [null, null];
    }

    /**
     * Each key that names exactly one line of the delivery: a code or barcode on two lines names neither.
     *
     * @param  \Illuminate\Support\Collection<int, StockDeliveryItem>  $items
     * @return \Illuminate\Support\Collection<string, StockDeliveryItem>
     */
    private static function uniqueBy(\Illuminate\Support\Collection $items, callable $keys): \Illuminate\Support\Collection
    {
        $byKey = [];
        foreach ($items as $item) {
            foreach (array_unique(array_filter($keys($item))) as $key) {
                $byKey[$key][$item->id] = $item;
            }
        }

        return collect($byKey)->filter(fn (array $sameKey) => count($sameKey) === 1)->map(fn (array $sameKey) => reset($sameKey));
    }

    /**
     * The one line of the delivery whose product name the invoice line's description holds, or that holds it. Only
     * ever a suggestion: the reviewer still says where the line goes.
     *
     * @param  \Illuminate\Support\Collection<int, StockDeliveryItem>  $items
     */
    private static function suggestByDescription(\Illuminate\Support\Collection $items, ?string $description): ?int
    {
        $words       = fn (?string $text) => trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $text)));
        $description = $words($description);

        if (mb_strlen($description) < 8) {
            return null;
        }

        $candidates = $items->filter(function (StockDeliveryItem $item) use ($words, $description) {
            foreach ([$item->supplierProduct?->name, $item->orgStock?->name] as $name) {
                $name = $words($name);
                if (mb_strlen($name) >= 8 && (str_contains($description, $name) || str_contains($name, $description))) {
                    return true;
                }
            }

            return false;
        });

        return $candidates->count() === 1 ? $candidates->first()->id : null;
    }

    public static function normaliseCode(?string $code): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }
}
