<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateAnnouncement extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $request);
    }

    public function handle(Website $parent, ActionRequest $request): Response
    {
        $fields = [];

        $fields[] = [
            'title'  => '',
            'fields' => [
                'name' => [
                    'type'        => 'input',
                    'label'       => __('Name'),
                    'placeholder' => __('Name for new announcement'),
                    'required'    => true,
                    'value'       => '',
                ],
            ]
        ];

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new announcement'),
                'pageHead'    => [
                    'model'   => __('Announcement'),
                    'icon'    => ['fal', 'fa-megaphone'],
                    'title'   => __('Create'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $fields,
                    'route'     => [
                                'name'       => 'grp.models.shop.website.announcement.store',
                                'parameters' => [
                                    'shop' => $parent->shop_id,
                                    'website' => $parent->id,
                                ]
                            ],

                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexAnnouncements::make()->getBreadcrumbs(
                'grp.org.shops.show.web.announcements.index',
                $routeParameters
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating banner"),
                    ]
                ]
            ]
        );
    }


}
