<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 16:37:22 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Web\WithUploadWebImage;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToWebsite extends OrgAction
{
    use WithUploadWebImage;

    public function header(Website $website, ActionRequest $request): Collection
    {
        $this->scope = $website->organisation;
        $this->initialisation($this->scope, $request);

        return $this->handle($website, 'header', $this->validatedData);
    }

    public function footer(Website $website, ActionRequest $request): Collection
    {
        $this->scope = $website->organisation;
        $this->initialisation($this->scope, $request);

        return $this->handle($website, 'footer', $this->validatedData);
    }

    public function sidebar(Website $website, ActionRequest $request): Collection
    {
        $this->scope = $website->organisation;
        $this->initialisation($this->scope, $request);

        return $this->handle($website, 'sidebar', $this->validatedData);
    }

    public function favicon(Website $website, ActionRequest $request): Collection
    {
        $this->scope = $website->shop;
        $this->initialisationFromShop($this->scope, $request);

        return $this->handle($website, 'favicon', $this->validatedData);
    }

    public function jsonResponse($medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }
}
