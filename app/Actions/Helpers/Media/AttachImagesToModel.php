<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Goods\TradeUnit\UpdateTradeUnitImages;
use App\Actions\Masters\MasterCollection\UploadImagesToMasterCollection;
use App\Actions\Masters\MasterProductCategory\UploadImagesToMasterProductCategory;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachImagesToModel extends OrgAction
{
    use WithAttachMediaToModel;

    /**
     * @return array<Media>
     */
    public function handle(TradeUnit|MasterCollection|MasterProductCategory $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = [];

        foreach (Arr::get($modelData, 'images') as $mediaId) {
            $media = Media::find($mediaId);

            if (!$media || $model->images()->where('media.id', $media->id)->exists()) {
                continue;
            }

            $this->attachMediaToModel($model, $media, $scope);

            $model->images()->updateExistingPivot($media->id, [
                'caption' => $model->name,
            ]);

            $medias[] = $media;
        }

        if ($updateDependants && $medias) {
            $this->updateDependants($model, $medias, $scope);
        }

        return $medias;
    }

    /**
     * @param  array<Media>  $medias
     */
    public function updateDependants(TradeUnit|MasterCollection|MasterProductCategory $model, array $medias, string $scope): void
    {
        if ($model instanceof MasterProductCategory) {
            UploadImagesToMasterProductCategory::make()->updateDependants($model, $medias, $scope);
        } elseif ($model instanceof MasterCollection) {
            UploadImagesToMasterCollection::make()->updateDependants($model, $medias, $scope);
        } elseif ($model instanceof TradeUnit) {
            UpdateTradeUnitImages::make()->updateDependencies($model);
        }
    }

    public function rules(): array
    {
        return [
            'images'   => ['required', 'array'],
            'images.*' => ['integer', 'exists:media,id'],
        ];
    }

    public function inMasterProductCategory(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, 'image', $this->validatedData, true);
    }

    public function inMasterCollection(MasterCollection $masterCollection, ActionRequest $request): void
    {
        $this->initialisationFromGroup($masterCollection->group, $request);

        $this->handle($masterCollection, 'image', $this->validatedData, true);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, 'image', $this->validatedData, true);
    }
}
