<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 16:37:22 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\WithUploadWebImage;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToWebsite extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithUploadWebImage;



    public function header(Website $website, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, 'header', $this->validatedData);
    }

    public function footer(Website $website, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, 'footer', $this->validatedData);
    }

    public function sidebar(Website $website, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, 'sidebar', $this->validatedData);
    }

    public function menu(Website $website, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, 'menu', $this->validatedData);
    }

    public function favicon(Website $website, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, 'favicon', $this->validatedData);
    }

    public function jsonResponse($medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }

}
