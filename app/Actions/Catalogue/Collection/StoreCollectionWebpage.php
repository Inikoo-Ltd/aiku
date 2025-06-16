<?php

/*
 * author Arya Permana - Kirin
 * created on 07-10-2024-14h-21m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Redirect;

class StoreCollectionWebpage extends OrgAction
{
    public function handle(Collection $collection): Webpage
    {
        $webpageData = [
            'code'  => $collection->code,
            'title'  => $collection->name,
            'url'   => strtolower($collection->code),
            'sub_type'   => WebpageSubTypeEnum::COLLECTION,
            'type'      => WebpageTypeEnum::CATALOGUE,
            'model_type'    => class_basename($collection),
            'model_id'     => $collection->id
            ];

        $webpage = StoreWebpage::make()->action(
            $collection->shop->website,
            $webpageData
        );

        return $webpage;
    }

    public function htmlResponse(Webpage $webpage)
    {
        return Redirect::route(
            'grp.org.shops.show.web.webpages.show',
            [
            'organisation' => $webpage->organisation->slug,
            'shop'         => $webpage->shop->slug,
            'website'      => $webpage->website->slug,
            'webpage'      => $webpage->slug,
        ]
        );
    }

    public function asController(Collection $collection): Webpage
    {
        $this->initialisationFromShop($collection->shop, []);
        return $this->handle($collection);
    }
}
