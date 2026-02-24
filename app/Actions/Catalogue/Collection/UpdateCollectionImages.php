<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Concerns\CanUpdateImages;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateCollectionImages extends OrgAction
{
    use WithActionUpdate;
    use CanUpdateImages;

    public function handle(Collection $collection, array $modelData): Collection
    {
        $this->updateImages($collection, $modelData);

        $collection = $this->update($collection, $modelData);

        $changes = Arr::except($collection->getChanges(), ['updated_at']);

        if (Arr::has($changes, 'image_id')) {
            UpdateCollectionWebImages::run($collection);
        }

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

        $this->handle($collection, $this->validatedData);
    }
}
