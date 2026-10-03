<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location;

use App\Actions\OrgAction;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItem;
use App\Actions\Production\PartnerShippingList\CherryPickPartnerShoppingListItems;
use App\Actions\Production\PartnerShippingList\GetPartnerOrdersInTheMaking;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CreateOrderFromLocationOrgStocks extends OrgAction
{
    private Location $location;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'inventory.'.$this->organisation->id.'.edit',
            'supervisor-locations.'.$this->warehouse->id,
            'locations.'.$this->warehouse->id.'.edit',
        ]);
    }

    /**
     * Orders what sits in a partner's goods out bay through the partner's shopping list, so the
     * lines end up ordered and the same stock can not be raised again from the partner orders block.
     *
     * @param  array<int, int>  $orgStockIds
     *
     * @return array<int, Order>
     *
     * @throws \Throwable
     */
    public function handle(Location $location, array $orgStockIds): array
    {
        $seller     = $location->organisation;
        $bayPartner = OrgPartner::where('organisation_id', $seller->id)
            ->withBay($location->id)
            ->first();
        $buyerPartner = $bayPartner
            ? OrgPartner::where('organisation_id', $bayPartner->partner_id)->where('partner_id', $seller->id)->first()
            : null;

        if (!$buyerPartner) {
            throw ValidationException::withMessages(['org_stock_ids' => __('This location is not the goods out bay of a partner')]);
        }

        $inTheBay = $location->locationOrgStocks()
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity', '>', 0)
            ->with('orgStock')
            ->get();

        $spokenFor = GetPartnerOrdersInTheMaking::awaitingPicking($seller)
            ->where('partner_shopping_list_items.organisation_id', $buyerPartner->organisation_id)
            ->whereIn('partner_shopping_list_items.stock_id', $inTheBay->pluck('orgStock.stock_id'))
            ->groupBy('partner_shopping_list_items.stock_id')
            ->selectRaw('partner_shopping_list_items.stock_id, sum(partner_shopping_list_items.quantity) as quantity')
            ->pluck('quantity', 'stock_id');

        return DB::transaction(function () use ($seller, $buyerPartner, $inTheBay, $spokenFor) {
            $lines          = [];
            $alreadyOrdered = [];
            foreach ($inTheBay as $locationOrgStock) {
                $orgStock = $locationOrgStock->orgStock;
                $free     = round((float) $locationOrgStock->quantity - (float) ($spokenFor[$orgStock->stock_id] ?? 0), 3);
                if ($free <= 0) {
                    $alreadyOrdered[] = $orgStock->code;
                    continue;
                }
                array_push($lines, ...$this->linesCovering($buyerPartner, $orgStock, $free));
            }

            if ($alreadyOrdered) {
                throw ValidationException::withMessages([
                    'org_stock_ids' => __('Already on an order waiting to be picked: :codes', ['codes' => implode(', ', $alreadyOrdered)]),
                ]);
            }

            if (!$lines) {
                throw ValidationException::withMessages(['org_stock_ids' => __('None of the selected SKOs are in this location')]);
            }

            $picked = CherryPickPartnerShoppingListItems::make()->action($seller, $lines);

            if ($picked['skipped']) {
                throw ValidationException::withMessages(['org_stock_ids' => collect($picked['skipped'])->pluck('reason')->unique()->implode(', ')]);
            }

            return $picked['orders'];
        });
    }

    /**
     * Bay stock the partner did not ask for is added to their list first, then the open lines
     * are taken in the order the partner orders block uses the bay.
     *
     * @return array<int, array{id: int, quantity: float}>
     */
    private function linesCovering(OrgPartner $buyerPartner, OrgStock $sellerOrgStock, float $quantity): array
    {
        $openLines = fn () => PartnerShoppingListItem::where('org_partner_id', $buyerPartner->id)
            ->where('stock_id', $sellerOrgStock->stock_id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->orderByRaw('pre_picked_at is null')
            ->orderByRaw('job_order_id is null')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $lines     = $openLines();
        $shortfall = round($quantity - (float) $lines->sum('quantity'), 3);
        if ($shortfall > 0) {
            $plainLine = $lines->first(fn (PartnerShoppingListItem $line) => !$line->job_order_id && !$line->pre_picked_at);
            if ($plainLine) {
                $plainLine->increment('quantity', $shortfall);
            } else {
                StorePartnerShoppingListItem::make()->action($buyerPartner, $sellerOrgStock, ['quantity' => $shortfall]);
            }
            $lines = $openLines();
        }

        $picks     = [];
        $remaining = $quantity;
        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }
            $take      = round(min((float) $line->quantity, $remaining), 3);
            $picks[]   = ['id' => $line->id, 'quantity' => $take];
            $remaining = round($remaining - $take, 3);
        }

        return $picks;
    }

    public function rules(): array
    {
        return [
            'org_stock_ids'   => ['required', 'array', 'min:1'],
            'org_stock_ids.*' => [
                'integer',
                Rule::exists('location_org_stocks', 'org_stock_id')->where('location_id', $this->location->id),
            ],
        ];
    }

    /**
     * @return array<int, Order>
     *
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): array
    {
        $this->location = $location;
        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location, $this->validatedData['org_stock_ids']);
    }

    /**
     * @param  array<int, Order>  $orders
     */
    public function jsonResponse(array $orders): array
    {
        return array_map(fn (Order $order) => $order->only(['id', 'slug', 'reference']), $orders);
    }

    /**
     * @param  array<int, Order>  $orders
     */
    public function htmlResponse(array $orders): RedirectResponse
    {
        $order = $orders[0];

        request()->session()->flash('notification', [
            'title'       => __('Order :reference', ['reference' => $order->reference]),
            'description' => __(':count lines in the order', ['count' => $order->transactions()->count()]),
            'status'      => 'success',
        ]);

        return redirect()->route('grp.org.shops.show.ordering.orders.show', [
            'organisation' => $order->organisation->slug,
            'shop'         => $order->shop->slug,
            'order'        => $order->slug,
        ]);
    }
}
