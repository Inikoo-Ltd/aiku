<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\Helpers\Snapshot\StoreBannerSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Actions\Web\Banner\UI\ParseBannerLayout;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Banner\BannerTypeEnum;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class StoreBanner extends OrgAction
{
    use WithWebEditAuthorisation;

    private Website $website;


    /**
     * @throws \Throwable
     */
    public function handle(Website $website, array $modelData): Banner
    {
        $layout = [
            "delay"      => 5000,
            "navigation" => [
                "bottomNav" => [
                    "value" => true,
                    "type"  => "bullet"
                ],
                "sideNav"   => [
                    "value" => true,
                    "type"  => "arrow"
                ]
            ],
            "common"     => [
                "spaceBetween" => 0,
                "centralStage" => [
                    "title"    => null,
                    "subtitle" => null,
                    "text"     => null
                ]
            ],
            "components" => [
            ]
        ];
        list($layout, $slides) = ParseBannerLayout::run($layout);

        data_set($modelData, 'group_id', $website->group_id);
        data_set($modelData, 'organisation_id', $website->organisation_id);
        data_set($modelData, 'shop_id', $website->shop_id);
        data_set($modelData, 'website_id', $website->id);
        data_set($modelData, 'ulid', Str::ulid());
        data_set($modelData, 'date', now());

        /** @var Banner $banner */
        $banner   = Banner::create($modelData);
        $snapshot = StoreBannerSnapshot::make()->action(
            $banner,
            [
                'layout' => $layout,
            ],
            $slides
        );

        $banner->update(
            [
                'unpublished_snapshot_id' => $snapshot->id,
                'compiled_layout'         => $snapshot->compiledLayout()
            ]
        );

        $banner->stats()->create();

        BannerRecordSearch::dispatch($banner);

        return $banner;
    }


    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(BannerTypeEnum::class)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Website $website, ActionRequest $request): Banner
    {
        $this->website = $website;
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function action(Website $website, array $objectData): Banner
    {
        $this->asAction = true;
        $this->initialisationFromShop($website->shop, $objectData);

        return $this->handle($website, $this->validatedData);
    }


    public function htmlResponse(Banner $banner): RedirectResponse
    {
        if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
            return redirect()->route(
                'grp.org.fulfilments.show.web.banners.workshop',
                [
                    'organisation' => $this->website->organisation->slug,
                    'fulfilment'   => $this->shop->fulfilment->slug,
                    'website'      => $this->website->slug,
                    'banner'       => $banner->slug
                ]
            );
        } else {
            return redirect()->route(
                'grp.org.shops.show.web.banners.workshop',
                [
                    'organisation' => $this->website->organisation->slug,
                    'shop'         => $this->website->shop->slug,
                    'website'      => $this->website->slug,
                    'banner'       => $banner->slug
                ]
            );
        }
    }
}
