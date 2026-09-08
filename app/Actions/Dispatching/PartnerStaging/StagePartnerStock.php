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
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StagePartnerStock extends OrgAction
{
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
        if ($quantity > (float) $source->quantity) {
            throw ValidationException::withMessages(['quantity' => __('Not that much stock in :location', ['location' => $source->location->code])]);
        }

        $target = LocationOrgStock::where('location_id', $orgPartner->goods_out_location_id)
            ->where('org_stock_id', $source->org_stock_id)
            ->first()
            ?? StoreLocationOrgStock::make()->action($source->orgStock, $orgPartner->goodsOutLocation, [
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

        return $request->user()->authTo("dispatching.{$this->organisation->id}.edit");
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
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Stock moved to the partner goods out location'),
        ]);
    }
}
