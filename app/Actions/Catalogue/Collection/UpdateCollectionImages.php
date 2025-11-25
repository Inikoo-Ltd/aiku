<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Masters\MasterCollection\UpdateMasterCollectionImages;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Collection;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateCollectionImages extends OrgAction
{
    use WithActionUpdate;

    public function handle(Collection $collection, array $modelData, bool $updateDependants = false): Collection
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
                $collection->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $collection->images()
                            ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                            ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $collection->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        $this->update($collection, $modelData);

        return $collection;
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
        ];
    }

    public function asController(Collection $collection, ActionRequest $request): void
    {
        $this->initialisationFromShop($collection->shop, $request);

        $this->handle($collection, $this->validatedData, true);
    }
}
