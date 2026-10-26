<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-26 10:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Takes one or more items out of a container in process, their units going back to awaiting delivery. A purchase
 * order with none of its items left in the container leaves it too.
 */
class RemoveStockDeliveryItemsFromStockDelivery extends OrgAction
{
    use HasStockDeliveryHydrators;

    private StockDelivery $stockDelivery;

    public static function removableItems(StockDelivery $stockDelivery): HasMany
    {
        return $stockDelivery->items()
            ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    public function rules(): array
    {
        return [
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in(self::removableItems($this->stockDelivery)->pluck('stock_delivery_items.id')->all()),
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!RemovePurchaseOrderFromStockDelivery::acceptsRemovals($this->stockDelivery)) {
            $validator->errors()->add('items', __('Items can only be removed from a container in process'));
        }
    }

    /**
     * @param  int[]  $stockDeliveryItemIds
     */
    public function handle(StockDelivery $stockDelivery, array $stockDeliveryItemIds): StockDelivery
    {
        [$purchaseOrdersLeft, $purchaseOrdersStaying] = DB::transaction(function () use ($stockDelivery, $stockDeliveryItemIds) {
            StockDelivery::whereKey($stockDelivery->id)->lockForUpdate()->first();

            $affectedPurchaseOrders = $stockDelivery->purchaseOrders()->get()
                ->filter(fn (PurchaseOrder $purchaseOrder) => RemovePurchaseOrderFromStockDelivery::itemsOfPurchaseOrder($stockDelivery, $purchaseOrder)
                    ->whereIn('stock_delivery_items.id', $stockDeliveryItemIds)
                    ->exists());

            self::removableItems($stockDelivery)->whereIn('stock_delivery_items.id', $stockDeliveryItemIds)->get()
                ->each(fn (StockDeliveryItem $stockDeliveryItem) => $stockDeliveryItem->delete());

            return $affectedPurchaseOrders->partition(
                fn (PurchaseOrder $purchaseOrder) => RemovePurchaseOrderFromStockDelivery::itemsOfPurchaseOrder($stockDelivery, $purchaseOrder)->doesntExist()
            );
        });

        $purchaseOrdersLeft->each(fn (PurchaseOrder $purchaseOrder) => RemovePurchaseOrderFromStockDelivery::make()->action($stockDelivery, $purchaseOrder));
        $purchaseOrdersStaying->each(fn (PurchaseOrder $purchaseOrder) => PurchaseOrderHydrateTransactions::dispatch($purchaseOrder));

        StockDeliveriesHydrateItems::run($stockDelivery);
        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData['items']);
    }

    /**
     * @param  int[]  $stockDeliveryItemIds
     */
    public function action(StockDelivery $stockDelivery, array $stockDeliveryItemIds): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, ['items' => $stockDeliveryItemIds]);

        return $this->handle($stockDelivery, $this->validatedData['items']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
