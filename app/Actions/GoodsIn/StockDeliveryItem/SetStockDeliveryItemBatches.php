<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The batches a delivery line arrived in: code, best-before and SKOs of each. The put-aways
 * take them in this order. A batch already on a shelf cannot drop below what was put away.
 */
class SetStockDeliveryItemBatches extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    private StockDeliveryItem $stockDeliveryItem;

    public function rules(): array
    {
        return [
            'batches'               => ['present', 'array'],
            'batches.*.code'        => ['required', 'string', 'max:64'],
            'batches.*.expiry_date' => ['nullable', 'date'],
            'batches.*.quantity'    => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDeliveryItem->state === StockDeliveryItemStateEnum::CANCELLED || !$this->stockDeliveryItem->stockDelivery->isInGoodsIn()) {
            $validator->errors()->add('batches', __('Batches can only be entered while the delivery is being booked in'));

            return;
        }

        $checkedSkos = (float) $this->stockDeliveryItem->unit_quantity_checked / $this->stockDeliveryItem->unitsPerSko();
        $total       = collect($this->get('batches', []))->sum(fn ($batch) => (float) ($batch['quantity'] ?? 0));
        if ($total - $checkedSkos > 0.00005) {
            $validator->errors()->add('batches', __('The batches add up to :total, more than the :checked checked', ['total' => round($total, 4), 'checked' => round($checkedSkos, 4)]));
        }
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $warehouse = $stockDeliveryItem->organisation->warehouses()->first();

        DB::transaction(function () use ($stockDeliveryItem, $modelData, $warehouse) {
            StockDeliveryItem::lockForUpdate()->find($stockDeliveryItem->id);

            $quantities = [];
            foreach ($modelData['batches'] as $batch) {
                $batchCode = StoreBatchCode::make()->action($warehouse, [
                    'code'         => trim($batch['code']),
                    'expiry_date'  => $batch['expiry_date'] ?? null,
                    'org_stock_id' => $stockDeliveryItem->org_stock_id,
                ]);
                $quantities[$batchCode->id] = ($quantities[$batchCode->id] ?? 0) + (float) $batch['quantity'];
            }

            foreach ($stockDeliveryItem->placedBatchQuantities() as $batchCodeId => $placed) {
                if ($placed - ($quantities[$batchCodeId] ?? 0) > 0.00005) {
                    throw ValidationException::withMessages([
                        'batches' => __(':placed of a batch are already on a shelf; undo that put-away first', ['placed' => round($placed, 4)]),
                    ]);
                }
            }

            $stockDeliveryItem->batches()->delete();
            foreach ($quantities as $batchCodeId => $quantity) {
                $stockDeliveryItem->batches()->create([
                    'group_id'        => $stockDeliveryItem->group_id,
                    'organisation_id' => $stockDeliveryItem->organisation_id,
                    'batch_code_id'   => $batchCodeId,
                    'quantity'        => $quantity,
                ]);
            }
        });

        return $stockDeliveryItem->refresh();
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $this->asAction          = true;
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}
