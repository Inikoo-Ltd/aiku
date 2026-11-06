<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff say whether the AI matched our product to the right competitor product, and how many units
 * their pack holds when the name does not say.
 */
class ReviewMasterAssetCompetitorProduct extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(MasterAssetCompetitorProduct $masterAssetCompetitorProduct, array $modelData, ?User $user = null): MasterAssetCompetitorProduct
    {
        if (isset($modelData['competitor_units'])) {
            $masterAssetCompetitorProduct->update(['competitor_units' => $modelData['competitor_units']]);
        }

        if (isset($modelData['status'])) {
            $masterAssetCompetitorProduct->update([
                'status'              => $modelData['status'],
                'reviewed_by_user_id' => $user?->id,
                'reviewed_at'         => now(),
            ]);
        }

        return $masterAssetCompetitorProduct;
    }

    public function rules(): array
    {
        return [
            'status'           => ['required_without:competitor_units', Rule::enum(MasterAssetCompetitorProductStatusEnum::class)],
            'competitor_units' => ['required_without:status', 'numeric', 'min:1', 'max:100000'],
        ];
    }

    public function asController(MasterAssetCompetitorProduct $masterAssetCompetitorProduct, ActionRequest $request): MasterAssetCompetitorProduct
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterAssetCompetitorProduct, $this->validatedData, $request->user());
    }

    public function action(MasterAssetCompetitorProduct $masterAssetCompetitorProduct, array $modelData, ?User $user = null): MasterAssetCompetitorProduct
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterAssetCompetitorProduct->masterAsset->group, $modelData);

        return $this->handle($masterAssetCompetitorProduct, $this->validatedData, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
