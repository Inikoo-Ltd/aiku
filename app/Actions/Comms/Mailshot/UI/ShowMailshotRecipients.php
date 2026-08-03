<?php

namespace App\Actions\Comms\Mailshot\UI;

use Inertia\Inertia;
use Inertia\Response;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Comms\Mailshot\GetMailshotRecipientsQueryBuilder;
use App\Actions\CRM\Customer\GetCustomerFilterStructure;
use App\Actions\Traits\Authorisations\WithMarketingAuthorisation;

class ShowMailshotRecipients extends OrgAction
{
    use WithMarketingAuthorisation;

    public function handle(Mailshot $mailshot, ActionRequest $request): Response
    {
        $requestFilters = $request->input('filters', []);

        $defaultFilters = [
            'all_customers' => [
                'value' => true,
            ],
        ];
        $currentFilters = empty($requestFilters) ? $defaultFilters : $requestFilters;
        $previewMailshot = $mailshot->replicate();
        $previewMailshot->id = $mailshot->id;
        $previewMailshot->recipients_recipe = $currentFilters;

        $queryBuilder = GetMailshotRecipientsQueryBuilder::make()->handle($previewMailshot);
        $estimatedRecipients = $queryBuilder?->count('customers.id') ?? 0;

        $filtersStructure = GetCustomerFilterStructure::run($mailshot->shop);

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
                    'title' => __('Setup Recipients'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.mailshots.show',
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
                    'name'       => 'grp.models.shop.mailshot.recipient-filter.update',
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
        return ShowMailshot::make()->getBreadcrumbs(
            mailshot: $mailshot,
            routeName: preg_replace('/recipients$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Recipients') . ')'
        );
    }
}
