<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMailshot extends OrgAction
{
    use WithMarketingEditAuthorisation;
    /**
     * @throws Exception
     */
    public function handle(Mailshot $mailshot, ActionRequest $request): Response
    {
        $fields[] = [
            'title'  => '',
            'fields' => [
                'name'           => [
                    'type'        => 'input',
                    'label'       => __('Name'),
                    'placeholder' => __('Mailshot name'),
                    'required'    => false,
                    'value'       => $mailshot->name,
                ],
            ]
        ];

        // Only show subject and preview_text for non-SENT states
        if ($mailshot->state !== MailshotStateEnum::SENT) {
            $fields[0]['fields']['subject'] = [
                'type'        => 'input',
                'label'       => __('Subject'),
                'placeholder' => __('Email subject'),
                'required'    => false,
                'value'       => $mailshot->subject,
            ];

            $fields[0]['fields']['preview_text'] = [
                'type'        => 'input',
                'label'       => __('Preview text'),
                'placeholder' => __('Email preview text'),
                'required'    => false,
                'value'       => $mailshot->preview_text,
            ];
        }

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
                    'title' => __('Edit mailshot'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                    [
                        [
                            'title'  => '',
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
            suffix: '(' . __('Editing') . ')'
        );
    }
}
