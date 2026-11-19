<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\OrgAction;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Voids a truck made by mistake, with its customs invoices (HELP-4482). Nothing is deleted: the numbers,
 * lines and header stay as issued, marked void with who, when and why, and the invoices and delivery
 * notes are freed for another truck. Customs records are kept, and a number is never given twice.
 */
class VoidPartnerShipment extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(PartnerShipment $partnerShipment, User $user, string $reason): PartnerShipment
    {
        return DB::transaction(function () use ($partnerShipment, $user, $reason) {
            DB::select('select pg_advisory_xact_lock(4482, ?)', [$partnerShipment->org_partner_id]);
            $partnerShipment = PartnerShipment::lockForUpdate()->findOrFail($partnerShipment->id);

            if ($partnerShipment->voided_at) {
                throw ValidationException::withMessages(['void_reason' => __('These customs invoices are already void')]);
            }

            $partnerShipment->update([
                'voided_at'         => now(),
                'voided_by_user_id' => $user->id,
                'void_reason'       => $reason,
                'data'              => [
                    ...$partnerShipment->data,
                    'delivery_notes' => $partnerShipment->data['delivery_notes'] ?? $partnerShipment->deliveryNotes()->orderBy('reference')->pluck('reference')->all(),
                ],
            ]);
            $partnerShipment->deliveryNotes()->detach();
            $partnerShipment->invoices()->detach();

            return $partnerShipment;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["org-admin.{$this->organisation->id}", "supervisor-dispatching.{$request->route('warehouse')->id}"]);
    }

    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, PartnerShipment $partnerShipment, ActionRequest $request): RedirectResponse
    {
        abort_unless($partnerShipment->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id && $partnerShipment->warehouse_id === $warehouse->id, 404);
        $this->initialisation($organisation, $request);
        $this->handle($partnerShipment, $request->user(), $this->validatedData['void_reason']);

        return back();
    }
}
