<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Enums\UI\Web\SeoDashboardPageTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

/**
 * Visitors, Page views and Missing pages were pages of the SEO menu before they became tabs of the
 * SEO dashboard; their old addresses, bookmarks and breadcrumbs open the tab instead.
 */
class RedirectToSeoDashboardTab
{
    use AsController;

    private const array TABS = [
        'grp.org.shops.show.seo.visitors.index'   => SeoDashboardPageTabsEnum::VISITORS,
        'grp.org.shops.show.seo.page_views.index' => SeoDashboardPageTabsEnum::PAGE_VIEWS,
        'grp.org.shops.show.seo.not_found.index'  => SeoDashboardPageTabsEnum::MISSING_PAGES,
    ];

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $tab = self::TABS[$request->route()->getName()] ?? SeoDashboardPageTabsEnum::OVERVIEW;

        return redirect()->route('grp.org.shops.show.seo.dashboard', [
            'organisation' => $organisation->slug,
            'shop'         => $shop->slug,
            'tab'          => $tab->value,
        ]);
    }
}
