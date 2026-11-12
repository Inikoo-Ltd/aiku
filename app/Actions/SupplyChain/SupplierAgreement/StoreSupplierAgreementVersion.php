<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum;
use App\Models\SupplyChain\SupplierAgreementVersion;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A new draft of the Supplier Agreement, numbered after the last version. Only one draft at a time: it starts from
 * the text in force, so a change is always made on top of what suppliers accepted.
 */
class StoreSupplierAgreementVersion extends OrgAction
{
    use WithSupplierAgreementAuthorisation;

    /**
     * @param  array{title?: string, body?: string, change_summary?: string|null, requires_reacceptance?: bool}  $modelData
     */
    public function handle(Group $group, array $modelData, ?User $user = null): SupplierAgreementVersion
    {
        if (SupplierAgreementVersion::where('group_id', $group->id)->where('state', SupplierAgreementVersionStateEnum::DRAFT)->exists()) {
            throw ValidationException::withMessages(['version' => __('There is already a draft. Change it or publish it first.')]);
        }

        $current = GetSupplierAgreementStatus::make()->currentVersion($group);

        return SupplierAgreementVersion::create([
            'group_id'              => $group->id,
            'version'               => (int) SupplierAgreementVersion::where('group_id', $group->id)->max('version') + 1,
            'title'                 => $modelData['title'] ?? $current?->title ?? __('Supplier Agreement'),
            'body'                  => $modelData['body'] ?? $current?->body ?? '',
            'change_summary'        => $modelData['change_summary'] ?? null,
            'requires_reacceptance' => $modelData['requires_reacceptance'] ?? true,
            'state'                 => SupplierAgreementVersionStateEnum::DRAFT,
            'created_by_user_id'    => $user?->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'title'                 => ['sometimes', 'string', 'max:255'],
            'body'                  => ['sometimes', 'string', 'max:100000'],
            'change_summary'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'requires_reacceptance' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): SupplierAgreementVersion
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $this->validatedData, $request->user());
    }

    public function htmlResponse(SupplierAgreementVersion $version): RedirectResponse
    {
        return redirect()->route('grp.supply-chain.supplier_agreement.show', ['supplierAgreementVersion' => $version->id]);
    }

    public function action(Group $group, array $modelData, ?User $user = null): SupplierAgreementVersion
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData, $user);
    }
}
