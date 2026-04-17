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
                'title'   => __('Prospect Filters'),
                'filters' => [
                    'never_contacted' => [
                        'label'       => __('Never Contacted'),
                        'type'        => 'boolean',
                        'description' => __('Targets prospects who have never been contacted.'),
                    ],
                    'last_contacted' => [
                        'label'       => __('Last Contacted'),
                        'type'        => 'boolean',
                        'description' => __('Targets prospects whose last contact was a specified number of weeks ago or more.'),
                        'options'     => [
                            'weeks' => [
                                'label' => __('Time period'),
                                'presets' => [
                                    ['label' => __('1 week ago'), 'value' => 'one_week_ago'],
                                    ['label' => __('2 weeks ago'), 'value' => 'two_weeks_ago'],
                                    ['label' => __('3 weeks ago'), 'value' => 'three_weeks_ago'],
                                    ['label' => __('Custom date'), 'value' => 'custom'],
                                ],
                                'default' => 'three_weeks_ago',
                            ],
                        ],
                    ],
                    'sent_email_times' => [
                        'label'       => __('Number of Emails'),
                        'type'        => 'boolean',
                        'description' => __('Targeting potential prospects who have received a certain number of emails.'),
                        'options'     => [
                            'count' => [
                                'label' => __('Number of emails'),
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
