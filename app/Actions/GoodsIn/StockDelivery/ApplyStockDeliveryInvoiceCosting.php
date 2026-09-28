<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItemCost;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Writes the costs someone checked against the supplier invoice: each line's goods cost, the invoice
 * itself as received, and the extra charges they kept. The numbers come from the reviewer, never
 * straight from the reading.
 */
class ApplyStockDeliveryInvoiceCosting extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private StockDelivery $stockDelivery;

    private Media $media;

    public function rules(): array
    {
        return [
            'invoice_number'   => ['nullable', 'string', 'max:255'],
            'invoice_date'     => ['required', 'date'],
            'invoice_total'    => ['required', 'numeric', 'gte:0'],
            'items'            => ['required', 'array'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.cost'     => ['required', 'numeric', 'gte:0'],
            'charges'          => ['sometimes', 'array'],
            'charges.*.label'  => ['required', 'string', 'max:255'],
            'charges.*.amount' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::PLACED || $this->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('You can only edit the costs while the costing is in progress'));
        }

        $reading = ReadStockDeliveryInvoice::reading($this->stockDelivery, $this->media);

        if (Arr::get($reading, 'state') !== 'read') {
            $validator->errors()->add('invoice', __('This invoice has not been read yet'));
        } elseif (Arr::get($reading, 'applied_at')) {
            $validator->errors()->add('invoice', __('This invoice is already in the costing, change the costs in the checklist'));
        } elseif ($reading['currency'] && strtoupper($reading['currency']) !== $this->stockDelivery->currency?->code) {
            $validator->errors()->add('currency', __('The invoice is in :invoice and the delivery in :delivery, change the currency of the delivery first', [
                'invoice'  => strtoupper($reading['currency']),
                'delivery' => $this->stockDelivery->currency?->code,
            ]));
        }

        $ids = collect($this->get('items', []))->pluck('id');

        if ($this->stockDelivery->items()->whereIn('id', $ids)->count() !== $ids->unique()->count()) {
            $validator->errors()->add('items', __('Some lines are not in this stock delivery'));
        }
    }

    public function handle(StockDelivery $stockDelivery, Media $media, array $modelData): StockDelivery
    {
        DB::transaction(function () use ($stockDelivery, $modelData) {
            $items = $stockDelivery->items()->whereIn('id', Arr::pluck($modelData['items'], 'id'))->get()->keyBy('id');

            foreach ($modelData['items'] as $line) {
                /** @var StockDeliveryItem $item */
                $item = $items->get($line['id']);
                UpdateStockDeliveryItemCost::make()->action($item, ['cost_items' => $line['cost']]);
            }

            foreach (Arr::get($modelData, 'charges', []) as $charge) {
                StoreStockDeliveryCost::make()->action($stockDelivery, [
                    'type'        => StockDeliveryCostTypeEnum::EXTRA->value,
                    'label'       => $charge['label'],
                    'amount'      => $charge['amount'],
                    'received_at' => $modelData['invoice_date'],
                ]);
            }

            $invoice     = [
                'label'       => $modelData['invoice_number'] ? __('Invoice :number', ['number' => $modelData['invoice_number']]) : null,
                'amount'      => $modelData['invoice_total'],
                'received_at' => $modelData['invoice_date'],
            ];
            $invoiceCost = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();

            if ($invoiceCost) {
                UpdateStockDeliveryCost::make()->action($invoiceCost, $invoice);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, ...$invoice]);
            }
        });

        ReadStockDeliveryInvoice::storeReading($stockDelivery, $media, [
            ...(ReadStockDeliveryInvoice::reading($stockDelivery, $media) ?? []),
            'applied_at' => now()->toIso8601String(),
        ]);

        return $stockDelivery->refresh();
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
