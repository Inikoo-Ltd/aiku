<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:47:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class MoveOrgStockToOtherLocation extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;
    use CalculatesOrgStockHistories;

    private User|null $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $currentLocationStock, LocationOrgStock $targetLocation, array $modelData): LocationOrgStock
    {
        DB::transaction(function () use ($currentLocationStock, $targetLocation, $modelData) {
            $quantity = Arr::pull($modelData, 'quantity');

            // Removed reason and note on move due to Tomas Request
            // $reason = Arr::pull($modelData, 'reason', null);
            // $note   = Arr::pull($modelData, 'note', null);
            // Source
            $this->processStockMovement($currentLocationStock, [
                'quantity'              => $currentLocationStock->quantity - $quantity,
                // 'reason'                => $reason,
                // 'note'                  => $note,
            ]);
            // Destination
            $this->processStockMovement($targetLocation, [
                'quantity'  => $targetLocation->quantity + $quantity,
                // 'reason'                => $reason,
                // 'note'                  => $note,
            ]);

            RepairOrgStockMissingLocationIds::dispatch($currentLocationStock->org_stock_id)->delay(2);
            OrgStockHydrateQuantityInLocations::run($currentLocationStock->org_stock_id);

        });

        $currentLocationStock->refresh();
        $targetLocation->refresh();

        return $currentLocationStock;
    }

    public function processStockMovement(LocationOrgStock $locationOrgStock, array $modelData): void
    {
        $currentStock = $locationOrgStock->quantity;
        $newQuantity  = Arr::pull($modelData, 'quantity');
        $stockDiff    = $newQuantity - $currentStock;

        $costPerSku = $this->getCostPerSku($locationOrgStock->orgStock, Carbon::now());

        $exchangeRate = GetCurrencyExchange::run($locationOrgStock->organisation->currency, $locationOrgStock->group->currency);

        $storedData = [
            'quantity'         => $stockDiff,
            'date'             => now()->format('Y-m-d H:i:s.u'),
            'type'             => OrgStockMovementTypeEnum::LOCATION_TRANSFER,
            'cost_per_sku'     => $costPerSku,
            'org_amount'       => $stockDiff * $costPerSku,
            'grp_amount'       => $stockDiff * $costPerSku * $exchangeRate,
            'user_id'          => $this->user?->id,
        ];

        // $reason = Arr::pull($modelData, 'reason', null);
        // $note   = Arr::pull($modelData, 'note', null);

        // if ($reason) {
        //     data_set($storedData, 'reason', $reason);
        // }

        // if ($note) {
        //     data_set($storedData, 'note', $note);
        // }

        StoreOrgStockMovement::make()->action(
            $locationOrgStock->orgStock,
            $locationOrgStock->location,
            $storedData
        );


    }

    public function rules(): array
    {
        return [
            'quantity'  => ['required','numeric','gt:0'],
            'reason'    => ['sometimes', 'nullable', new Enum(OrgStockMovementReasonEnum::class)],
            'note'                  => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $currentLocationStock, LocationOrgStock $targetLocationOrgStock, array $modelData): LocationOrgStock
    {
        $this->asAction = true;
        $this->initialisation($currentLocationStock->organisation, $modelData);
        return $this->handle($currentLocationStock, $targetLocationOrgStock, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, LocationOrgStock $targetLocationOrgStock, ActionRequest $request): void
    {
        $this->user = request()->user();
        $this->initialisation($locationOrgStock->organisation, $request);

        $this->handle($locationOrgStock, $targetLocationOrgStock, $this->validatedData);
    }
}
