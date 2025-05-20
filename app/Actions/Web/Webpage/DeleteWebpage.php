<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\Search\WebpageRecordSearch;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWebpage extends OrgAction
{
    use AsAction;
    use WithAttributes;

    // TODO: Add authorisation

    public function handle(Webpage $webpage): Webpage
    {
        $webpage->delete();
        WebpageRecordSearch::run($webpage);
        return $webpage;
    }

    public function action(Webpage $webpage): Webpage
    {
        return $this->handle($webpage);
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;

        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }

    public function inShop(Shop $shop, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($webpage);
    }

    public function htmlResponse(Webpage $webpage): RedirectResponse
    {
        return redirect()->route(
            'grp.org.shops.show.web.webpages.index',
            [
                'organisation' => $webpage->organisation->slug,
                'shop' => $webpage->shop->slug,
                'website' => $webpage->website->slug,
                'webpage' => $webpage->slug
            ]
        );
    }
}
