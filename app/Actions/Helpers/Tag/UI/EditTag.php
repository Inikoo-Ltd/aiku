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
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;

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

    public function inProductProperty(Tag $tag, ActionRequest $request): Response
    {
        $this->forcedScope = TagScopeEnum::PRODUCT_PROPERTY;
        $group = Group::query()->findOrFail($tag->group_id);
        $this->initialisationFromGroup($group, $request);

        return $this->handle($tag, $request);
    }

    public function handle(Tag $tag, ActionRequest $request): Response
    {
        $routeName = match ($this->forcedScope) {
            TagScopeEnum::USER_CUSTOMER    => 'grp.org.shops.show.crm.self_filled_tags.update',
            TagScopeEnum::ADMIN_CUSTOMER   => 'grp.org.shops.show.crm.internal_tags.update',
            TagScopeEnum::PRODUCT_PROPERTY => 'grp.trade_units.tags.update',
            default                        => 'grp.org.shops.show.crm.self_filled_tags.update',
        };

        $parameters = match ($this->forcedScope) {
            TagScopeEnum::PRODUCT_PROPERTY => [
                $tag->id
            ],
            default => [
                $this->organisation->slug,
                $this->shop->slug,
                $tag->id
            ],
        };

        $exitRoute = $this->forcedScope === TagScopeEnum::PRODUCT_PROPERTY
            ? [
                'name'       => preg_replace('/edit$/', 'index', $request->route()->getName()),
                'parameters' => [],
            ]
            : [
                'name'       => preg_replace('/edit$/', 'index', $request->route()->getName()),
                'parameters' => [
                    $this->organisation->slug,
                    $this->shop->slug
                ],
            ];

        $breadcrumbs = $this->forcedScope === TagScopeEnum::PRODUCT_PROPERTY
            ? array_merge(
                IndexTagsProductProperty::make()->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $request->route()->getName(),
                                'parameters' => $request->route()->originalParameters(),
                            ],
                            'label' => __('Edit'),
                        ],
                    ],
                ]
            )
            : $this->getBreadcrumbs($request->route()->originalParameters());

        $updateRoute = [
            'name'       => $routeName,
            'parameters' => $parameters,
        ];

        $fields = [
            'name' => [
                'type'  => 'input',
                'label' => __('Name'),
                'value' => $tag->name
            ],
        ];

        if ($this->forcedScope === TagScopeEnum::PRODUCT_PROPERTY) {
            $fields['label'] = [
                'type'          => 'input_translation_use_option',
                'label'         => __('Label'),
                'language_from' => 'en',
                'languages'     => GetLanguagesOptions::make()->all(),
                'value'         => $tag->getTranslations('label')
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $breadcrumbs,
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
                            'route' => $exitRoute
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Name'),
                            'title'  => __('Edit Name'),
                            'fields' => $fields,
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
