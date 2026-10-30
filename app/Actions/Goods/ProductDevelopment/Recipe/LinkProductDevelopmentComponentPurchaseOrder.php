<?php

/*
 * Author Louis Perez
 * Created on 28-10-2026-10h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Purchasing links the purchase orders that buy a component on the buyer's list, or unlinks one. A purchase
 * order belongs to one organisation, so a component bought for several organisations has several.
 */
class LinkProductDevelopmentComponentPurchaseOrder extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string LINK = 'link';

    public const string UNLINK = 'unlink';

    private ProductDevelopment $productDevelopment;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->productDevelopment)
            && $this->productDevelopmentAccess()->canApproveComponents($request->user(), $this->productDevelopment);
    }

    public function handle(ProductDevelopment $productDevelopment, User $user, array $modelData): void
    {
        $tradeUnit      = TradeUnit::findOrFail($modelData['trade_unit_id']);
        $purchaseOrders = PurchaseOrder::whereIn('id', $modelData['purchase_order_ids'])->orderBy('reference')->get();
        $link           = $modelData['operation'] === self::LINK;
        $linked         = $productDevelopment->componentPurchaseOrders()->wherePivot('trade_unit_id', $tradeUnit->id);

        foreach ($purchaseOrders as $purchaseOrder) {
            $isLinked = (clone $linked)->where('purchase_orders.id', $purchaseOrder->id)->exists();
            if ($link === $isLinked) {
                continue;
            }

            if ($link) {
                $productDevelopment->componentPurchaseOrders()->attach($purchaseOrder->id, ['trade_unit_id' => $tradeUnit->id, 'linked_by_id' => $user->id]);
            } else {
                $productDevelopment->componentPurchaseOrders()->wherePivot('trade_unit_id', $tradeUnit->id)->detach($purchaseOrder->id);
            }

            RecordProductDevelopmentActivity::record(
                $productDevelopment,
                $link ? RecordProductDevelopmentActivity::COMPONENT_PURCHASE_ORDER_LINKED : RecordProductDevelopmentActivity::COMPONENT_PURCHASE_ORDER_UNLINKED,
                [],
                ['purchase_order' => $purchaseOrder->reference, 'component' => $tradeUnit->code]
            );
        }
    }

    public function rules(): array
    {
        return [
            'trade_unit_id'        => ['required', 'integer', Rule::exists('product_development_components', 'trade_unit_id')->where('product_development_id', $this->productDevelopment->id)],
            'purchase_order_ids'   => ['required', 'array', 'min:1'],
            'purchase_order_ids.*' => ['integer', 'distinct', Rule::exists('purchase_orders', 'id')->where('group_id', $this->productDevelopment->group_id)],
            'operation'            => ['required', Rule::in([self::LINK, self::UNLINK])],
        ];
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): void
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $user, array $modelData): void
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        $this->handle($productDevelopment, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
