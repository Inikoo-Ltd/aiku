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
use App\Models\Inventory\Warehouse;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PutAwayFinishedJobOrder extends OrgAction
{
    /**
     * One walk to one location, carrying every job order's share of it.
     *
     * @param  array<int, int>  $jobOrderIds
     * @return array<int, JobOrder>
     */
    public function handle(Warehouse $warehouse, array $jobOrderIds, string $locationCode): array
    {
        $location = $warehouse->locations()->where('code', $locationCode)->first();
        if (!$location) {
            throw ValidationException::withMessages(['location_code' => __('No location :code in this warehouse', ['code' => $locationCode])]);
        }

        $jobOrders = JobOrder::whereIn('id', $jobOrderIds)->get();

        return $jobOrders->map(function (JobOrder $jobOrder) use ($warehouse, $location) {
            if ($jobOrder->organisation_id !== $warehouse->organisation_id) {
                throw ValidationException::withMessages(['job_order' => __('Job order does not belong to this warehouse')]);
            }

            return ReceiveJobOrderIntoStock::make()->action($jobOrder, [
                'location_id' => $location->id,
                'allocations' => $this->allocationsFor($jobOrder, $location),
            ]);
        })->all();
    }

    /**
     * What this job order owes this location, minus whatever earlier walks already put away.
     *
     * @return array<int, float>
     */
    private function allocationsFor(JobOrder $jobOrder, Location $location): array
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
                : $allocation['location_id'] === null;

            if ($quantity > 0 && $goesHere) {
                $allocations[$item->id] = ($allocations[$item->id] ?? 0) + $quantity;
            }
        }

        return $allocations;
    }

    public function rules(): array
    {
        return [
            'location_code'   => ['required', 'string'],
            'job_order_ids'   => ['required', 'array', 'min:1'],
            'job_order_ids.*' => ['integer'],
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
     * @return array<int, JobOrder>
     */
    public function action(Warehouse $warehouse, array $jobOrderIds, string $locationCode): array
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, ['location_code' => $locationCode, 'job_order_ids' => $jobOrderIds]);

        return $this->handle($warehouse, $jobOrderIds, $locationCode);
    }

    /**
     * @return array<int, JobOrder>
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData['job_order_ids'], $this->validatedData['location_code']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Production received into stock'),
        ]);
    }
}
