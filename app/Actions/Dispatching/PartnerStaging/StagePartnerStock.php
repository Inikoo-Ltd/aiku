<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerStaging;

use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\OrgAction;
use App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
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

class StagePartnerStock extends OrgAction
{
    public float $notReserved = 0.0;

    /**
     * Walk stock from a picking location into the partner's goods out location, which is
     * what takes it out of everybody else's availability.
     *
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, LocationOrgStock $source, OrgPartner $orgPartner, float $quantity): LocationOrgStock
    {
        if (!$orgPartner->goods_out_location_id) {
            throw ValidationException::withMessages(['org_partner' => __('This partner has no goods out location')]);
        }
        if ($orgPartner->organisation_id !== $warehouse->organisation_id) {
            throw ValidationException::withMessages(['org_partner' => __('Partner does not belong to this warehouse')]);
        }
        if ($source->location->warehouse_id !== $warehouse->id || $source->location->is_goods_out) {
            throw ValidationException::withMessages(['source' => __('Stock can only be staged from a shelf in this warehouse')]);
        }
        if ($quantity > (float) $source->quantity) {
            throw ValidationException::withMessages(['quantity' => __('Not that much stock in :location', ['location' => $source->location->code])]);
        }

        return DB::transaction(function () use ($source, $orgPartner, $quantity) {
            OrgStock::whereKey($source->org_stock_id)->lockForUpdate()->first();

            $this->notReserved = $this->prePickWhatWasWalkedBeyondThePromise($source, $orgPartner, $quantity);

            return $this->move($source, $orgPartner, $quantity);
        });
    }

    /**
     * Stock walked to a partner's bay is that partner's: whatever goes beyond what was pre-picked
     * is pre-picked now onto the partner's waiting lines, so they stop asking for it to be made.
     * Returns what could not be reserved: the partner does not ask for it, or it was promised to another partner.
     */
    private function prePickWhatWasWalkedBeyondThePromise(LocationOrgStock $source, OrgPartner $orgPartner, float $quantity): float
    {
        $waitingLines = PartnerShoppingListItem::query()
            ->where('partner_organisation_id', $orgPartner->organisation_id)
            ->where('organisation_id', $orgPartner->partner_id)
            ->where('stock_id', $source->orgStock->stock_id)
            ->where('state', ShoppingListItemStateEnum::OPEN);

        $promised = (float) (clone $waitingLines)->whereNotNull('pre_picked_at')->sum('quantity');
        $staged   = (float) LocationOrgStock::whereIn('location_id', $orgPartner->bayIds())
            ->where('org_stock_id', $source->org_stock_id)
            ->sum('quantity');

        $beyondThePromise = round($quantity - max(0, $promised - $staged), 3);
        if ($beyondThePromise <= 0) {
            return 0.0;
        }

        $lines   = [];
        $toCover = $beyondThePromise;
        foreach ($waitingLines->whereNull('pre_picked_at')->whereNull('job_order_id')->whereNull('preparing_at')->orderBy('created_at')->orderBy('id')->get(['id', 'quantity']) as $line) {
            if ($toCover <= 0) {
                break;
            }
            $lines[] = ['id' => $line->id, 'quantity' => min((float) $line->quantity, $toCover)];
            $toCover = round($toCover - (float) $line->quantity, 3);
        }

        $prePicked = $lines ? PrePickPartnerShoppingListItems::make()->action($orgPartner->organisation, $lines)['quantity'] : 0.0;

        return round($beyondThePromise - $prePicked, 3);
    }

    private function move(LocationOrgStock $source, OrgPartner $orgPartner, float $quantity): LocationOrgStock
    {
        $bay = $orgPartner->bayFor((bool) $source->orgStock->stock->is_cosmetic, $source->orgStock->isOnGbPallet());

        $target = LocationOrgStock::where('location_id', $bay->id)
            ->where('org_stock_id', $source->org_stock_id)
            ->first()
            ?? StoreLocationOrgStock::make()->action($source->orgStock, $bay, [
                'type' => LocationStockTypeEnum::PICKING,
            ]);

        MoveOrgStockToOtherLocation::make()->action($source, $target, ['quantity' => $quantity]);

        /* The move hydrates before the target slot is written, which leaves the goods out
           quantity still counted as available; recompute once both slots are settled. */
        OrgStockHydrateQuantityInLocations::run($source->org_stock_id);

        return $target->refresh();
    }

    public function rules(): array
    {
        return [
            'location_org_stock_id' => ['required', 'integer', 'exists:location_org_stocks,id'],
            'org_partner_id'        => ['required', 'integer', 'exists:org_partners,id'],
            'quantity'              => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("dispatching.{$this->warehouse->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function action(Warehouse $warehouse, LocationOrgStock $source, OrgPartner $orgPartner, float $quantity): LocationOrgStock
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, [
            'location_org_stock_id' => $source->id,
            'org_partner_id'        => $orgPartner->id,
            'quantity'              => $quantity,
        ]);

        return $this->handle($warehouse, $source, $orgPartner, $quantity);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LocationOrgStock
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(
            $warehouse,
            LocationOrgStock::findOrFail($this->validatedData['location_org_stock_id']),
            OrgPartner::findOrFail($this->validatedData['org_partner_id']),
            (float) $this->validatedData['quantity']
        );
    }

    public function htmlResponse(): RedirectResponse
    {
        if ($this->notReserved > 0) {
            return Redirect::back()->with('notification', [
                'status'      => 'warning',
                'title'       => __('Moved, but :quantity are not reserved for this partner', ['quantity' => $this->notReserved + 0]),
                'description' => __('That stock was promised to another partner, or this partner does not ask for it. Check the pre-pick list.'),
            ]);
        }

        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Stock moved to the partner goods out location'),
        ]);
    }
}
