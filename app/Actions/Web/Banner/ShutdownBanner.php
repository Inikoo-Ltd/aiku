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
use App\Enums\Web\Banner\BannerStateEnum;
use App\Http\Resources\Web\BannerResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class ShutdownBanner extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    public function handle(Banner $banner, array $modelData): Banner
    {
        data_set($modelData, 'state', BannerStateEnum::SWITCH_OFF);
        data_set($modelData, 'switch_off_at', now());
        data_set($modelData, 'live_at', null);

        $this->update($banner, $modelData, ['data']);

        BannerRecordSearch::dispatch($banner);

        return $banner;
    }

    public function asController(Shop $shop, Website $website, Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($banner, $this->validatedData);
    }

    public function action(Banner $banner, $modelData): Banner
    {

        $this->initialisationFromGroup($banner->group, $modelData);

        return $this->handle($banner, $this->validatedData);
    }

    public function jsonResponse(Banner $banner): BannerResource
    {
        return new BannerResource($banner);
    }
}
