<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachWebpageToCollections extends OrgAction
{
    public function handle(Collection $collection, array $modelData)
    {
        $webpageIds = Arr::get($modelData, 'webpages', []);

        if (!empty($webpageIds)) {
            $collection->webpageHasCollections()->sync($webpageIds);
            $collection->refresh();
        }

        return true;
    }

    public function rules(): array
    {
        return [
                'webpages'   => ['nullable', 'array'],
                'webpages.*' => ['exists:webpages,id'],
        ];
    }
    public function asController(Collection $collection, ActionRequest $request)
    {
        $this->initialisationFromShop($collection->shop, $request);
        $this->handle($collection, $this->validatedData);
    }
}
