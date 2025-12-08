<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use App\Actions\InertiaAction;
use App\Enums\Web\Banner\BannerTabsEnum;
use App\Http\Resources\Web\BannerResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Banner;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDeletedBanner extends InertiaAction
{
    private Customer|Shop|Organisation $parent;

    public function handle(Organisation|Shop|Customer $parent, Banner $banner): Banner
    {
        $this->parent = $parent;

        return $banner;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canRestore = $request->get('customerUser')->hasPermissionTo('portfolio.banners.edit');

        return

                $request->get('customerUser')->hasPermissionTo('portfolio.banners.view');
    }

    public function asController(Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisation($request)->withTab(BannerTabsEnum::values());

        return $this->handle($request->get('customer'), $banner);
    }

    public function inCustomer(Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisation($request)->withTab(BannerTabsEnum::values());

        return $banner;
    }

    /*
    public function inPortfolioWebsite(PortfolioWebsite $portfolioWebsite, Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisation($request)->withTab(BannerTabsEnum::values());

        return $banner;
    }
    */

    public function htmlResponse(Banner $banner, ActionRequest $request): Response
    {
        $customer = $request->get('customer');

        return Inertia::render(
            'Banners/Banner',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => $banner->name,
                'pageHead' => [
                    'title' => $banner->name,
                    'icon' => [
                        'title' => __('Banner'),
                        'icon' => 'fal fa-sign',
                    ],
                    'actions' => [
                        $this->canRestore ? [
                            'type' => 'button',
                            'style' => 'edit',
                            'label' => __('restore'),
                            'icon' => ['fal', 'fa-trash-restore-alt'],
                            'route' => [
                                'name' => preg_replace('/show$/', 'workshop', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ] : false,
                    ],
                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => BannerTabsEnum::navigation(),
                ],
                BannerTabsEnum::SHOWCASE->value => $this->tab == BannerTabsEnum::SHOWCASE->value
                    ?
                    fn () => BannerResource::make($banner)->getArray()
                    : Inertia::lazy(
                        fn () => BannerResource::make($banner)->getArray()
                    ),
                BannerTabsEnum::CHANGELOG->value => $this->tab == BannerTabsEnum::CHANGELOG->value
                    ?
                    fn () => CustomerHistoryResource::collection(
                        IndexCustomerHistory::run(
                            customer: $customer,
                            model: $banner,
                            prefix: BannerTabsEnum::CHANGELOG->value
                        )
                    )
                    : Inertia::lazy(fn () => CustomerHistoryResource::collection(
                        IndexCustomerHistory::run(
                            customer: $customer,
                            model: $banner,
                            prefix: BannerTabsEnum::CHANGELOG->value
                        )
                    )),

            ]
        )->table(
            IndexCustomerHistory::make()->tableStructure(
                prefix: BannerTabsEnum::CHANGELOG->value
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return [];
    }
}
