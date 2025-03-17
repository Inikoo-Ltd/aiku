<?php

/*
 * author Arya Permana - Kirin
 * created on 10-10-2024-09h-27m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Response;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class ShowWebpagesTree extends OrgAction
{
    use HasWebAuthorisation;

    public function handle(Website $website, ActionRequest $request): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $data = null;
        if (!app()->environment('production')) {
            $data = $this->getData($website);
        }
        return Inertia::render(
            'Org/Web/Structure',
            [

                'title'    => __('dummy'),
                'pageHead' => [
                    'title' => $request->route()->getName()
                ],
                'data'     => $data
            ]
        );
    }

    public function getData(Website $website)
    {
        $dataTree = [];

        $webpages = $website->webpages()->where('parent_id', null)->orderBy('id')->get();

        foreach ($webpages as $webpage) {
            $dataTree[] = [
                'id' => $webpage->id,
                'name' => $webpage->url ?: 'home',
                'children' => $this->getChildren($webpage)
            ];
        }

        return $dataTree;
    }

    public function getChildren($webpage)
    {
        $children = [];

        $webpages = $webpage->webpages()->orderBy('id')->get();

        foreach ($webpages as $webpage) {
            $children[] = [
                'id' => $webpage->id,
                'name' => $webpage->url,
                'children' => $this->getChildren($webpage)
            ];
        }

        return $children;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $request);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website, $request);
    }


}
