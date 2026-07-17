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
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class AuditLocationOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;
    use CalculatesOrgStockHistories;

    private LocationOrgStock $locationOrgStock;
    private User|null $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {
        $locationOrgStock = DB::transaction(function () use ($locationOrgStock, $modelData) {
            $currentStock = $locationOrgStock->quantity;
            $newQuantity  = Arr::pull($modelData, 'quantity');
            $reason       = Arr::pull($modelData, 'reason');
            $note         = Arr::pull($modelData, 'note');
            $stockDiff    = $newQuantity - $currentStock;

            $costPerSku = $this->getCostPerSku($locationOrgStock->orgStock, Carbon::now());

            $exchangeRate = GetCurrencyExchange::run($locationOrgStock->organisation->currency, $locationOrgStock->group->currency);

            $storedData    = [
                'quantity'         => $stockDiff,
                'audited_quantity' => $newQuantity,
                'date'             => now()->format('Y-m-d H:i:s.u'),
                'type'             => Arr::pull($modelData, 'stock_movement_type', OrgStockMovementTypeEnum::AUDIT),
                'cost_per_sku'     => $costPerSku,
                'org_amount'       => $stockDiff * $costPerSku,
                'grp_amount'       => $stockDiff * $costPerSku * $exchangeRate,
                'user_id'          => $this->user?->id,
            ];

            if ($reason) {
                data_set($storedData, 'reason', $reason);
            }

            if ($note) {
                data_set($storedData, 'note', $note);
            }

            StoreOrgStockMovement::make()->action(
                $locationOrgStock->orgStock,
                $locationOrgStock->location,
                $storedData
            );
            // Update audited_at
            $locationOrgStock->updateQuietly([
                'audited_at'    =>  now()
            ]);
            $locationOrgStock->refresh();

            return $locationOrgStock;
        });

        RepairOrgStockMissingLocationIds::dispatch($locationOrgStock->org_stock_id)->delay(2);
        OrgStockHydrateQuantityInLocations::dispatch($locationOrgStock->org_stock_id)->delay(2);

        return $locationOrgStock;
    }

    public function rules(): array
    {
        return [
            'quantity'              => ['required', 'numeric', 'gte:0'],
            'reason'                => ['required', new Enum(OrgStockMovementReasonEnum::class)],
            'note'                  => ['sometimes', 'nullable', 'string'],
            'stock_movement_type'   => ['sometimes', new Enum(OrgStockMovementTypeEnum::class)]
        ];
    }


    public function prepareForValidation(): void
    {
        if (!$this->has('quantity')) {
            $this->set('quantity', $this->locationOrgStock->quantity);
            $this->set('reason', OrgStockMovementReasonEnum::RECOUNT->value);
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $locationOrgStock, array $modelData, ?User $user = null): LocationOrgStock
    {
        $this->asAction         = true;
        $this->locationOrgStock = $locationOrgStock;
        if ($user) {
            $this->user = $user;
        }

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
