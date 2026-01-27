<?php

namespace App\Actions\Comms\Mailshot\UI;

use Inertia\Inertia;
use Inertia\Response;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Actions\Comms\Mailshot\GetMailshotRecipientsQueryBuilder;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Helpers\Tag;
use App\Models\Helpers\Country;

class ShowMailshotRecipients extends OrgAction
{
    public function handle(Mailshot $mailshot, ActionRequest $request): Response
    {
        $requestFilters = $request->input('filters', []);
        $savedFilters = $mailshot->recipients_recipe['customer_query'] ?? [];
        $currentFilters = empty($requestFilters) ? $savedFilters : $requestFilters;

        $previewMailshot = $mailshot->replicate();
        $previewMailshot->id = $mailshot->id;
        $previewMailshot->recipients_recipe = array_merge(
            $mailshot->recipients_recipe ?? [],
            ['customer_query' => $currentFilters]
        );

        $queryBuilder = GetMailshotRecipientsQueryBuilder::make()->handle($previewMailshot);

        $customers = $queryBuilder ? $queryBuilder->paginate(15)->withQueryString() : new LengthAwarePaginator([], 0, 15);

        $productFamilies = ProductCategory::query()
            ->where('type', 'family')
            ->where('shop_id', $mailshot->shop_id)
            ->whereIn('state', ['active', 'discontinuing'])
            ->orderBy('name')
            ->get()
            ->map(fn ($pc) => ['value' => $pc->id, 'label' => $pc->name])
            ->toArray();

        $subdepartments = ProductCategory::query()
            ->where('type', 'sub_department')
            ->where('shop_id', $mailshot->shop_id)
            ->whereIn('state', ['active', 'discontinuing'])
            ->orderBy('name')
            ->get()
            ->map(fn ($pc) => ['value' => $pc->id, 'label' => $pc->name])
            ->toArray();

        $interestTags = Tag::query()
            ->where('scope', TagScopeEnum::SYSTEM_CUSTOMER)
            ->orderBy('name')
            ->get()
            ->map(fn ($tag) => ['value' => $tag->id, 'label' => $tag->name])
            ->toArray();

        $countries = Country::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($country) => [
                'value' => $country->id,
                'label' => $country->name,
            ])
            ->toArray();

        $filtersStructure = [
            'marketing' => [
                'title'   => 'Email Marketing Targeting',
                'filters' => [
                    'registered_never_ordered' => [
                        'label'       => 'Registered Never Ordered',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have created an account but have never placed an order.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Registration Date Range',
                                'placeholder' => 'Select date range'
                            ]
                        ]
                    ],
                    'by_family_never_ordered' => [
                        'label'       => 'By Family Never Ordered',
                        'type'        => 'select',
                        'description' => 'Targets customers who have never placed an order containing products from the selected family.',
                        'multiple'    => false,
                        'options'     => $productFamilies,
                    ],
                    'orders_in_basket' => [
                        'label'       => 'Orders In Basket',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who currently have an order in their basket.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Basket Age',
                                'placeholder' => 'Select time frame (e.g. last 7 days)',
                            ],
                            'amount_range' => [
                                'type'        => 'number_range',
                                'label'       => 'Basket Value Range (Net Amount)',
                                'min_label'   => 'Min Value',
                                'max_label'   => 'Max Value',
                                'currency'    => $mailshot->shop->currency->symbol ?? '£',
                            ]
                        ]
                    ],
                    'by_order_value' => [
                        'label'       => 'By Order Value',
                        'type'        => 'boolean',
                        'description' => 'Target customers who have placed orders within a specific value range.',
                        'options'     => [
                            'amount_range' => [
                                'type'        => 'number_range',
                                'label'       => 'Order Value Range',
                                'min_label'   => 'Min Value',
                                'max_label'   => 'Max Value',
                                'currency'    => $mailshot->shop->currency->symbol ?? '£',

                            ]
                        ]
                    ],
                    'orders_collection' => [
                        'label'       => 'Orders Collection',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have ever selected "Collection" and collected an order from the warehouse.',
                        'options'     => [],
                    ],
                    'by_subdepartment' => [
                        'label'       => 'By Subdepartment',
                        'type'        => 'multiselect',
                        'description' => 'Target customers based on interaction with sub-departments.',
                        'multiple'    => true,
                        'options'     => $subdepartments,
                        'behavior_options' => [
                            ['value' => 'purchased', 'label' => 'Purchased products in the past'],
                            ['value' => 'in_basket', 'label' => 'Added to basket (not completed)'],
                        ]
                    ],
                    'gold_reward_status' => [
                        'label'       => 'Gold Reward Membership',
                        'type'        => 'select',
                        'description' => 'Filter customers based on their Gold Reward status (Last purchase within 30 days).',
                        'multiple'    => false,
                        'options'     => [
                            ['value' => 'gold', 'label' => 'Gold Reward Members (Active < 30 days)'],
                            ['value' => 'non_gold', 'label' => 'Non-Gold Reward Members (Inactive > 30 days)'],
                        ],
                    ],
                    'by_interest' => [
                        'label'          => 'By Interest',
                        'type'           => 'multiselect',
                        'description'    => 'Targets customers who have selected at least one of the chosen interests in their profile.',
                        'options'        => $interestTags,
                        'multiple'       => true,
                        'logic'          => 'OR'
                    ],
                    'by_showroom_orders'       => [
                        'label'       => 'By Showroom Orders',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have placed at least one order in the showroom in the past.'
                    ],
                    'by_location'              => [
                        'label'       => 'By Location',
                        'type'        => 'location',
                        'description' => 'Target customers based on Country/Postcode OR Radius from a location.',
                        'fields'      => [

                            'mode' => [
                                'type'    => 'select',
                                'label'   => 'Filter Mode',
                                'default' => 'direct',
                                'options' => [
                                    'direct' => 'By Country & Postcode',
                                    'radius' => 'By Radius (Geocoding)',
                                ]
                            ],

                            'country_ids' => [
                                'type'        => 'multiselect',
                                'label'       => 'Countries',
                                'placeholder' => 'Select countries',
                                'options'     => $countries,
                                'dependency'  => ['mode' => 'direct']
                            ],

                            'postal_codes' => [
                                'type'        => 'tags',
                                'label'       => 'Postal Codes',
                                'placeholder' => 'Type postcode and hit enter',
                                'dependency'  => ['mode' => 'direct']
                            ],

                            'location' => [
                                'type'        => 'input',
                                'label'       => 'Center Location (Address/City)',
                                'placeholder' => 'e.g. London, UK',
                                'dependency'  => ['mode' => 'radius']
                            ],

                            'radius'   => [
                                'type'    => 'select',
                                'label'   => 'Radius Distance',
                                'options' => [
                                    '5km'    => '5 km',
                                    '10km'   => '10 km',
                                    '25km'   => '25 km',
                                    '50km'   => '50 km',
                                    '100km'  => '100 km',
                                ],
                                'dependency' => ['mode' => 'radius']
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return Inertia::render(
            'Comms/MailshotRecipients',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'mailshot' => $mailshot,
                'title'    => __('Setup Recipients'),
                'pageHead' => [
                    'title' => __('Setup Recipients')
                ],
                'filtersStructure' => $filtersStructure,
                'filters'          => $currentFilters,
                'customers'        => $customers,
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $request);
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters): array
    {
        return ShowMailshot::make()->getBreadcrumbs(
            mailshot: $mailshot,
            routeName: preg_replace('/recipients$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Recipients') . ')'
        );
    }


}
