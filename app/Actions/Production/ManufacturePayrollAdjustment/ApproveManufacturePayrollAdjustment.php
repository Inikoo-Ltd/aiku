<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 10:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufacturePayrollAdjustment;

use App\Actions\OrgAction;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The accountant has seen a change the production manager made to a locked pay week, which clears its highlight.
 */
class ApproveManufacturePayrollAdjustment extends OrgAction
{
    public function handle(ManufacturePayrollAdjustment $adjustment, User $user): ManufacturePayrollAdjustment
    {
        if ($adjustment->changed_by_user_id == $user->id) {
            throw ValidationException::withMessages(['approval' => __('A change cannot be approved by the person who made it')]);
        }
        if ($adjustment->isAwaitingApproval()) {
            $adjustment->update([
                'approved_by_user_id' => $user->id,
                'approved_at'         => now(),
            ]);
        }

        return $adjustment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return ManufacturePayrollAdjustment::isAccountant($request->user(), $this->production);
    }

    public function action(ManufacturePayrollAdjustment $adjustment, User $user): ManufacturePayrollAdjustment
    {
        $this->asAction = true;
        $this->initialisationFromProduction($adjustment->production, []);

        return $this->handle($adjustment, $user);
    }

    public function asController(ManufacturePayrollAdjustment $manufacturePayrollAdjustment, ActionRequest $request): ManufacturePayrollAdjustment
    {
        $this->initialisationFromProduction($manufacturePayrollAdjustment->production, $request);

        return $this->handle($manufacturePayrollAdjustment, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
