<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\Dispatching\DeliveryNote\UpdateState\DispatchDeliveryNote;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Dispatches every delivery note on a truck when it leaves (HELP-4482), through the same
 * DispatchDeliveryNote as one note at a time. Only once its customs invoices are made, and only
 * finalised notes: a note cancelled or changed since (or one that cannot go, e.g. an insert not
 * printed) is named and the consolidation stays undispatched until it is sorted out.
 */
class DispatchPartnerShipment extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(PartnerShipment $partnerShipment): PartnerShipment
    {
        $partnerShipment = $partnerShipment->refresh();
        if ($partnerShipment->voided_at) {
            throw ValidationException::withMessages(['dispatch' => __('This consolidation is void')]);
        }
        if (!$partnerShipment->customs_invoiced_at) {
            throw ValidationException::withMessages(['dispatch' => __('Make the customs invoices first')]);
        }
        if ($partnerShipment->dispatched_at) {
            return $partnerShipment;
        }

        $failed = [];
        $partnerShipment->deliveryNotes()
            ->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::DISPATCHED)
            ->orderBy('delivery_notes.id')
            ->get()
            ->each(function (DeliveryNote $deliveryNote) use (&$failed) {
                if ($deliveryNote->state !== DeliveryNoteStateEnum::FINALISED) {
                    $failed[] = $deliveryNote->reference.': '.(DeliveryNoteStateEnum::labels()[$deliveryNote->state->value] ?? $deliveryNote->state->value);

                    return;
                }
                try {
                    DispatchDeliveryNote::make()->action($deliveryNote);
                } catch (HttpException $exception) {
                    $failed[] = $deliveryNote->reference.': '.$exception->getMessage();
                } catch (ValidationException $exception) {
                    $failed[] = $deliveryNote->reference.': '.collect($exception->errors())->flatten()->implode(' ');
                }
            });

        if ($failed) {
            throw ValidationException::withMessages(['dispatch' => __('Not dispatched: :notes', ['notes' => implode('; ', $failed)])]);
        }

        $partnerShipment->update(['dispatched_at' => now()]);

        return $partnerShipment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($this->organisation));
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, PartnerShipment $partnerShipment, ActionRequest $request): RedirectResponse
    {
        abort_unless($partnerShipment->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id && $partnerShipment->warehouse_id === $warehouse->id, 404);
        $this->initialisation($organisation, $request);
        $this->handle($partnerShipment);

        return back();
    }
}
