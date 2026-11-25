<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-21 15:29
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent containers not dispatched yet the items of a purchase order can go in, searched by reference, fetched when the
 * picker opens so the purchase order page does not load them up front.
 */
class IndexOpenAgentContainersOfPurchaseOrder extends OrgAction
{
    private const int LIMIT = 20;

    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, ?string $search = null): Collection
    {
        return StoreStockDeliveryFromPurchaseOrder::openAgentDeliveriesQuery($purchaseOrder)
            ->when($search, fn ($query) => $query->whereAnyWordStartWith('reference', $search))
            ->limit(self::LIMIT)
            ->get(['id', 'reference', 'date', 'state', 'number_purchase_orders'])
            ->map(fn (StockDelivery $stockDelivery) => [
                'id'                     => $stockDelivery->id,
                'reference'              => $stockDelivery->reference,
                'date'                   => $stockDelivery->date,
                'state'                  => $stockDelivery->state->value,
                'state_label'            => StockDeliveryStateEnum::labels()[$stockDelivery->state->value],
                'state_icon'             => Arr::only(StockDeliveryStateEnum::stateIcon()[$stockDelivery->state->value], ['icon', 'class']),
                'number_purchase_orders' => $stockDelivery->number_purchase_orders,
            ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->authTo("procurement.{$this->purchaseOrder->organisation_id}.edit")) {
            return true;
        }

        $agentOrganisationId = $this->purchaseOrder->isAgentOrder() ? $this->purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function jsonResponse(Collection $containers): array
    {
        return ['data' => $containers->values()->all()];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): Collection
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData['search'] ?? null);
    }
}
