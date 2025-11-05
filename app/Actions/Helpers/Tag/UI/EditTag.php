<?php
/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditTag extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(Tag $tag): Tag
    {
        return $tag;
    }

    public function asController(Organisation $organisation, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisation($organisation, $request);

        return $this->handle($tag);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tag);
    }

    public function htmlResponse(Tag $tag, ActionRequest $request): Response
    {
        $scopes = collect(TagScopeEnum::cases())->map(fn ($case) => [
            'label' => $case->pretty(),
            'value' => $case->value,
        ])->toArray();

        return Inertia::render(
            'EditModel',
            [
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
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
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
                        [
                            'label'  => __('Scope'),
                            'title'  => __('Edit Scope'),
                            'fields' => [
                                'scope' => [
                                    'type'     => 'select',
                                    'label'    => __('Scope'),
                                    'options'  => $scopes,
                                    'value'    => $tag->scope
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.org.tags.update',
                            'parameters' => [$this->organisation->slug, $tag->id],
                        ],
                    ]
                ]
            ]
        );
    }
}
