<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalSummary;
use App\Models\SysAdmin\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * How the production time card on the supplier pages is measured: the date the clock starts from, the target in days
 * and how many orders with both dates a supplier needs to be ranked, and after how many days an unanswered material
 * planning note goes to whom (EscalateOverdueSupplierPlanningNotes). One setting for every supplier of the group.
 */
class UpdateSupplierPortalSettings extends OrgAction
{
    /**
     * @param  array{start: string, target_days: int, minimum_orders: int, planning_days?: int, planning_user_ids?: array<int, int>}  $modelData
     */
    public function handle(Group $group, array $modelData): Group
    {
        $settings = $group->settings;
        data_set($settings, 'supplier_portal.cho', [
            'start'          => $modelData['start'],
            'target_days'    => (int) $modelData['target_days'],
            'minimum_orders' => (int) $modelData['minimum_orders'],
        ]);
        if (array_key_exists('planning_days', $modelData)) {
            data_set($settings, 'supplier_portal.planning_escalation', [
                'days'     => (int) $modelData['planning_days'],
                'user_ids' => array_values(array_unique(array_map('intval', $modelData['planning_user_ids'] ?? []))),
            ]);
        }
        $group->update(['settings' => $settings]);

        return $group;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'start'               => ['required', Rule::in(GetSupplierPortalSummary::CHO_STARTS)],
            'target_days'         => ['required', 'integer', 'min:1', 'max:365'],
            'minimum_orders'      => ['required', 'integer', 'min:1', 'max:100'],
            'planning_days'       => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
            'planning_user_ids'   => ['sometimes', 'array', 'max:20'],
            'planning_user_ids.*' => ['integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)->where('status', true)],
        ];
    }

    public function asController(ActionRequest $request): Group
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
