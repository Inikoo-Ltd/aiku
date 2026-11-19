<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The service invoices read from documents attached to a delivery, each with the reasons its reading cannot go
 * into the costing. A reading with any reason is entered by hand instead: its amounts are never corrected here.
 */
class GetStockDeliveryServiceInvoiceReadings
{
    use AsObject;

    private const float TOLERANCE = 0.005;

    private const int DAYS_INVOICED_AFTER_ARRIVAL = 90;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(StockDelivery $stockDelivery): array
    {
        return $stockDelivery->attachments()
            ->wherePivot('scope', PurchaseOrderAttachmentScopeEnum::SERVICE_INVOICE->value)
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->map(fn (Media $media) => [
                'media_id'   => $media->id,
                'ulid'       => $media->ulid,
                'name'       => $media->pivot->caption ?: $media->file_name,
                'readRoute'  => [
                    'name'       => 'grp.models.stock-delivery.service-invoice.read',
                    'parameters' => ['stockDelivery' => $stockDelivery->id, 'media' => $media->id],
                    'method'     => 'post',
                ],
                'applyRoute' => [
                    'name'       => 'grp.models.stock-delivery.service-invoice.apply',
                    'parameters' => ['stockDelivery' => $stockDelivery->id, 'media' => $media->id],
                    'method'     => 'post',
                ],
                'reading'    => $this->review($stockDelivery, ReadStockDeliveryInvoice::reading($stockDelivery, $media)),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $reading
     * @return array<string, mixed>|null
     */
    public function review(StockDelivery $stockDelivery, ?array $reading): ?array
    {
        if (Arr::get($reading, 'state') !== 'read' || Arr::get($reading, 'kind') !== ReadStockDeliveryServiceInvoice::KIND) {
            return $reading ? Arr::only($reading, ['state', 'error']) : null;
        }

        $checks     = $reading['checks'];
        $lines      = $reading['lines'];
        $linesTotal = round(array_sum(array_column($lines, 'amount')), 2);
        $vat        = (float) ($reading['vat_total'] ?? 0);
        $net        = $reading['net_total'] ?? (isset($reading['total_payable'], $reading['vat_total']) ? round($reading['total_payable'] - $vat, 2) : null);
        $currency   = $reading['currency'] ? Currency::whereRaw('upper(code) = ?', [strtoupper($reading['currency'])])->first() : null;
        $applied    = (bool) Arr::get($reading, 'applied_at');
        $money      = fn (float $amount) => number_format($amount, 2).' '.($currency?->code ?? '');

        $problems = array_keys(array_filter([
            __('This does not look like an invoice for services.') => !$reading['is_invoice'],
            __('The two readers do not agree on: :fields.', ['fields' => implode(', ', array_map(fn (string $field) => self::fieldLabels()[$field], $checks['disagreements']))]) => (bool) $checks['disagreements'],
            __('These amounts are not printed in the document: :amounts.', ['amounts' => implode(', ', array_map(fn ($amount) => number_format((float) $amount, 2), $checks['amounts_missing']))]) => (bool) $checks['amounts_missing'],
            __('The document also mentions :references: enter it by hand to split it between the deliveries.', ['references' => implode(', ', $checks['other_deliveries'])]) => (bool) $checks['other_deliveries'],
            __('The invoice has no lines.') => !$lines,
            __('A line has no amount, or a credit: enter it by hand.') => collect($lines)->contains(fn (array $line) => $line['amount'] === null || $line['amount'] <= 0),
            __('The document shows no total before VAT to check the lines against.') => $net === null,
            __('The lines add up to :sum, the invoice says :total before VAT.', ['sum' => $money($linesTotal), 'total' => $money((float) $net)]) => $net !== null && abs($linesTotal - $net) > self::TOLERANCE,
            __('The total before VAT and the VAT do not add up to the amount to pay.') => isset($reading['net_total'], $reading['total_payable']) && abs($reading['net_total'] + $vat - $reading['total_payable']) > self::TOLERANCE,
            __('The document does not show a currency we know.') => !$currency,
            __('The currency :currency is not printed in the document.', ['currency' => (string) $currency?->code]) => $currency && Arr::get($checks, 'currency_printed') === false,
            __('The document shows no invoice date.') => !self::isDate($reading['invoice_date']),
            __('The document does not show who issued it.') => !$reading['issuer'],
            __('The document shows no invoice number, so it cannot be checked against the invoices already entered.') => !$reading['invoice_number'],
            __('The invoice number :reference is not printed in the document.', ['reference' => (string) $reading['invoice_number']]) => Arr::get($checks, 'number_printed') === false,
            __('The invoice :reference is already in the service invoices.', ['reference' => (string) $reading['invoice_number']]) => !$applied && $this->isAlreadyEntered($stockDelivery, $reading['invoice_number']),
            __('A service invoice of the same amount is already on this delivery, check it is not this one and enter it by hand.') => !$applied && $currency && $lines && $stockDelivery->serviceInvoices()->where('currency_id', $currency->id)->where('total_amount', $linesTotal)->exists(),
            __('The invoice is larger than the value of the goods of the delivery.') => $currency && $this->exceedsGoodsValue($stockDelivery, $currency, $linesTotal),
            __('This delivery is costed, an accounting manager can change it with Update costing.') => (bool) $stockDelivery->is_costed,
            __('This delivery is cancelled or was not received.') => in_array($stockDelivery->state, [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED], true),
        ]));

        return [
            ...Arr::only($reading, ['state', 'read_at', 'applied_at', 'is_invoice', 'issuer', 'invoice_number', 'invoice_date', 'readers', 'vat_total', 'total_payable']),
            'currency'           => $currency?->code ?? $reading['currency'],
            'currency_id'        => $currency?->id,
            'org_exchange'       => $currency ? GetCurrencyExchange::run($currency, $stockDelivery->organisation->currency) : null,
            'lines'              => $lines,
            'lines_total'        => $linesTotal,
            'net_total'          => $net,
            'problems'           => $applied ? [] : $problems,
            'needs_manual_check' => !$checks['text_available'] || $checks['mentions_delivery'] === null,
            'delivery_confirmation' => $checks['mentions_delivery'] === false && !$applied ? self::deliveryConfirmation($stockDelivery, $reading) : null,
            'can_apply'          => !$problems && !$applied,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'is_invoice'     => __('whether it is an invoice'),
            'invoice_number' => __('the invoice number'),
            'invoice_date'   => __('the date'),
            'currency'       => __('the currency'),
            'net_total'      => __('the total before VAT'),
            'vat_total'      => __('the VAT'),
            'total_payable'  => __('the amount to pay'),
            'total'          => __('the total'),
            'lines'          => __('the lines'),
            'charges'        => __('the charges'),
        ];
    }

    /**
     * What the reviewer is told, and must type, to add an invoice that does not print the delivery's reference:
     * forwarders write our references their own way. The invoice date fitting the delivery, from the day it left
     * to some weeks after it arrived, makes it more likely to be the right one, and says so.
     *
     * @param  array<string, mixed>  $reading
     * @return array{phrase: string, reference: string, printed: array<int, string>, dates_fit: ?bool, left_at: ?string, arrived_at: ?string}
     */
    public static function deliveryConfirmation(StockDelivery $stockDelivery, array $reading): array
    {
        $leftAt    = $stockDelivery->dispatched_at ?? $stockDelivery->created_at;
        $arrivedAt = $stockDelivery->received_at;
        $from      = $leftAt ? Carbon::parse($leftAt)->startOfDay() : null;
        $until     = $arrivedAt ? Carbon::parse($arrivedAt)->addDays(self::DAYS_INVOICED_AFTER_ARRIVAL)->endOfDay() : now()->endOfDay();

        return [
            'phrase'     => __('I confirm this is the correct invoice'),
            'reference'  => $stockDelivery->reference,
            'printed'    => array_slice($reading['references'] ?? [], 0, 10),
            'dates_fit'  => $from && self::isDate($reading['invoice_date']) ? Carbon::parse($reading['invoice_date'])->betweenIncluded($from, $until) : null,
            'left_at'    => $from?->toDateString(),
            'arrived_at' => $arrivedAt ? Carbon::parse($arrivedAt)->toDateString() : null,
        ];
    }

    public static function isDate(?string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) && strtotime((string) $date) !== false;
    }

    private function isAlreadyEntered(StockDelivery $stockDelivery, ?string $invoiceNumber): bool
    {
        $reference = StockDeliveryDocumentChecks::normalise($invoiceNumber);

        return $reference !== '' && StockDeliveryServiceInvoice::where('organisation_id', $stockDelivery->organisation_id)
            ->whereRaw("lower(regexp_replace(reference, '[^A-Za-z0-9]', '', 'g')) = ?", [$reference])
            ->exists();
    }

    private function exceedsGoodsValue(StockDelivery $stockDelivery, Currency $currency, float $total): bool
    {
        $goodsValue = (float) $stockDelivery->items()->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)->sum('org_net_amount');
        $exchange   = GetCurrencyExchange::run($currency, $stockDelivery->organisation->currency);

        return $goodsValue > 0 && $exchange && $total * $exchange > $goodsValue;
    }
}
