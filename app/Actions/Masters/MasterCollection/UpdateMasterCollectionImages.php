<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterCollectionImages extends GrpAction
{
    use WithActionUpdate;

    public function handle(MasterCollection $masterCollection, array $modelData, bool $updateDependants = false)
    {
        $imageTypeMapping = [
            'image_id' => 'main',
        ];

        $imageKeys = collect($imageTypeMapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];

            if ($mediaId === null) {
                $masterCollection->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $masterCollection->images()
                            ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                            ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $masterCollection->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        $this->update($masterCollection, $modelData);

        if ($updateDependants) {
            $this->updateDependants($masterCollection, $modelData);
        }

        return $masterCollection;
    }

    public function updateDependants(MasterCollection $seedMasterCollection, array $modelData): void
    {
        // Master Collections don't have dependants to update
        // This method is kept for consistency with the trait interface
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

        $this->handle($masterCollection, $this->validatedData, false);
    }
}
