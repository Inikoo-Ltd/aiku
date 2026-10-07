<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\Web\WebsiteDialog\WithWebsiteDialogScope;
use Lorisleiva\Actions\ActionRequest;

class CreateWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebEditAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Response
    {
        $this->ensureWebsiteDialogScope($shop, $website);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $request);
    }

    public function handle(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'    => __('new dialog'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead' => [
                    'model'       => __('Dialog'),
                    'icon'        => ['fal', 'fa-window-restore'],
                    'title'       => __('Create'),
                    'actions'     => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => '',
                            'fields' => [
                                'name' => [
                                    'type'        => 'input',
                                    'label'       => __('Name'),
                                    'placeholder' => __('Name for new dialog'),
                                    'required'    => true,
                                    'value'       => '',
                                ],
                            ]
                        ]
                    ],
                    'route'     => [
                        'name'       => 'grp.models.shop.website.website_dialog.store',
                        'parameters' => [
                            'shop'    => $website->shop_id,
                            'website' => $website->id,
                        ]
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexWebsiteDialogs::make()->getBreadcrumbs('grp.org.shops.show.web.website_dialogs.index', $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('creating dialog'),
                    ]
                ]
            ]
        );
    }
}
