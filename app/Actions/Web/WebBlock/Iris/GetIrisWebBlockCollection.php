<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 12:28:40 Central Indonesia Time, Beach Office, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Http\Resources\Web\WebBlockProductCategoryDescriptionResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Arr;

class GetIrisWebBlockCollection
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    { 
            $resource = WebBlockProductCategoryDescriptionResource::make($webpage->model)->toArray(request());
            data_set($webBlock, 'web_block.layout.data.fieldValue.collection', $resource);

            return [
                'type' => $webBlock['type'],
                'structure' => Arr::get(
                    $webBlock,
                    'web_block.layout.data.fieldValue',
                    []
                ),
            ];
    }
}
