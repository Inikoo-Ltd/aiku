<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\CRM\Prospect\Mailshots\GetProspectMailshotRecipientsQueryBuilder;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowProspectMailshotRecipients extends OrgAction
{
    public function handle(Mailshot $mailshot, ActionRequest $request): Response
    {
        $requestFilters = $request->input('filters', []);

        $defaultFilters = [
            'all_prospects' => [
                'value' => true,
            ],
        ];
        $currentFilters = empty($requestFilters) ? $defaultFilters : $requestFilters;
        $previewMailshot = $mailshot->replicate();
        $previewMailshot->id = $mailshot->id;
        $previewMailshot->recipients_recipe = $currentFilters;

        $queryBuilder = GetProspectMailshotRecipientsQueryBuilder::make()->handle($previewMailshot);
        $estimatedRecipients = $queryBuilder?->count('prospects.id') ?? 0;

        $filtersStructure = [
            'prospects' => [
                'title'   => 'Prospect Filters',
                'filters' => [
                    'never_contacted' => [
                        'label'       => 'Never Contacted',
                        'type'        => 'boolean',
                        'description' => 'Targets prospects who have never been contacted.',
                    ],
                    'last_contacted' => [
                        'label'       => 'Last Contacted',
                        'type'        => 'boolean',
                        'description' => 'Targets prospects whose last contact was a specified number of weeks ago or more.',
                        'options'     => [
                            'weeks' => [
                                'label' => 'Time period',
                                'presets' => [
                                    ['label' => '1 week ago', 'value' => 'one_week_ago'],
                                    ['label' => '2 weeks ago', 'value' => 'two_weeks_ago'],
                                    ['label' => '3 weeks ago', 'value' => 'three_weeks_ago'],
                                    ['label' => 'Custom date', 'value' => 'custom'],
                                ],
                                'default' => 'three_weeks_ago',
                            ],
                        ],
                    ],
                    'sent_email_times' => [
                        'label'       => 'Number of Emails Sent',
                        'type'        => 'boolean',
                        'description' => 'Targets prospects who have already been sent emails a specified number of times.',
                        'options'     => [
                            'count' => [
                                'label' => 'Number of times',
                                'default' => 3,
                                'min' => 1,
                            ],
                        ],
                    ],
                ]
            ]
        ];

        return Inertia::render(
            'CRM/ProspectMailshotRecipients',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'mailshot' => $mailshot,
                'title'    => __('Setup Recipients'),
                'pageHead' => [
                    'title' => __('Setup Recipients'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.prospects.mailshots.show',
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ],
                    ]
                ],
                'filtersStructure' => $filtersStructure,
                'filters'          => $currentFilters,
                'recipientFilterRoute' => [
                    'name'       => 'grp.models.shop.prospect.mailshot.recipient-filter.update',
                    'parameters' => [
                        'shop' => $mailshot->shop_id,
                        'mailshot' => $mailshot->id
                    ],
                    'method' => 'patch'
                ],
                'recipients_recipe' => $mailshot->recipients_recipe,
                'shop_id' => $mailshot->shop_id,
                'shop_slug' => $this->shop->slug,
                'estimatedRecipients' => $estimatedRecipients
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $request);
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters): array
    {
        return ShowProspectMailshot::make()->getBreadcrumbs(
            mailshot: $mailshot,
            routeName: preg_replace('/recipients$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Recipients') . ')'
        );
    }
}
