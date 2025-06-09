<?php
/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-16h-42m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachCollectionsToWebpage extends OrgAction
{
    public function handle(Webpage $webpage, array $modelData)
    {
        $collectionIds = Arr::get($modelData, 'collections', []);

        foreach ($collectionIds as $collectionId) {
                $collection = Collection::find($collectionId);
                if ($collection) {
                    AttachCollectionToWebpage::make()->action($webpage, $collection);
                }
            
        }

        return true;
    }

    public function rules(): array
    {
        return [
                'collections'   => ['nullable', 'array'],
                'collections.*' => ['exists:collections,id'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->initialisationFromShop($webpage->shop, $request);
        $this->handle($webpage, $this->validatedData);
    }
}
