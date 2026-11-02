<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItemCost;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\SyncStockDeliveryServiceInvoiceCosts;
use App\Actions\SupplyChain\AgentInvoice\ApplyAgentInvoiceCosting;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\Helpers\Currency;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Writes the costs of a supplier invoice read from its document, exactly as read: each line's goods cost (the
 * invoice's unit price times what was received), the invoice as received at its total, and its charges as extra
 * expenses. The reviewer says only where a line that matched no product goes and what kind of expense each charge
 * is, never how much. The checks run again here, on the reading as stored, while the delivery is locked.
 *
 * An invoice in another currency than the delivery asks the reviewer whether the delivery follows the invoice:
 * yes moves the delivery to the invoice's currency, no keeps it and converts the invoice at the rate they give,
 * the lines adding up to the converted total to the cent.
 */
class ApplyStockDeliveryInvoiceCosting extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;

    private StockDelivery $stockDelivery;

    private Media $media;

    private ?Currency $invoiceCurrency = null;

    /**
     * The charge type a reviewer gives a freight charge: it fills the shipping row, like the agent invoice's freight.
     */
    public const string FREIGHT = 'freight';

    public function rules(): array
    {
        return [
            'read_at'               => ['required', 'string'],
            'assignments'           => ['sometimes', 'array', 'list'],
            'assignments.*.index'   => ['required', 'integer', 'min:0'],
            'assignments.*.item_id' => ['present', 'nullable', 'integer'],
            'charge_types'          => ['sometimes', 'array', 'list'],
            'charge_types.*'        => ['required', Rule::in([self::FREIGHT, ...array_column(StockDeliveryServiceInvoiceTypeEnum::extraOptions(), 'value')])],
            'checked'               => ['sometimes', 'boolean'],
            'confirmation'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'follow_invoice'        => ['sometimes', 'boolean'],
            'exchange'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $currency = Arr::get(ReadStockDeliveryInvoice::reading($this->stockDelivery, $this->media) ?? [], 'currency');

        if ($currency && strtoupper($currency) !== $this->stockDelivery->currency?->code) {
            $this->validateOtherCurrency($validator, strtoupper($currency));
        }
    }

    private function validateOtherCurrency(Validator $validator, string $code): void
    {
        $this->invoiceCurrency = Currency::whereRaw('upper(code) = ?', [$code])->first();

        if (!$this->invoiceCurrency) {
            $validator->errors()->add('currency', __('The invoice is in :invoice, a currency we do not know: enter its costs by hand', ['invoice' => $code]));

            return;
        }

        if (!$this->has('follow_invoice')) {
            $validator->errors()->add('currency', __('The invoice is in :invoice and the delivery in :delivery, choose whether the delivery follows the invoice', [
                'invoice'  => $code,
                'delivery' => $this->stockDelivery->currency?->code,
            ]));
        } elseif (filter_var($this->get('follow_invoice'), FILTER_VALIDATE_BOOLEAN)) {
            if ($blockers = ChangeStockDeliveryCurrency::blockers($this->stockDelivery)) {
                $validator->errors()->add('currency', __('The delivery cannot follow the invoice:').' '.implode(' ', $blockers));
            }
        } elseif (!$this->get('exchange')) {
            $validator->errors()->add('exchange', __('Enter the exchange rate from :invoice to :delivery', [
                'invoice'  => $code,
                'delivery' => $this->stockDelivery->currency?->code,
            ]));
        }
    }

    /**
     * The goods cost of each line in the delivery's currency. The cents lost to rounding go on the largest line,
     * so the lines add up to the converted total of what the reviewer entered.
     *
     * @param  array<int, array{id: int, cost: float|int|string}>  $lines
     * @return array<int, float> cost by item id
     */
    public static function convertedCosts(array $lines, float $exchange): array
    {
        $costs = [];
        foreach ($lines as $line) {
            $costs[$line['id']] = round((float) $line['cost'] * $exchange, 2);
        }

        if ($costs) {
            $largest          = array_search(max($costs), $costs);
            $costs[$largest] = round($costs[$largest] + round(array_sum(array_column($lines, 'cost')) * $exchange, 2) - array_sum($costs), 2);
        }

        return $costs;
    }

    /**
     * @param  array<string, mixed>  $modelData
     *
     * @throws ValidationException
     */
    public function handle(StockDelivery $stockDelivery, Media $media, array $modelData): StockDelivery
    {
        $invoiceCurrency = $this->invoiceCurrency;

        DB::transaction(function () use ($stockDelivery, $media, $modelData, $invoiceCurrency) {
            $stockDelivery = StockDelivery::whereKey($stockDelivery->id)->lockForUpdate()->firstOrFail();
            $reading       = ReadStockDeliveryInvoice::reading($stockDelivery, $media);
            $assignments   = collect(Arr::get($modelData, 'assignments', []))->mapWithKeys(fn (array $row) => [(int) $row['index'] => $row['item_id'] === null ? null : (int) $row['item_id']])->all();
            $review        = GetStockDeliveryInvoiceCosting::make()->review($stockDelivery, $reading, $media, $assignments);

            $this->refuseUnlessApplicable($stockDelivery, $review, $modelData);

            if ($invoiceCurrency && Arr::get($modelData, 'follow_invoice')) {
                ChangeStockDeliveryCurrency::run($stockDelivery, $invoiceCurrency);
                $invoiceCurrency = null;
            }

            $exchange          = $invoiceCurrency ? (float) $modelData['exchange'] : 1.0;
            $inInvoiceCurrency = $invoiceCurrency ? ['currency_id' => $invoiceCurrency->id, 'exchange' => $exchange] : ['currency_id' => null];
            $lines             = collect($review['items'])->whereNotNull('proposed_cost')->map(fn (array $row) => ['id' => $row['id'], 'cost' => $row['proposed_cost']])->values()->all();
            $costs             = self::convertedCosts($lines, $exchange);
            $items             = $stockDelivery->items()->whereIn('id', array_keys($costs))->get()->keyBy('id');

            foreach ($costs as $itemId => $cost) {
                /** @var StockDeliveryItem $item */
                $item = $items->get($itemId);
                UpdateStockDeliveryItemCost::make()->action($item, ['cost_items' => $cost]);
            }

            $freight = collect($review['charges'])
                ->filter(fn (array $charge, int $index) => $modelData['charge_types'][$index] === self::FREIGHT && (float) $charge['amount'] > 0);

            if ($freight->isNotEmpty()) {
                $this->invoiceFreightToShipping($stockDelivery, $freight, $exchange, $review['invoice_date']);
            }

            foreach ($review['charges'] as $index => $charge) {
                if ((float) $charge['amount'] > 0 && $modelData['charge_types'][$index] !== self::FREIGHT) {
                    StoreStockDeliveryCost::make()->action($stockDelivery, [
                        'type'        => StockDeliveryCostTypeEnum::EXTRA->value,
                        'category'    => $modelData['charge_types'][$index],
                        'label'       => mb_substr($charge['label'], 0, 255),
                        'amount'      => $charge['amount'],
                        'received_at' => $review['invoice_date'],
                        ...$inInvoiceCurrency,
                    ]);
                }
            }

            foreach ($review['not_in_delivery'] as $line) {
                if ((float) $line['amount'] > 0) {
                    StoreStockDeliveryCost::make()->action($stockDelivery, [
                        'type'        => StockDeliveryCostTypeEnum::EXTRA->value,
                        'category'    => StockDeliveryServiceInvoiceTypeEnum::NOT_IN_DELIVERY->value,
                        'label'       => $line['label'],
                        'amount'      => $line['amount'],
                        'received_at' => $review['invoice_date'],
                        ...$inInvoiceCurrency,
                    ]);
                }
            }

            $invoice     = [
                'label'                  => __('Invoice :number', ['number' => $review['invoice_number']]),
                'amount'                 => $review['total'],
                'received_at'            => $review['invoice_date'],
                'is_waiting_for_invoice' => false,
                ...$inInvoiceCurrency,
            ];
            $invoiceCost = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();

            if ($invoiceCost) {
                UpdateStockDeliveryCost::make()->action($invoiceCost, $invoice);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, ...$invoice]);
            }

            $data = $stockDelivery->refresh()->data ?? [];
            data_set($data, 'invoice_number', $review['invoice_number']);
            data_set($data, 'invoice_date', $review['invoice_date']);
            $stockDelivery->update(['data' => $data]);

            ReadStockDeliveryInvoice::storeReading($stockDelivery, $media, [
                ...$reading,
                'applied_at' => now()->toIso8601String(),
                'applied_by' => auth()->id(),
                ...($review['delivery_confirmation'] ? ['delivery_confirmed' => ['user_id' => auth()->id(), 'dates_fit' => $review['delivery_confirmation']['dates_fit']]] : []),
            ]);
        });

        return $stockDelivery->refresh();
    }

    /**
     * Freight on the invoice becomes the shipping, split by weight, and is kept as the invoice freight so the
     * forwarder's freight bills add to it. A shipping row left not applicable or empty (domestic deliveries are
     * prefilled so) takes the freight; shipping typed by hand may already hold it, so the invoice is refused rather
     * than count the freight twice.
     *
     * @param  \Illuminate\Support\Collection<int, array{label: string, amount: float}>  $freight
     *
     * @throws ValidationException
     */
    public function invoiceFreightToShipping(StockDelivery $stockDelivery, \Illuminate\Support\Collection $freight, float $exchange, string $date): void
    {
        $shipping = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->first();

        $placeholder = $shipping && !$shipping->from_service_invoices && ($shipping->is_na || $shipping->amount === null);

        if ($shipping && !$shipping->from_service_invoices && !$placeholder) {
            throw ValidationException::withMessages(['charge_types' => __('Shipping is already entered by hand on this delivery: put the invoice freight there, or remove it, before applying the invoice.')]);
        }

        $invoiceFreight = [
            'label'       => mb_substr($freight->pluck('label')->implode(', '), 0, 250),
            'amount'      => round($freight->sum('amount') * $exchange, 2),
            'received_at' => $date,
        ];

        $data = $stockDelivery->refresh()->data ?? [];
        data_set($data, ApplyAgentInvoiceCosting::FREIGHT, $invoiceFreight);
        $stockDelivery->update(['data' => $data]);

        if ($placeholder) {
            UpdateStockDeliveryCost::make()->action($shipping, [...$invoiceFreight, 'is_na' => false, 'currency_id' => null]);
        } elseif ($shipping) {
            SyncStockDeliveryServiceInvoiceCosts::run($stockDelivery->refresh());
        } else {
            StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, ...$invoiceFreight]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $review
     * @param  array<string, mixed>  $modelData
     *
     * @throws ValidationException
     */
    private function refuseUnlessApplicable(StockDelivery $stockDelivery, ?array $review, array $modelData): void
    {
        $refusal = match (true) {
            data_get($review, 'state') !== 'read' || !array_key_exists('problems', $review) => ['invoice' => __('This invoice has not been read yet')],
            (bool) ($review['applied_at'] ?? null)                                           => ['invoice' => __('This invoice is already in the costing, change the costs in the checklist')],
            ($review['read_at'] ?? null) !== $modelData['read_at']                           => ['invoice' => __('This invoice was read again since you opened it, review it again')],
            (bool) $review['problems']                                                       => ['invoice' => implode(' ', $review['problems'])],
            $review['needs_manual_check'] && empty($modelData['checked'])                    => ['checked' => __('This document has no text to check the reading against: confirm you have checked every amount')],
            $review['delivery_confirmation'] && self::typed($modelData['confirmation'] ?? null) !== self::typed($review['delivery_confirmation']['phrase']) => ['confirmation' => __('The document does not mention :reference: type ":phrase" to add it', ['reference' => $stockDelivery->reference, 'phrase' => $review['delivery_confirmation']['phrase']])],
            (bool) $review['unmatched']                                                      => ['assignments' => __('Say where every invoice line that matches no product goes')],
            count(Arr::get($modelData, 'charge_types', [])) !== count($review['charges'])    => ['charge_types' => __('Say what kind of expense every charge is')],
            default                                                                          => null,
        };

        if ($refusal) {
            throw ValidationException::withMessages($refusal);
        }
    }

    private static function typed(?string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $text), " .\t\n"));
    }

    public function asController(StockDelivery $stockDelivery, Media $media, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->media         = $media;

        abort_unless(ReadStockDeliveryInvoice::pivot($stockDelivery, $media)->exists(), 404);

        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $media, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, Media $media, array $modelData): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->media         = $media;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $media, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
