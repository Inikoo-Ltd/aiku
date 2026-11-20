<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:47:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\SetOrgStockPickingLocation;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\AllocateOrgStockMovementBatches;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MoveOrgStockToOtherLocation extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;
    use CalculatesOrgStockHistories;

    private User|null $user = null;

    private LocationOrgStock|null $sourceLocationOrgStock = null;

    private LocationOrgStock|null $targetLocationOrgStock = null;

    private bool $withoutSourceMovement = false;

    private bool $withoutTargetMovement = false;

    /**
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $currentLocationStock, LocationOrgStock $targetLocation, array $modelData): LocationOrgStock
    {
        DB::transaction(function () use ($currentLocationStock, $targetLocation, $modelData) {
            $quantity = Arr::pull($modelData, 'quantity');
            $reason   = Arr::get($modelData, 'reason');
            $note     = Arr::get($modelData, 'note');

            $sourceMovement = $this->withoutSourceMovement ? null : $this->processStockMovement($currentLocationStock, [
                'quantity' => $currentLocationStock->quantity - $quantity,
                'reason'   => $reason,
                'note'     => $note,
            ]);

            if (!$this->withoutTargetMovement) {
                $this->processStockMovement($targetLocation, [
                    'quantity' => $targetLocation->quantity + $quantity,
                    'batches'  => $sourceMovement ? AllocateOrgStockMovementBatches::make()->movedBatches($sourceMovement) : [],
                    'reason'   => $reason,
                    'note'     => $note,
                ]);
            }

            SetOrgStockPickingLocation::dispatch($currentLocationStock->org_stock_id)->delay(2);
            OrgStockHydrateQuantityInLocations::run($currentLocationStock->org_stock_id);

        });

        $currentLocationStock->refresh();
        $targetLocation->refresh();

        return $currentLocationStock;
    }

    public function processStockMovement(LocationOrgStock $locationOrgStock, array $modelData): OrgStockMovement
    {
        $currentStock = $locationOrgStock->quantity;
        $newQuantity  = Arr::pull($modelData, 'quantity');
        $stockDiff    = $newQuantity - $currentStock;

        $costPerSku = $this->getLppPerSku($locationOrgStock->orgStock, Carbon::now());

        $exchangeRate = GetCurrencyExchange::run($locationOrgStock->organisation->currency, $locationOrgStock->group->currency);

        $storedData = [
            'quantity'         => $stockDiff,
            'date'             => now()->format('Y-m-d H:i:s.u'),
            'type'             => OrgStockMovementTypeEnum::LOCATION_TRANSFER,
            'cost_per_sku'     => $costPerSku,
            'org_amount'       => $stockDiff * $costPerSku,
            'grp_amount'       => $stockDiff * $costPerSku * $exchangeRate,
            'user_id'          => $this->user?->id,
            'batches'          => Arr::get($modelData, 'batches', []),
        ];

        if ($reason = Arr::get($modelData, 'reason')) {
            data_set($storedData, 'reason', $reason);
        }

        if ($note = Arr::get($modelData, 'note')) {
            data_set($storedData, 'note', $note);
        }

        return StoreOrgStockMovement::make()->action(
            $locationOrgStock->orgStock,
            $locationOrgStock->location,
            $storedData
        );


    }

    public function rules(): array
    {
        return [
            'quantity'  => ['required','numeric','gt:0', $this->quantityFitsInSourceLocation(...)],
            'reason'    => ['sometimes', 'nullable', Rule::in(OrgStockMovementReasonEnum::transferReason())],
            'note'      => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * Stock stored with six decimals, so a fraction such as 1/6 is compared with the same
     * tolerance the stored quantity was rounded with.
     */
    private function quantityFitsInSourceLocation(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($this->sourceLocationOrgStock === null) {
            return;
        }

        if ($this->sourceLocationOrgStock->id === $this->targetLocationOrgStock?->id) {
            $fail(__('The source and the destination location must be different.'));

            return;
        }

        if (!$this->withoutSourceMovement && (float) $value > (float) $this->sourceLocationOrgStock->quantity + 0.000001) {
            $fail(__('Only :quantity in stock in :location, can not move more than that.', [
                'quantity' => trimDecimalZeros($this->sourceLocationOrgStock->quantity),
                'location' => $this->sourceLocationOrgStock->location->code,
            ]));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $currentLocationStock, LocationOrgStock $targetLocationOrgStock, array $modelData, User|null $user = null, bool $withoutSourceMovement = false, bool $withoutTargetMovement = false): LocationOrgStock
    {
        $this->asAction = true;
        $this->user = $user;
        $this->sourceLocationOrgStock = $currentLocationStock;
        $this->targetLocationOrgStock = $targetLocationOrgStock;
        $this->withoutSourceMovement = $withoutSourceMovement;
        $this->withoutTargetMovement = $withoutTargetMovement;

        return DB::transaction(function () use ($currentLocationStock, $targetLocationOrgStock, $modelData) {
            $this->lockRowsInIdOrder($currentLocationStock, $targetLocationOrgStock);
            $this->initialisation($currentLocationStock->organisation, $modelData);

            return $this->handle($currentLocationStock, $targetLocationOrgStock, $this->validatedData);
        });
    }

    private function lockRowsInIdOrder(LocationOrgStock $sourceLocationOrgStock, LocationOrgStock $targetLocationOrgStock): void
    {
        $quantities = LocationOrgStock::whereIn('id', [$sourceLocationOrgStock->id, $targetLocationOrgStock->id])
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('quantity', 'id');

        foreach ([$sourceLocationOrgStock, $targetLocationOrgStock] as $locationOrgStock) {
            if ($quantities->has($locationOrgStock->id)) {
                $locationOrgStock->forceFill(['quantity' => $quantities->get($locationOrgStock->id)])->syncOriginalAttribute('quantity');
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, LocationOrgStock $targetLocationOrgStock, ActionRequest $request): void
    {
        $this->user = request()->user();
        $this->sourceLocationOrgStock = $locationOrgStock;
        $this->targetLocationOrgStock = $targetLocationOrgStock;

        DB::transaction(function () use ($locationOrgStock, $targetLocationOrgStock, $request) {
            $this->lockRowsInIdOrder($locationOrgStock, $targetLocationOrgStock);
            $this->initialisation($locationOrgStock->organisation, $request);

            $this->handle($locationOrgStock, $targetLocationOrgStock, $this->validatedData);
        });
    }
}
