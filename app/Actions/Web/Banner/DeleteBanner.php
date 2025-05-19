<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Models\Catalogue\Shop;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteBanner extends OrgAction
{
    use WithWebEditAuthorisation;
    use AsAction;
    use WithAttributes;

    public function handle(Banner $banner): Banner
    {
        $banner->delete();
        BannerRecordSearch::run($banner);
        return $banner;
    }

    public function action(Banner $banner): Banner
    {
        return $this->handle($banner);
    }

    public function asController(Shop $shop, Website $website, Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($banner);
    }

    public function htmlResponse(Banner $banner): RedirectResponse
    {
        return redirect()->route(
            'grp.org.shops.show.web.banners.index',
            [
                'organisation' => $banner->organisation->slug,
                'shop' => $banner->shop->slug,
                'website' => $banner->website->slug,
                'banner' => $banner->slug
            ]
        );
    }
}
