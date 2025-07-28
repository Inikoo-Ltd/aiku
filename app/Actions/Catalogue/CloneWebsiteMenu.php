<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Jul 2025 14:21:05 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneWebsiteMenu
{
    use asAction;
    use WithStoreWebpage;
    use WithActionUpdate;


    public array $navigation = [];
    public array $subNavigation = [];
    public array $subNavigationLink = [];

    public Shop $shop;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop|Shop $fromShop, Shop $shop): void
    {
        $fromWebsite = $fromShop->website;
        $website     = $shop->website;

        $this->shop   = $shop;
        $fromBaseMenu = Arr::get($fromWebsite->unpublishedMenuSnapshot->layout, 'menu');
        $fromMenuData = Arr::pull($fromBaseMenu, 'data.fieldValue');


        foreach ($fromMenuData as $key => $value) {
            if ($key == 'navigation') {
                $this->parseNavigationSection($value);
            } elseif ($key == 'sub_navigation') {
                $this->parseSubNavigationSection($value);
            } elseif ($key == 'sub_navigation_link') {
                $this->parseSubNavigationSectionLink($value);
            }
        }

        $fieldValue = [
            'navigation'          => $this->navigation,
            'sub_navigation'      => $this->subNavigation,
            'sub_navigation_link' => $this->subNavigationLink,
        ];

        $layout = data_set($fromBaseMenu, 'data.fieldValue', $fieldValue);
        $this->saveMenu($website, $layout);
    }

    public function saveMenu(Website $website, array $layout): void
    {
        if (!$website->unpublishedMenuSnapshot) {
            $menuSnapshot = StoreWebsiteSnapshot::run(
                $website,
                [
                    'scope'  => SnapshotScopeEnum::MENU,
                    'layout' => []
                ]
            );

            $website->update(
                [
                    'unpublished_menu_snapshot_id' => $menuSnapshot->id
                ]
            );
            $website->refresh();
        }


        $this->update($website->unpublishedMenuSnapshot, [
            'layout' => [
                'menu' => $layout
            ]
        ]);
    }

    public function parseNavigationSection($navigationSections): void
    {
        foreach ($navigationSections as $navigationSection) {
            $parsedLink = $this->parseLink(Arr::get($navigationSection, 'link'));
            if (!$parsedLink) {
                continue;
            }


            $newNavigationSection            = $navigationSection;
            $newNavigationSection['link']    = $parsedLink;
            $newNavigationSection['subnavs'] = $this->parseSubnavs(Arr::get($navigationSection, 'subnavs'));


            $this->navigation[] = $newNavigationSection;
        }
    }

    public function parseSubnavs($data): array
    {
        $subnavs = [];
        $counter = 0;
        foreach ($data as $value) {
            $subnav = $this->parseSubnav($value);
            if ($subnav) {
                data_set($subnav, 'index', $counter);
                $subnavs[] = $subnav;
                $counter++;
            }
        }

        return $subnavs;
    }

    public function parseSubnav($data): ?array
    {
        $link     = null;
        $formLink = Arr::get($data, 'link');
        if ($formLink) {
            $link = $this->parseLink($formLink);
        }

        $links = $this->parseLinks($data['links']);

        $result = [
            'id'    => Str::uuid(),
            'link'  => $link,
            'links' => $links,
            'title' => $data['title'],
        ];

        if ($link) {
            data_set($result, 'link', $link);
        }


        return $result;
    }

    public function parseLinks($data): array
    {
        $links = [];
        foreach ($data as $value) {
            $link = $this->parseLink($value['link']);
            if ($link) {
                $links[] = [
                    'id'    => Str::uuid(),
                    'link'  => $link,
                    'label' => $value['label'],
                    'icon'  => $value['icon'] ?? '',
                ];
            }
        }

        return $links;
    }

    public function parseSubNavigationSection($data): void
    {
        $this->subNavigation = $data;
    }

    public function parseSubNavigationSectionLink($data): void
    {
        $this->subNavigationLink = $data;
    }

    public function parseLink(array $linkData): ?array
    {
        $webpage = Webpage::find($linkData['id']);
        if ($webpage->model instanceof ProductCategory) {
            $foundCategoryData = DB::table('product_categories')
                ->where('shop_id', $this->shop->id)
                ->whereRaw("lower(code) = lower(?)", [$webpage->model->code])->first();

            if ($foundCategoryData) {
                $foundCategory = ProductCategory::find($foundCategoryData->id);
                if ($foundCategory && $foundCategory->webpage) {
                    return [
                        'id'       => $foundCategory->webpage->id,
                        'href'     => $foundCategory->webpage->getUrl(),
                        'type'     => 'internal',
                        'target'   => '_self',
                        'workshop' => route(
                            'grp.org.shops.show.web.webpages.workshop',
                            [
                                'organisation' => $foundCategory->organisation->slug,
                                'shop'         => $foundCategory->shop->slug,
                                'website'      => $foundCategory->shop->website->slug,
                                'webpage'      => $foundCategory->webpage->slug,
                            ]
                        ),
                    ];
                }
            }
        }

        return null;
    }

    public function getCommandSignature(): string
    {
        return 'catalogue:clone_menu {from_type} {from} {to}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('to'))->firstOrFail();
        if ($command->argument('from_type') == 'shop') {
            $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();
        } else {
            $fromShop = MasterShop::where('slug', $command->argument('from'))->firstOrFail();
        }
        $this->handle($fromShop, $shop);

        return 0;
    }


}
