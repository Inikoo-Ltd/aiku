<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
use App\Models\Masters\MasterAssetPriceTip;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff turn a price tip down, saying why. The product gets no new tip for a while.
 */
class DismissMasterAssetPriceTip extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(MasterAssetPriceTip $masterAssetPriceTip, array $modelData, ?User $user = null): MasterAssetPriceTip
    {
        if ($masterAssetPriceTip->status !== MasterAssetPriceTipStatusEnum::OPEN) {
            throw ValidationException::withMessages(['status' => __('This price tip is no longer open')]);
        }

        $masterAssetPriceTip->update([
            'status'               => MasterAssetPriceTipStatusEnum::DISMISSED,
            'dismissed_reason'     => $modelData['dismissed_reason'],
            'dismissed_by_user_id' => $user?->id,
            'dismissed_at'         => now(),
        ]);

        return $masterAssetPriceTip;
    }

    public function rules(): array
    {
        return [
            'dismissed_reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function asController(MasterAssetPriceTip $masterAssetPriceTip, ActionRequest $request): MasterAssetPriceTip
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterAssetPriceTip, $this->validatedData, $request->user());
    }

    public function action(MasterAssetPriceTip $masterAssetPriceTip, array $modelData, ?User $user = null): MasterAssetPriceTip
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterAssetPriceTip->masterAsset->group, $modelData);

        return $this->handle($masterAssetPriceTip, $this->validatedData, $user);
    }
}
