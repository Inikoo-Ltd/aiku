<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Tag\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateTag extends OrgAction
{
    use WithGoodsEditAuthorisation;

    private Group $parent;

    public function asController(ActionRequest $request): Response
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $request);
    }

    public function handle(Group $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->getName(),
                //     $request->route()->originalParameters()
                // ),
                'title'    => __('new tag'),
                'icon'     =>
                    [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('Tag')
                    ],
                'pageHead' => [
                    'title'        => __('new Tag'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('new Tag'),
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                            ]
                        ]
                    ],
                    'route' => [
                        'name'      => 'grp.models.tags.store',
                        'parameters' => []
                    ]
                ],

            ]
        );
    }
}
