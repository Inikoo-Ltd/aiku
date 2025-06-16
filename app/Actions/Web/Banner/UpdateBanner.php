<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:15 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Http\Resources\Web\BannerResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class UpdateBanner extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    public function handle(Banner $banner, array $modelData): Banner
    {
        $this->update($banner, $modelData, ['data']);

        BannerRecordSearch::dispatch($banner);

        return $banner;
    }


    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required','string','max:255']
        ];
    }


    public function asController(Shop $shop, Website $website, Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($banner, $this->validatedData);
    }

    public function action(Banner $banner, $modelData): Banner
    {
        $this->asAction = true;
        $this->initialisationFromGroup($banner->group, $modelData);

        return $this->handle($banner, $this->validatedData);
    }

    public function jsonResponse(Banner $banner): BannerResource
    {
        return new BannerResource($banner);
    }
}
