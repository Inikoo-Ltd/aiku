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
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Publishing a draft makes it the version suppliers read and accept, and retires the one before. From then on its
 * text is frozen. When it asks for a new acceptance, suppliers who accepted an earlier version become Outdated;
 * otherwise their acceptance still counts and they are only told of the change.
 */
class PublishSupplierAgreementVersion extends OrgAction
{
    use WithSupplierAgreementAuthorisation;

    public function handle(SupplierAgreementVersion $version, ?User $user = null): SupplierAgreementVersion
    {
        if ($version->state !== SupplierAgreementVersionStateEnum::DRAFT) {
            throw ValidationException::withMessages(['version' => __('Only a draft can be published.')]);
        }

        if (trim($version->body) === '') {
            throw ValidationException::withMessages(['body' => __('The agreement text is empty.')]);
        }

        $isFirst = !SupplierAgreementVersion::where('group_id', $version->group_id)->released()->exists();
        if (!$isFirst && !$version->requires_reacceptance && blank($version->change_summary)) {
            throw ValidationException::withMessages(['change_summary' => __('Say what changed: suppliers are shown it instead of accepting again.')]);
        }

        DB::transaction(function () use ($version, $user, $isFirst) {
            SupplierAgreementVersion::where('group_id', $version->group_id)
                ->where('state', SupplierAgreementVersionStateEnum::PUBLISHED)
                ->lockForUpdate()
                ->get()
                ->each(fn (SupplierAgreementVersion $published) => $published->update([
                    'state'      => SupplierAgreementVersionStateEnum::RETIRED,
                    'retired_at' => now(),
                ]));

            $version->update([
                'state'                 => SupplierAgreementVersionStateEnum::PUBLISHED,
                'published_at'          => now(),
                'published_by_user_id'  => $user?->id,
                'requires_reacceptance' => $isFirst || $version->requires_reacceptance,
            ]);
        });

        return $version;
    }

    public function asController(SupplierAgreementVersion $supplierAgreementVersion, ActionRequest $request): SupplierAgreementVersion
    {
        $this->initialisationFromGroup($supplierAgreementVersion->group, $request);

        return $this->handle($supplierAgreementVersion, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function action(SupplierAgreementVersion $version, ?User $user = null): SupplierAgreementVersion
    {
        $this->asAction = true;
        $this->initialisationFromGroup($version->group, []);

        return $this->handle($version, $user);
    }
}
