<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\Web\WebsiteDialog\WithWebsiteDialogScope;
use Lorisleiva\Actions\ActionRequest;

class EditWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebEditAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): Response
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($websiteDialog, $request);
    }

    public function handle(WebsiteDialog $websiteDialog, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'    => __('Dialog'),
                'breadcrumbs' => ShowWebsiteDialog::make()->getBreadcrumbs(
                    $websiteDialog,
                    $request->route()->originalParameters(),
                    '('.__('Editing').')'
                ),
                'pageHead' => [
                    'title'       => $websiteDialog->name,
                    'model'       => __('Edit'),
                    'icon'        => [
                        'tooltip' => __('Dialog'),
                        'icon'    => 'fal fa-window-restore'
                    ],
                    'actions'     => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.show',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Detail'),
                            'fields' => [
                                'name' => [
                                    'type'        => 'input',
                                    'label'       => __('Name'),
                                    'placeholder' => __('Name for dialog'),
                                    'required'    => true,
                                    'value'       => $websiteDialog->name,
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Delete'),
                            'icon'   => 'fal fa-trash-alt',
                            'fields' => [
                                'name' => [
                                    'type'   => 'action',
                                    'action' => [
                                        'type'  => 'button',
                                        'style' => 'delete',
                                        'label' => __('Delete dialog'),
                                        'route' => [
                                            'method'     => 'delete',
                                            'name'       => 'grp.models.shop.website.website_dialog.delete',
                                            'parameters' => [
                                                'shop'          => $websiteDialog->website->shop_id,
                                                'website'       => $websiteDialog->website_id,
                                                'websiteDialog' => $websiteDialog->id,
                                            ]
                                        ],
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.website.website_dialog.update',
                            'parameters' => [
                                'shop'          => $websiteDialog->website->shop_id,
                                'website'       => $websiteDialog->website_id,
                                'websiteDialog' => $websiteDialog->id,
                            ]
                        ]
                    ],
                ],
            ]
        );
    }
}
