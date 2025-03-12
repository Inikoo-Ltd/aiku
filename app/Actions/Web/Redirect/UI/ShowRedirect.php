<?php
/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-16h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Enums\UI\Catalogue\ServiceTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentServiceTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRedirect extends OrgAction
{
    use HasWebAuthorisation;

    public function handle(Redirect $redirect): Redirect
    {
        return $redirect;
    }

    public function inWebpage(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, Redirect $redirect, ActionRequest $request): Redirect
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($redirect, $request);
    }

    public function inWebpageInFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, Redirect $redirect, ActionRequest $request): Redirect
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($redirect, $request);
    }

    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, Redirect $redirect, ActionRequest $request): Redirect
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($redirect, $request);
    }

    public function inWebsiteInFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Redirect $redirect, ActionRequest $request): Redirect
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($redirect, $request);
    }

    public function htmlResponse(Redirect $redirect, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/Redirect',
            [
                'title'       => __('Redirect'),
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->getName(),
                //     $request->route()->originalParameters()
                // ),
                'pageHead'    => [
                    'model'   => __('redirect'),
                    'title'   => $redirect->path,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-concierge-bell'],
                            'title' => __('redirect')
                        ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,

                    ]
                ],
            ]
        );
    }
}
