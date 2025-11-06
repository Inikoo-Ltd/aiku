<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAnnouncement extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($announcement, $request);
    }

    public function handle(Announcement $announcement, ActionRequest $request): Response
    {
        $fields = [];

        $fields[] = [
            'label' => __('Detail'),
            'fields' => [
                'name' => [
                    'type'        => 'input',
                    'label'       => __('name'),
                    'placeholder' => __('Name for announcement'),
                    'required'    => true,
                    'value'       => $announcement->name,
                ],
            ]
        ];

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Announcement'),
                'pageHead'    => [
                    'title'   => $announcement->name,
                    'model' => __('Edit'),
                    'icon'    => [
                        'tooltip' => __('Edit'),
                        'icon'    => 'fal fa-megaphone'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $fields,
                    'args'     => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.website.announcement.update',
                            'parameters' => [
                                'shop' => $announcement->website->shop_id,
                                'website' => $announcement->website_id,
                                'announcement' => $announcement->id,
                            ]
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
