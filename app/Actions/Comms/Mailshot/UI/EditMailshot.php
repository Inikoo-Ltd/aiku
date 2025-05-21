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
use App\Http\Resources\Tag\TagResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditMailshot extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Mailshot $mailshot, ActionRequest $request): Response
    {
        $fields[] = [
            'title'  => '',
            'fields' => [
                'subject'        => [
                    'type'        => 'input',
                    'label'       => __('subject'),
                    'placeholder' => __('Email subject'),
                    'required'    => false,
                    'value'       => $mailshot->subject,
                ],
                'type'           => [
                    'type'     => 'select',
                    'label'    => __('type'),
                    'required' => false,
                    'value'    => $mailshot->type,
                    'options'  => Options::forEnum(MailshotTypeEnum::class),
                ],
                'recipient_type' => [
                    'type'     => 'radio',
                    'label'    => __('Recipient Type'),
                    'required' => true,
                    'value'    => 'query',
                    'options'  => [
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
                    'type'     => 'mailshotRecipient',
                    'label'    => __('recipients'),
                    'required' => true,
                    'options'  => [
                        'query'                  => IndexProspects::run(parent: $mailshot->shop, prefix: null, scope: 'all'),
                        'custom_prospects_query' => '',
                        'tags'                   => TagResource::collection(Tag::where('type', 'crm')->get()),
                    ],
                    'full'     => true,
                    'value'    => [
                        'query'                  => $mailshot->recipients_recipe['query'] ?? null,
                        'custom_prospects_query' => [
                            "tags"         => [
                                "tag_ids"          => $mailshot->recipients_recipe['custom_prospects_query']['tags']['tag_ids'] ?? null,
                                "logic"            => 'all',
                                "negative_tag_ids" => $mailshot->recipients_recipe['custom_prospects_query']['tags']['negative_tag_ids'] ?? null
                            ],
                            "last_contact" => [
                                "use_contact" => false,
                                "interval"    => $mailshot->recipients_recipe['custom_prospects_query']['last_contact']['interval'] ?? null
                            ]
                        ],
                        'prospects'              => $mailshot->recipients_recipe['query'] ?? null,
                    ]
                ],
            ]
        ];

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit mailshot'),
                'pageHead'    => [
                    'title' => __('edit mailshot')
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('name'),
                                'fields' => array_merge(...array_map(fn ($item) => $item['fields'], $fields))
                            ]
                        ],
                    'args'       => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.mailshot.update',
                            'parameters' => [
                                'mailshot' => $mailshot->id
                            ]

                        ],
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
    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $request);
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters): array
    {
        return ShowMailshot::make()->getBreadcrumbs(
            mailshot: $mailshot,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
