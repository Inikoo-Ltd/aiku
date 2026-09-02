<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\OrgAction;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MoveOrgStockToMultipleLocations extends OrgAction
{
    use WithLocationOrgStockActionAuthorisation;

    private User|null $user = null;

    private LocationOrgStock|null $sourceLocationOrgStock = null;

    /**
     * @param array{targets: array<int, array{location_org_stock_id: int, quantity: float}>} $modelData
     *
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $sourceLocationOrgStock, array $modelData): LocationOrgStock
    {
        DB::transaction(function () use ($sourceLocationOrgStock, $modelData) {
            foreach (Arr::get($modelData, 'targets', []) as $target) {
                $targetLocationOrgStock = LocationOrgStock::findOrFail(Arr::get($target, 'location_org_stock_id'));

                MoveOrgStockToOtherLocation::make()->action(
                    $sourceLocationOrgStock,
                    $targetLocationOrgStock,
                    ['quantity' => Arr::get($target, 'quantity')],
                    $this->user
                );
            }
        });

        return $sourceLocationOrgStock->refresh();
    }

    public function rules(): array
    {
        return [
            'targets'                         => ['required', 'array', 'min:1', $this->targetsAreValid(...)],
            'targets.*.location_org_stock_id' => ['required', 'integer', Rule::exists('location_org_stocks', 'id')],
            'targets.*.quantity'              => ['required', 'numeric', 'gt:0'],
        ];
    }

    private function targetsAreValid(string $attribute, mixed $value, \Closure $fail): void
    {
        if (!is_array($value) || $this->sourceLocationOrgStock === null) {
            return;
        }

        $targetIds = collect($value)->map(fn ($target) => Arr::get($target, 'location_org_stock_id'))->filter();

        if ($targetIds->duplicates()->isNotEmpty()) {
            $fail(__('Each destination location can only be used once.'));

            return;
        }

        if ($targetIds->contains($this->sourceLocationOrgStock->id)) {
            $fail(__('The source and the destination location must be different.'));

            return;
        }

        $numberOfTargetsHoldingSameOrgStock = LocationOrgStock::whereIn('id', $targetIds)
            ->where('org_stock_id', $this->sourceLocationOrgStock->org_stock_id)
            ->count();

        if ($numberOfTargetsHoldingSameOrgStock !== $targetIds->count()) {
            $fail(__('All destination locations must hold the same SKU as the source location.'));

            return;
        }

        $totalQuantity = collect($value)->sum(fn ($target) => (float) Arr::get($target, 'quantity', 0));

        if ($totalQuantity > (float) $this->sourceLocationOrgStock->quantity + 0.000001) {
            $fail(__('Only :quantity in stock in :location, can not move more than that.', [
                'quantity' => trimDecimalZeros($this->sourceLocationOrgStock->quantity),
                'location' => $this->sourceLocationOrgStock->location->code,
            ]));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $sourceLocationOrgStock, array $modelData): LocationOrgStock
    {
        $this->asAction               = true;
        $this->sourceLocationOrgStock = $sourceLocationOrgStock;
        $this->initialisation($sourceLocationOrgStock->organisation, $modelData);

        return $this->handle($sourceLocationOrgStock, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): void
    {
        $this->user                   = $request->user();
        $this->sourceLocationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $request);

        $this->handle($locationOrgStock, $this->validatedData);
    }
}
