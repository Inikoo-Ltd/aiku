<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionMaterials;

use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\OrgAction;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Production\ProductionMaterialRequestItem;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class DeliverProductionMaterialRequestItem extends OrgAction
{
    /**
     * Walks a material the factory asked for from a shelf to the factory location. It stays
     * stock: production uses it up from there, and orders can still pick it. The warehouse
     * says how many SKOs it brought; what Aiku did not have on that shelf (or anywhere) is
     * recorded as unbooked, so the stock gets counted, but the delivery is never refused.
     *
     * @throws \Throwable
     */
    public function handle(ProductionMaterialRequestItem $item, ?LocationOrgStock $source, float $quantity, ?User $user): ProductionMaterialRequestItem
    {
        $production = $item->request->production;
        $factory    = $production->factoryLocation;

        if (!$factory) {
            throw ValidationException::withMessages(['production' => __('This production has no factory location')]);
        }
        if ($item->state !== ProductionMaterialRequestItemStateEnum::PENDING) {
            throw ValidationException::withMessages(['item' => __('This material is no longer waiting to be brought')]);
        }
        if ($source && ($source->org_stock_id !== $item->org_stock_id || $source->warehouse_id !== $item->request->warehouse_id
            || $source->location_id === $factory->id || $source->location->is_goods_out)) {
            throw ValidationException::withMessages(['source' => __('Materials can only be taken from a shelf in this warehouse')]);
        }

        $moved    = $source ? min($quantity, max(0, (float) $source->quantity)) : 0.0;
        $unbooked = round($quantity - $moved, 3);

        DB::transaction(function () use ($item, $source, $factory, $quantity, $moved, $unbooked, $user) {
            if ($source && $moved > 0) {
                $target = LocationOrgStock::where('location_id', $factory->id)
                    ->where('org_stock_id', $source->org_stock_id)
                    ->first()
                    ?? StoreLocationOrgStock::make()->action($source->orgStock, $factory, [
                        'type' => LocationStockTypeEnum::PICKING,
                    ]);

                MoveOrgStockToOtherLocation::make()->action($source, $target, ['quantity' => $moved], $user);
            }

            $delivered = round((float) $item->delivered_skos + $quantity, 3);
            $item->update([
                'delivered_skos'       => $delivered,
                'unbooked_skos'        => round((float) $item->unbooked_skos + $unbooked, 3),
                'delivered_by_user_id' => $user?->id,
                'delivered_at'         => now(),
                'state'                => $item->skos_to_bring === null || $delivered >= (float) $item->skos_to_bring
                    ? ProductionMaterialRequestItemStateEnum::DELIVERED
                    : ProductionMaterialRequestItemStateEnum::PENDING,
            ]);

            self::settleRequest($item);
        });

        if ($moved > 0) {
            OrgStockHydrateQuantityInLocations::run($item->org_stock_id);
        }

        return $item->refresh();
    }

    public static function settleRequest(ProductionMaterialRequestItem $item): void
    {
        $request = $item->request;
        if ($request->items()->where('state', ProductionMaterialRequestItemStateEnum::PENDING)->exists()) {
            return;
        }

        $request->update([
            'state'        => ProductionMaterialRequestStateEnum::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function rules(): array
    {
        return [
            'location_org_stock_id' => ['nullable', 'integer', 'exists:location_org_stocks,id'],
            'quantity'              => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->warehouse->canBeWorkedInBy($request->user());
    }

    /**
     * @throws \Throwable
     */
    public function action(ProductionMaterialRequestItem $item, ?LocationOrgStock $source, float $quantity, ?User $user = null): ProductionMaterialRequestItem
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($item->request->warehouse, [
            'location_org_stock_id' => $source?->id,
            'quantity'              => $quantity,
        ]);

        return $this->handle($item, $source, $quantity, $user);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ProductionMaterialRequestItem $productionMaterialRequestItem, ActionRequest $request): ProductionMaterialRequestItem
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        if ($productionMaterialRequestItem->request->warehouse_id !== $warehouse->id) {
            abort(404);
        }

        return $this->handle(
            $productionMaterialRequestItem,
            ($this->validatedData['location_org_stock_id'] ?? null) ? LocationOrgStock::findOrFail($this->validatedData['location_org_stock_id']) : null,
            (float) $this->validatedData['quantity'],
            $request->user()
        );
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Brought to the factory'),
        ]);
    }
}
