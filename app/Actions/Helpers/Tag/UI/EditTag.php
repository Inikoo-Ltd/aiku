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
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditTag extends OrgAction
{
    private ?TagScopeEnum $forcedScope = null;

    public function inSelfFilledTags(Organisation $organisation, Shop $shop, Tag $tag, ActionRequest $request): Response
    {
        $this->forcedScope = TagScopeEnum::USER_CUSTOMER;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($tag, $request);
    }

    public function inInternalTags(Organisation $organisation, Shop $shop, Tag $tag, ActionRequest $request): Response
    {
        $this->forcedScope = TagScopeEnum::ADMIN_CUSTOMER;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($tag, $request);
    }

    public function handle(Tag $tag, ActionRequest $request): Response
    {
        $routeName = match ($this->forcedScope) {
            TagScopeEnum::USER_CUSTOMER  => 'grp.org.shops.show.crm.self_filled_tags.update',
            TagScopeEnum::ADMIN_CUSTOMER => 'grp.org.shops.show.crm.internal_tags.update',
            default                      => 'grp.org.shops.show.crm.self_filled_tags.update',
        };

        $updateRoute = [
            'name'       => $routeName,
            'parameters' => [
                $this->organisation->slug,
                $this->shop->slug,
                $tag->id
            ],
        ];

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Edit Tag'),
                'pageHead'    => [
                    'title'   => $tag->name,
                    'icon'    => [
                        'title' => __('Tags'),
                        'icon'  => 'fal fa-tags'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'index', $request->route()->getName()),
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug
                                ]
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Name'),
                            'title'  => __('Edit Name'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $tag->name
                                ],
                            ],
                        ],
                    ],
                    'args' => [
                        'updateRoute' => $updateRoute
                    ]
                ]
            ]
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
                            'name'       => 'grp.org.shops.show.crm.self_filled_tags.edit',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Edit'),
                    ],
                ],
            ],
        );
    }
}
