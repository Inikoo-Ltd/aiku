<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\UpdateCollectionImages;
use App\Actions\Concerns\CanUpdateImages;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterCollectionImages extends GrpAction
{
    use WithActionUpdate;
    use CanUpdateImages;

    public function handle(MasterCollection $masterCollection, array $modelData, bool $updateDependants = false): MasterCollection
    {
        $this->updateImages($masterCollection, $modelData);

        $this->update($masterCollection, $modelData);

        $changes = Arr::except($masterCollection->getChanges(), ['updated_at']);

        if (Arr::has($changes, 'image_id')) {
            UpdateMasterCollectionWebImages::run($masterCollection);
        }

        if ($updateDependants) {
            $this->updateDependants($masterCollection, $modelData);
        }

        return $masterCollection;
    }

    public function updateDependants(MasterCollection $seedMasterCollection, array $modelData): void
    {
        foreach ($seedMasterCollection->childrenCollections as $collection) {
            UpdateCollectionImages::run($collection, $modelData);
        }
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
        ];
    }


    public function asController(MasterCollection $masterCollection, ActionRequest $request): void
    {
        $this->initialisation($masterCollection->group, $request);

        $this->handle($masterCollection, $this->validatedData, true);
    }
}
