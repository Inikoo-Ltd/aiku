<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\Catalogue\Product\CountOpenOrdersAffectedByUnitsChange;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class GetMasterAssetsOpenOrdersAffectedByUnitsChange extends OrgAction
{
    use WithMastersEditAuthorisation;

    /**
     * @param array<int, int> $masterAssetIds
     *
     * @return array<int, int>
     */
    public function handle(array $masterAssetIds): array
    {
        return MasterAsset::whereIn('id', $masterAssetIds)
            ->where('group_id', $this->group->id)
            ->get()
            ->mapWithKeys(fn (MasterAsset $masterAsset) => [$masterAsset->id => CountOpenOrdersAffectedByUnitsChange::run($masterAsset)])
            ->all();
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->validatedData['ids']);
    }

    public function jsonResponse(array $openOrdersByMasterAsset): array
    {
        return $openOrdersByMasterAsset;
    }
}
