<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateTag extends OrgAction
{
    private ?TagScopeEnum $forcedScope = null;

    public function inSelfFilledTags(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->forcedScope = TagScopeEnum::USER_CUSTOMER;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        // Todo: conditional inSelfFilledTags and inInternalTag
        $route = [
            'name'       => 'grp.org.shops.show.crm.self_filled_tags.store',
            'parameters' => [
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug
            ]
        ];

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Create Tag'),
                'icon'        =>
                    [
                        'icon'  => ['fal', 'fa-tags'],
                        'title' => __('Tag'),
                    ],
                'pageHead'    => [
                    'title'        => __('Create Tag'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Create Tag'),
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                    'route' => $route,
                ],
            ],
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexTags::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.self_filled_tags.create',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Create'),
                    ],
                ],
            ],
        );
    }
}
