<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 26 Feb 2026 14:25:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProspectMailshot extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Shop|Outbox $parent, ActionRequest $request): Response
    {

        if ($parent instanceof Shop) {
            $outbox = $parent->outboxes()->where('outboxes.code', OutboxCodeEnum::MARKETING)->first();
        } else {
            $outbox = $parent;
        }

        $fields[] = [
            'title'  => '',
            'fields' => [
                'subject' => [
                    'type'        => 'input',
                    'label'       => __('subject'),
                    'placeholder' => __('Email subject'),
                    'required'    => true,
                    'value'       => '',
                ],
                // add default value all prospects
                'recipients_recipe' => [
                    'type'        => 'input',
                    'label'       => __('recipients recipe'),
                    'placeholder' => __('Email recipients recipe'),
                    'required'    => true,
                    'hidden'      => true,
                    'value'       => [
                        'all_prospects' => [
                            'value' => true
                        ]
                    ],
                ],
            ]
        ];

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent->organisation,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('New prospect mailshot'),
                'pageHead' => [
                    'title' => __('New prospect mailshot')
                ],
                'formData' => [
                    'fullLayout' => true,
                    'submitLabel' => __('Continue'),
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('Name'),
                                'fields' => array_merge(...array_map(fn ($item) => $item['fields'], $fields))
                            ]
                        ],
                    'route' => [
                        'name'       => 'grp.models.outbox.mailshot.store',
                        'parameters' => [
                            'outbox' => $outbox->id,
                        ]
                    ],

                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(Organisation $parent, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexProspectMailshots::make()->getBreadcrumbs(
                routeName: $routeName,
                routeParameters: $routeParameters
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating prospect mailshot'),
                    ]
                ]
            ]
        );
    }
}
