<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Masters\MasterProductResource;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketAttachment;

class GetMasterProductShowcase
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;

    public function handle(MasterAsset $masterAsset): array
    {
        return [
            'images' => $this->getImagesData($masterAsset),
            'main_image'      => $masterAsset->imageSources(),
            'masterProduct' => MasterProductResource::make($masterAsset)->toArray(request()),
            'properties'           => null,  // TODO
            'gpsr'                 => null,  // TODO
            'parts'                 => null,  // TODO

        ];
    }


}
