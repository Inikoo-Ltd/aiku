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

        $fieldValue = [
            'builderType' => "beefree",
            'beefree' => [
                'json' => $pageJson,
                'html' => '',
            ]
        ];

        $webpage = StoreWebpage::make()->action(
            $mailshot->shop->website,
            [
                'title'        => $mailshot->subject,
                'code'         => $mailshot->slug,
                'url'          => $mailshot->slug,
                'model_type'    => class_basename(Mailshot::class),
                'model_id'      => $mailshot->id,
                'type'         => WebpageTypeEnum::BLOG,
                'sub_type'     => WebpageSubTypeEnum::MAILSHOT,
                'layout_style' => 'Beefree',
                'fieldValue' =>  $fieldValue,
            ]
        );

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
