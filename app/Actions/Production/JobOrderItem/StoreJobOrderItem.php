<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 12:09:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrderItem\JobOrderItemStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreJobOrderItem extends OrgAction
{
    public function handle(JobOrder $jobOrder, array $modelData): JobOrderItem
    {
        if (Arr::get($modelData, 'notes') === null) {
            data_set($modelData, 'notes', '');
        }

        if (Arr::exists($modelData, 'state') and Arr::get($modelData, 'state') != JobOrderItemStateEnum::IN_PROCESS) {
            if (! Arr::get($modelData, 'reference')) {
                data_set(
                    $modelData,
                    'reference',
                    Str::random(10) // TODO: make a reference generator for Job Order Item
                );
            }
        }

        data_set($modelData, 'group_id', $jobOrder->group_id);
        data_set($modelData, 'organisation_id', $jobOrder->organisation_id);

        /** @var JobOrderItem $jobOrderItem */
        $jobOrderItem = $jobOrder->jobOrderItems()->create($modelData);

        if ($jobOrderItem->reference) {
            $jobOrderItem->generateSlug();
            $jobOrderItem->save();
        }
        $jobOrderItem->refresh();

        // if ($this->parent instanceof PalletDelivery) {
        // }
        // FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
        // FulfilmentHydratePallets::dispatch($fulfilmentCustomer->fulfilment);
        // OrganisationHydratePallets::dispatch($fulfilmentCustomer->organisation);
        // WarehouseHydratePallets::dispatch($pallet->warehouse);
        // if ($pallet->location && $pallet->location->warehouseArea) {
        //     WarehouseAreaHydratePallets::dispatch($pallet->location->warehouseArea);
        // }
        // PalletRecordSearch::dispatch($pallet);

        return $jobOrderItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("productions-view.{$this->organisation->id}");
    }

    public function rules(): array
    {
        return [
            'artefact_id' => ['required', 'integer', 'exists:artefacts,id'],
            'status' => [
                'sometimes',
                Rule::enum(JobOrderItemStatusEnum::class),
            ],
            'state' => [
                'sometimes',
                Rule::enum(JobOrderItemStateEnum::class),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'quantity' => ['required', 'integer', 'min:1'],
            'created_at' => ['sometimes', 'date'],
            'received_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function asController(Organisation $organisation, JobOrder $jobOrder, ActionRequest $request): JobOrderItem
    {
        $this->initialisation($organisation, $request);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function action(JobOrder $jobOrder, array $modelData): JobOrderItem
    {
        $this->asAction = true;
        $this->initialisation($jobOrder->organisation, $modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }

    // public function htmlResponse(Pallet $pallet, ActionRequest $request): RedirectResponse
    // {
    //     if ($this->parent instanceof PalletDelivery) {
    //         return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
    //             'organisation'       => $pallet->organisation->slug,
    //             'fulfilment'         => $pallet->fulfilment->slug,
    //             'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
    //             'palletDelivery'     => $this->parent->slug
    //         ]);
    //     }

    //     return Redirect::route(
    //         'grp.org.fulfilments.show.crm.customers.show',
    //         [
    //             'organisation'       => $pallet->organisation->slug,
    //             'fulfilment'         => $pallet->fulfilment->slug,
    //             'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
    //         ]
    //     );
    // }
}
