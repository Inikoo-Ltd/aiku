<?php

/*
 * author Arya Permana - Kirin
 * created on 10-10-2024-09h-27m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Response;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class ShowWebpagesTree extends OrgAction
{
    use WithWebAuthorisation;


    public function htmlResponse(LengthAwarePaginator|Website $dataTree, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/Structure',
            [

                'title'    => __('dummy'),
                'pageHead' => [
                    'title' => $request->route()->getName()
                ],
                'data'     => $dataTree instanceof Website ? null : $dataTree->items(),
            ]
        );
    }


    public function handle(Website $website, ActionRequest $request): LengthAwarePaginator|Website
    {
        $dataTree = $website;
        if (!app()->environment('production')) {
            $dataTree = $this->getDataTree($website, $request);
        }

        return $dataTree;
    }

    public function getDataTree(Website $website, ActionRequest $request)
    {
        $perPage = 50;
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $webpages = $website->webpages
            ->where('parent_id', null)
            ->sortBy('id')
            ->slice($offset, $perPage)
            ->values();

        $dataTree = [];
        foreach ($webpages as $webpage) {
            $dataTree[] = [
                'id' => $webpage->id,
                'name' => $webpage->url ?: 'home',
                'children' => $this->getChildren($webpage)
            ];
        }

        return new LengthAwarePaginator(
            $dataTree,
            $website->webpages()->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
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

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator|Website
    {
        $this->scope  = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $request);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): LengthAwarePaginator|Website
    {
        $this->scope  = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website, $request);
    }


}
