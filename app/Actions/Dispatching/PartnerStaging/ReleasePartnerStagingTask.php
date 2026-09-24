<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerStaging;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ReleasePartnerStagingTask extends OrgAction
{
    /**
     * Give back what was pre-picked but never walked to the partner's goods out location: the
     * promise is dropped and the lines return to the pre-pick / to produce lists. Whatever
     * already sits in the goods out location stays promised.
     *
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, OrgPartner $orgPartner, OrgStock $orgStock): float
    {
        if ($orgPartner->organisation_id !== $warehouse->organisation_id || $orgStock->organisation_id !== $warehouse->organisation_id) {
            throw ValidationException::withMessages(['org_partner' => __('Partner does not belong to this warehouse')]);
        }

        return DB::transaction(function () use ($orgPartner, $orgStock) {
            $items = PartnerShoppingListItem::query()
                ->where('partner_organisation_id', $orgPartner->organisation_id)
                ->where('organisation_id', $orgPartner->partner_id)
                ->where('stock_id', $orgStock->stock_id)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->whereNotNull('pre_picked_at')
                ->orderByDesc('pre_picked_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $staged = (float) LocationOrgStock::where('location_id', $orgPartner->goods_out_location_id)
                ->where('org_stock_id', $orgStock->id)
                ->sum('quantity');

            $released  = max(round((float) $items->sum('quantity') - $staged, 3), 0.0);
            $remaining = $released;

            foreach ($items as $item) {
                if ($remaining <= 0) {
                    break;
                }

                $quantity = (float) $item->quantity;
                if ($quantity <= $remaining) {
                    $item->update(['pre_picked_at' => null]);
                    $remaining = round($remaining - $quantity, 3);
                    continue;
                }

                PartnerShoppingListItem::create([
                    ...$item->only([
                        'group_id',
                        'organisation_id',
                        'org_partner_id',
                        'partner_organisation_id',
                        'stock_id',
                        'org_stock_id',
                        'priority',
                        'needed_by',
                        'notes',
                        'added_by_user_id',
                    ]),
                    'parent_id'  => $item->id,
                    'quantity'   => $remaining,
                    'state'      => ShoppingListItemStateEnum::OPEN,
                    'created_at' => $item->created_at,
                ]);
                $item->update(['quantity' => round($quantity - $remaining, 3)]);
                $remaining = 0;
            }

            foreach ($items->pluck('orgPartner', 'org_partner_id')->filter() as $buyerOrgPartner) {
                OrgPartnerHydrateShoppingListItems::dispatch($buyerOrgPartner);
            }

            return $released;
        });
    }

    public function rules(): array
    {
        return [
            'org_partner_id' => ['required', 'integer', 'exists:org_partners,id'],
            'org_stock_id'   => ['required', 'integer', 'exists:org_stocks,id'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("dispatching.{$this->organisation->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function action(Warehouse $warehouse, OrgPartner $orgPartner, OrgStock $orgStock): float
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, [
            'org_partner_id' => $orgPartner->id,
            'org_stock_id'   => $orgStock->id,
        ]);

        return $this->handle($warehouse, $orgPartner, $orgStock);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): float
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(
            $warehouse,
            OrgPartner::findOrFail($this->validatedData['org_partner_id']),
            OrgStock::findOrFail($this->validatedData['org_stock_id'])
        );
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Sent back to production'),
        ]);
    }
}
