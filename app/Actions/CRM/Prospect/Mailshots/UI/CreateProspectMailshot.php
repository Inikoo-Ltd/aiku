<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\CRM\Prospect\Queries\UI\IndexProspectQueries;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProspectMailshot extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        // TODO: update if needed
        return true;
        // return $request->user()->authTo('crm.prospects.edit');
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }


    public function handle(Shop $parent, ActionRequest $request): Response
    {
        $fields[] = [
            'title'  => '',
            'fields' => [
                'subject' => [
                    'type'        => 'input',
                    'label'       => __('Subject'),
                    'placeholder' => __('Email subject'),
                    'required'    => true,
                    'value'       => '',
                ],
            ]
        ];

        // $tags = explode(',', $request->input('tags'));



        // $fields[] = [
        //     'title'  => '',
        //     'fields' => [
        //         'recipients_recipe' => [
        //             'type'        => 'prospectRecipients',
        //             'label'       => __('recipients'),
        //             'required'    => true,
        //             'options'     => [
        //                 'query'                  => IndexProspectQueries::run(),
        //                 'custom_prospects_query' => '',
        //             ],
        //             'full'      => true,
        //             'value'     => [
        //                 'recipient_builder_type' => 'query',
        //                 'recipient_builder_data' => [
        //                     'query'                     => null,
        //                     'custom_prospects_query'    => $tags[0] != '' ? [
        //                         'tags'   => [
        //                             'logic'    => 'all',
        //                             'tag_ids'  => $tags
        //                         ],
        //                     ] : null,
        //                     'prospects' => null,
        //                 ]
        //             ]
        //         ],
        //     ]
        // ];

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New mailshot'),
                'pageHead'    => [
                    'title'   => __('Prospect mailshot'),
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
                    'fullLayout' => true,
                    'submitLabel' => __('Continue'),
                    'blueprint' => $fields,
                    'route'     => [
                        'name'       => 'grp.models.shop.prospect.mailshot.store',
                        'parameters' => [
                            'shop' => $parent->id,
                        ]
                    ]
                ],

            ]
        );
    }


    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexProspectMailshots::make()->getBreadcrumbs(
                'grp.org.shops.show.crm.prospects.mailshots.index',
                $routeParameters
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("Creating mailshot"),
                    ]
                ]
            ]
        );
    }
}
