<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionOutput;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation;
use App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PutAwayFinishedJobOrder extends OrgAction
{
    /**
     * A partner bay takes the whole trip; stock goes item by item, each to its own location.
     *
     * @param  array<int, int>  $jobOrderIds
     * @param  string|array<int, string>  $locations  one code for everything, or job order item id => code
     * @return array<int, JobOrder>
     */
    public function handle(Warehouse $warehouse, array $jobOrderIds, string|array $locations, bool $allowNewLocations = false): array
    {
        $itemIdsByCode = is_string($locations)
            ? [$locations => null]
            : collect($locations)->map(fn ($code, $itemId) => ['code' => strtoupper(trim($code)), 'item_id' => (int) $itemId])
                ->groupBy('code')->map(fn ($rows) => $rows->pluck('item_id')->all())->all();

        $locationsByCode = [];
        foreach (array_keys($itemIdsByCode) as $code) {
            $locationsByCode[$code] = $warehouse->locations()->where('code', $code)->first()
                ?? throw ValidationException::withMessages(['location_code' => __('No location :code in this warehouse', ['code' => $code])]);
        }

        $jobOrders = JobOrder::whereIn('id', $jobOrderIds)->get();

        foreach ($jobOrders as $jobOrder) {
            if ($jobOrder->organisation_id !== $warehouse->organisation_id) {
                throw ValidationException::withMessages(['job_order' => __('Job order does not belong to this warehouse')]);
            }
        }

        return DB::transaction(function () use ($jobOrders, $itemIdsByCode, $locationsByCode, $allowNewLocations) {
            $received = [];

            foreach ($itemIdsByCode as $code => $itemIds) {
                $location = $locationsByCode[$code];

                foreach ($jobOrders as $jobOrder) {
                    $allocations = $this->allocationsFor($jobOrder, $location, $itemIds, $allowNewLocations);
                    if (!$allocations) {
                        continue;
                    }

                    $received[] = ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), [
                        'location_id' => $location->id,
                        'allocations' => $allocations,
                        'user_id'     => $this->userId,
                    ]);
                }
            }

            if (!$received) {
                throw ValidationException::withMessages(['location_id' => __('Nothing from this job order goes to :code', ['code' => implode(', ', array_keys($itemIdsByCode))])]);
            }

            return $received;
        });
    }

    private ?int $userId = null;

    /**
     * What this job order owes this location, minus whatever earlier walks already put away.
     *
     * @param  array<int, int>|null  $itemIds
     * @return array<int, float>
     */
    private function allocationsFor(JobOrder $jobOrder, Location $location, ?array $itemIds, bool $allowNewLocations): array
    {
        $partnerLocationIds = $jobOrder->organisation->orgPartners()->whereNotNull('goods_out_location_id')->pluck('goods_out_location_id');
        $isPartnerBay       = $partnerLocationIds->contains($location->id);

        $allocations    = [];
        $alreadyPutAway = [];

        foreach (GetJobOrderDestinationAllocation::run($jobOrder) as $allocation) {
            $item = $allocation['item'];

            $alreadyPutAway[$item->id] ??= (float) $item->quantity_received;
            $putAway                     = min($allocation['quantity'], $alreadyPutAway[$item->id]);
            $alreadyPutAway[$item->id]  -= $putAway;
            $quantity                    = $allocation['quantity'] - $putAway;

            $goesHere = $isPartnerBay
                ? $allocation['location_id'] === $location->id
                : $allocation['location_id'] === null && ($itemIds === null || in_array($item->id, $itemIds, true));

            if ($quantity <= 0 || !$goesHere) {
                continue;
            }

            $orgStockId = $item->artefact->org_stock_id;
            if (!$isPartnerBay && !$allowNewLocations && $orgStockId
                && !LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $orgStockId)->exists()) {
                throw ValidationException::withMessages(['new_location' => __(':stock is not kept in :code yet', ['stock' => $item->artefact->code, 'code' => $location->code])]);
            }

            $allocations[$item->id] = ($allocations[$item->id] ?? 0) + $quantity;
        }

        return $allocations;
    }

    public function rules(): array
    {
        return [
            'location_code'       => ['required_without:item_locations', 'nullable', 'string'],
            'item_locations'      => ['required_without:location_code', 'nullable', 'array', 'min:1'],
            'item_locations.*'    => ['required', 'string'],
            'allow_new_locations' => ['sometimes', 'boolean'],
            'job_order_ids'       => ['required', 'array', 'min:1'],
            'job_order_ids.*'     => ['integer', Rule::exists('job_orders', 'id')->where('organisation_id', $this->organisation->id)],
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
     * @param  array<int, int>  $jobOrderIds
     * @param  string|array<int, string>  $locations
     * @return array<int, JobOrder>
     */
    public function action(Warehouse $warehouse, array $jobOrderIds, string|array $locations, bool $allowNewLocations = false): array
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, [
            'job_order_ids'  => $jobOrderIds,
            ...(is_string($locations) ? ['location_code' => $locations] : ['item_locations' => $locations]),
        ]);

        return $this->handle($warehouse, $jobOrderIds, $locations, $allowNewLocations);
    }

    /**
     * @return array<int, JobOrder>
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): array
    {
        $this->userId = $request->user()->id;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(
            $warehouse,
            $this->validatedData['job_order_ids'],
            $this->validatedData['item_locations'] ?? $this->validatedData['location_code'],
            (bool) ($this->validatedData['allow_new_locations'] ?? false),
        );
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Production received into stock'),
        ]);
    }
}
