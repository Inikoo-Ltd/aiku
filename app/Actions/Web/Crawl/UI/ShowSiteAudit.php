<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * The shop's old site audit address, kept so bookmarks and links land on the site crawler of the
 * shop's website. Its breadcrumbs still lead the content suggestions page.
 */
class ShowSiteAudit extends OrgAction
{
    use WithWebAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Website
    {
        abort_unless($shop->website, 404);

        $this->initialisationFromShop($shop, $request);

        return $shop->website;
    }

    public function htmlResponse(Website $website): RedirectResponse
    {
        return redirect()->route('grp.websites.crawler.index', ['website' => $website->slug]);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $shopParameters = Arr::only($routeParameters, ['organisation', 'shop']);

        return array_merge(
            ShowSeoDashboard::make()->getBreadcrumbs($shopParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.site_audit.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Site audit'),
                    ],
                ],
            ]
        );
    }
}
