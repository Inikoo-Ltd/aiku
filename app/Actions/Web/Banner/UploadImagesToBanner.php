<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:15 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\OrgAction;
use App\Actions\Web\WithUploadWebImage;
use App\Models\Web\Banner;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToBanner extends OrgAction
{
    use WithUploadWebImage;

    // Todo WithWebEditAuthorisation here

    public function asController(Banner $banner, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($banner->shop, $request);

        $medias = $this->handle($banner->group, 'banner', $this->validatedData);

        if ($medias->isNotEmpty()) {
            $firstMedia = $medias->first();

            $currentData = is_array($banner->data) ? $banner->data : [];
            $currentData['unpublished_image_id'] = $firstMedia->id;

            $banner->image_id = $firstMedia->id;
            $banner->data = $currentData;
            $banner->save();
        }

        return $medias;
    }
}
