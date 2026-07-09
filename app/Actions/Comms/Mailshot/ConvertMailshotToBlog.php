<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu Jul 09 2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\BeeFreeSDK\BeefreeConvertEmailJsonToPageJson;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ConvertMailshotToBlog extends OrgAction
{
    public function handle(Mailshot $mailshot): Webpage
    {
        $pageJson = BeefreeConvertEmailJsonToPageJson::make()->handle($mailshot->organisation, $mailshot);

        // For testing Blogspot with product blogspot
        $product = Product::where('shop_id', $mailshot->shop_id)->inRandomOrder()->first();

        $webpage = StoreWebpage::make()->action(
            $mailshot->shop->website,
            [
                'title'        => $mailshot->subject,
                'code'         => $mailshot->slug,
                'type'         => WebpageTypeEnum::BLOG,
                'sub_type'     => WebpageSubTypeEnum::MAILSHOT,
                'layout_style' => 'Beefree',
            ]
        );

        $layout = array_merge(['web_blocks' => []], $pageJson);

        $webpage->unpublishedSnapshot->update([
            'layout'   => $layout,
            'checksum' => hash('sha256', json_encode($layout)),
        ]);

        return $webpage;
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot);
    }

    public function htmlResponse(Webpage $webpage): RedirectResponse
    {
        return redirect()->route('grp.org.shops.show.web.blogs.show', [
            'organisation' => $this->organisation->slug,
            'shop'         => $this->shop->slug,
            'website'      => $webpage->website->slug,
            'webpage'      => $webpage->slug,
        ]);
    }
}
