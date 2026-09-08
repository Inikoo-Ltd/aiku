<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionOutput;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock;
use App\Models\Inventory\Warehouse;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PutAwayFinishedJobOrder extends OrgAction
{
    public function handle(Warehouse $warehouse, JobOrder $jobOrder, string $locationCode): JobOrder
    {
        $location = $warehouse->locations()->where('code', $locationCode)->first();
        if (!$location) {
            throw ValidationException::withMessages(['location_code' => __('No location :code in this warehouse', ['code' => $locationCode])]);
        }
        if ($jobOrder->organisation_id !== $warehouse->organisation_id) {
            throw ValidationException::withMessages(['job_order' => __('Job order does not belong to this warehouse')]);
        }

        return ReceiveJobOrderIntoStock::make()->action($jobOrder, ['location_id' => $location->id]);
    }

    public function rules(): array
    {
        return ['location_code' => ['required', 'string']];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("dispatching.{$this->organisation->id}.edit");
    }

    public function action(Warehouse $warehouse, JobOrder $jobOrder, string $locationCode): JobOrder
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, ['location_code' => $locationCode]);

        return $this->handle($warehouse, $jobOrder, $locationCode);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $jobOrder, $this->validatedData['location_code']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Production received into stock'),
        ]);
    }
}
