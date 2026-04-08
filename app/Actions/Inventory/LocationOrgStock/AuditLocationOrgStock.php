<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 01:01:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class AuditLocationOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;
    use CalculatesOrgStockHistories;

    private LocationOrgStock $locationOrgStock;
    private User|null $user;

    /**
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {
        data_set($modelData, 'audited_at', now());


        $locationOrgStock = DB::transaction(function () use ($locationOrgStock, $modelData) {
            $currentStock = $locationOrgStock->quantity;
            $newQuantity  = Arr::pull($modelData, 'quantity');
            $stockDiff    = $newQuantity - $currentStock;

            $costPerSku = $this->getCostPerSku($locationOrgStock->orgStock, now());


            $locationOrgStock = $this->update(
                $locationOrgStock,
                [
                    'quantity' => $newQuantity,
                    'value'    => $newQuantity * $costPerSku,
                ]
            );

            $exchangeRate = GetCurrencyExchange::run($locationOrgStock->organisation->currency, $locationOrgStock->group->currency);

            StoreOrgStockMovement::make()->action(
                $locationOrgStock->orgStock,
                $locationOrgStock->location,
                [
                    'quantity'         => $stockDiff,
                    'audited_quantity' => $newQuantity,
                    'date'             => now()->format('Y-m-d H:i:s.u'),
                    'type'             => OrgStockMovementTypeEnum::AUDIT,
                    'cost_per_sku'     => $costPerSku,
                    'org_amount'       => $stockDiff * $costPerSku,
                    'grp_amount'       => $stockDiff * $costPerSku * $exchangeRate,
                    'user_id'          => $this->user?->id,

                ]
            );

            return $locationOrgStock;
        });

        RepairOrgStockMissingLocationIds::dispatch($locationOrgStock->orgStock);
        OrgStockHydrateQuantityInLocations::dispatch($locationOrgStock->orgStock);

        return $locationOrgStock;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gte:0'],
        ];
    }


    public function prepareForValidation(): void
    {
        if (!$this->has('quantity')) {
            $this->set('quantity', $this->locationOrgStock->quantity);
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {
        $this->asAction         = true;
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $modelData);

        return $this->handle($locationOrgStock, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): LocationOrgStock
    {
        $this->user = request()->user();
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $request);

        return $this->handle($locationOrgStock, $this->validatedData);
    }
}
