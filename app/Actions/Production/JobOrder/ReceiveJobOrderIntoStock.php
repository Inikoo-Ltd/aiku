<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ReceiveJobOrderIntoStock extends OrgAction
{
    private ?ActionRequest $request = null;

    public function handle(JobOrder $jobOrder, array $modelData): JobOrder
    {
        if ($jobOrder->state != JobOrderStateEnum::CONFIRMED) {
            throw ValidationException::withMessages([
                'state' => __('Only a confirmed job order can be received into stock'),
            ]);
        }

        /** @var Location $location */
        $location = Location::findOrFail($modelData['location_id']);

        $items = $jobOrder->jobOrderItems()->with(['artefact', 'tasks'])->get();

        $totalProduced = 0;

        foreach ($items as $item) {
            if (!$item->artefact->org_stock_id) {
                throw ValidationException::withMessages([
                    'location_id' => __('Artefact :code is not linked to a stock', ['code' => $item->artefact->code]),
                ]);
            }

            $totalProduced += $this->producedQuantity($item);
        }

        if ($totalProduced <= 0) {
            throw ValidationException::withMessages([
                'location_id' => __('Nothing has been made yet'),
            ]);
        }

        DB::transaction(function () use ($items, $jobOrder, $location) {
            $lockedState = JobOrder::lockForUpdate()->find($jobOrder->id)->state;
            if ($lockedState != JobOrderStateEnum::CONFIRMED) {
                throw ValidationException::withMessages([
                    'state' => __('Only a confirmed job order can be received into stock'),
                ]);
            }

            foreach ($items as $item) {
                $producedQuantity = $this->producedQuantity($item);

                if ($producedQuantity <= 0) {
                    continue;
                }

                $orgStock = $item->artefact->orgStock;

                if (!LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $orgStock->id)->exists()) {
                    StoreLocationOrgStock::make()->action($orgStock, $location, []);
                }

                StoreBatchCode::make()->action($location->warehouse, [
                    'code'         => $jobOrder->reference.'-'.$item->artefact->code,
                    'org_stock_id' => $orgStock->id,
                ]);

                StoreOrgStockMovement::make()->action($orgStock, $location, [
                    'quantity' => $producedQuantity,
                    'type'     => OrgStockMovementTypeEnum::PRODUCTION,
                    'user_id'  => $this->request?->user()?->id,
                ]);

                $this->deductRawMaterials($item, $producedQuantity);
            }

            $jobOrder->update([
                'state'       => JobOrderStateEnum::RECEIVED,
                'received_at' => now(),
            ]);
        });

        return $jobOrder;
    }

    private function producedQuantity(JobOrderItem $item): float
    {
        $lastTask = $item->tasks->sortByDesc('position')->first();

        if (!$lastTask) {
            return 0.0;
        }

        $unitsPerArtefact = ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
            ->where('manufacture_task_id', $lastTask->manufacture_task_id)
            ->value('units_per_artefact');

        $unitsPerArtefact = (float) $unitsPerArtefact;

        if ($unitsPerArtefact <= 0) {
            $unitsPerArtefact = 1;
        }

        return (float) $lastTask->quantity_made / $unitsPerArtefact;
    }

    private function deductRawMaterials(JobOrderItem $item, float $producedArtefactUnits): void
    {
        $recipeSteps = ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
            ->with('rawMaterials.rawMaterial.orgStock')
            ->get();

        $consumptionByOrgStock = [];

        foreach ($recipeSteps as $recipeStep) {
            foreach ($recipeStep->rawMaterials as $recipeStepRawMaterial) {
                $rawMaterial = $recipeStepRawMaterial->rawMaterial;

                if (!$rawMaterial || !$rawMaterial->org_stock_id || !$rawMaterial->orgStock) {
                    continue;
                }

                $consumed = $producedArtefactUnits * (float) $recipeStepRawMaterial->quantity_per_unit;

                $consumptionByOrgStock[$rawMaterial->org_stock_id] ??= [
                    'orgStock' => $rawMaterial->orgStock,
                    'quantity' => 0.0,
                ];

                $consumptionByOrgStock[$rawMaterial->org_stock_id]['quantity'] += $consumed;
            }
        }

        foreach ($consumptionByOrgStock as $consumption) {
            $orgStock = $consumption['orgStock'];

            $deductionLocationOrgStock = LocationOrgStock::where('org_stock_id', $orgStock->id)
                ->orderByRaw("type = '".LocationStockTypeEnum::PICKING->value."' desc")
                ->orderByDesc('quantity')
                ->first();

            if (!$deductionLocationOrgStock) {
                throw ValidationException::withMessages([
                    'location_id' => __('Raw material :code has no stock location to deduct from', ['code' => $orgStock->code]),
                ]);
            }

            StoreOrgStockMovement::make()->action($orgStock, $deductionLocationOrgStock->location, [
                'quantity' => -$consumption['quantity'],
                'type'     => OrgStockMovementTypeEnum::PRODUCTION,
                'user_id'  => $this->request?->user()?->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(
                    fn ($query) => $query->whereIn('warehouse_id', Warehouse::where('organisation_id', $this->organisation->id)->pluck('id'))
                ),
            ],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(JobOrder $jobOrder, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->initialisationFromProduction($jobOrder->production, $modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->request = $request;
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
