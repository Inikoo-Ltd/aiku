<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use App\Http\Resources\Tag\TagResource;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;
use Spatie\Tags\Tag;

class CreateMailshot extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Shop|Outbox $parent, ActionRequest $request): Response
    {
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
                'type' => [
                    'type'     => 'select',
                    'label'    => __('type'),
                    'required' => true,
                    'options'  => Options::forEnum(MailshotTypeEnum::class),
                ],
                'recipient_type' => [
                    'type'        => 'radio',
                    'label'       => __('Recipient Type'),
                    'required'    => true,
                    'value'       => 'query',
                    'options'     => [
                        [
                            "label" => "Query",
                            "value" => "query"
                        ],
                        [
                            "label" => "custom",
                            "value" => "custom"
                        ],
                        [
                            "label" => "Prospect",
                            "value" => "prospect"
                        ],
                    ]
                ],
            ]
        ];



        $fields[] = [
            'title'  => '',
            'fields' => [
                'recipients_recipe' => [
                    'type'        => 'mailshotRecipient',
                    'label'       => __('recipients'),
                    'required'    => true,
                    'options'     => [
                        'query'                  => IndexProspects::run(parent:$parent, prefix: null, scope: 'all'),
                        'custom_prospects_query' => '',
                        'tags'                   => TagResource::collection(Tag::where('type', 'crm')->get()),
                    ],
                    'full'      => true,
                    'value'     => [
                            'query'                     => null,
                            'custom_prospects_query'    => [
                                "tags" => [
                                    "tag_ids" => null,
                                    "logic" => 'all',
                                    "negative_tag_ids" => null
                                ],
                                "last_contact" => [
                                    "use_contact" => false,
                                    "interval" => null
                                ]
                            ],
                            'prospects' => null,
                    ]
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
                'title'    => __('new mailshot'),
                'pageHead' => [
                    'title' => __('new mailshot')
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('name'),
                                'fields' => array_merge(...array_map(fn ($item) => $item['fields'], $fields))
                            ]
                        ],
                    'route' => [
                        'name'       => 'grp.models.shop.mailshot.store',
                        'parameters' => [
                            'outbox'         => $parent->id,
                        ]
                    ]
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
            IndexMailshots::make()->getBreadcrumbs(
                routeName: $routeName,
                routeParameters: $routeParameters,
                parent: $parent
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating mailshot'),
                    ]
                ]
            ]
        );
    }

}
