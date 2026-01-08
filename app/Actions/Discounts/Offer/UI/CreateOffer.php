<?php

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateOffer extends OrgAction
{
    /**
     * Handle the action
     */
    public function handle(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $offerCampaign,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Create New Offer'),
                'pageHead'    => [
                    'title' => __('Create New Offer'),
                    'actions' => $this->getHeaderActions($offerCampaign, $request)
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                    [
                        [
                            'title'  => __('Basic Information'),
                            'fields' => [
                                'code' => [
                                    'type'        => 'input',
                                    'label'       => __('Offer Code'),
                                    'placeholder' => __('Enter unique offer code'),
                                    'required'    => true,
                                    'help'        => __('Unique code for this offer (max: 64 characters, alphanumeric and dashes)'),
                                    'validation'  => ['required', 'max:64', 'alpha_dash']
                                ],
                                'name' => [
                                    'type'        => 'input',
                                    'label'       => __('Offer Name'),
                                    'placeholder' => __('Enter offer name'),
                                    'required'    => true,
                                    'validation'  => ['required', 'max:250', 'string']
                                ],
                                'type' => [
                                    'type'        => 'input',
                                    'label'       => __('Offer Type'),
                                    'placeholder' => __('Enter offer type'),
                                    'required'    => true,
                                    'validation'  => ['required', 'max:250', 'string']
                                ],
                            ],
                        ]
                    ],
                    'route' => [
                        'name'       => 'grp.org.shops.show.discounts.offers.store',
                        'parameters' => array_merge(
                            $request->route()->originalParameters(),
                            ['offerCampaign' => $offerCampaign->id]
                        ),
                    ],
                ],
            ]
        );
    }

    /**
     * Authorization logic
     */
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.create");
    }

    /**
     * Controller method
     */
    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offerCampaign, $request);
    }

    /**
     * Get breadcrumbs for create page
     */
    public function getBreadcrumbs(OfferCampaign $offerCampaign, string $routeName, array $routeParameters): array
    {
        $showAction = new ShowOffer();

        $dummyOffer = new \App\Models\Discounts\Offer();
        $dummyOffer->name = __('New Offer');
        $dummyOffer->id = 0;

        return $showAction->getBreadCrumbs(
            offer: $dummyOffer,
            routeName: preg_replace('/create$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Creating') . ')'
        );
    }

    /**
     * Get header actions (back button, etc.)
     */
    private function getHeaderActions(OfferCampaign $offerCampaign, ActionRequest $request): array
    {
        return [
            [
                'type'  => 'button',
                'style' => 'secondary',
                'label' => __('Back to Offers'),
                'route' => [
                    'name'       => 'grp.org.shops.show.discounts.offers.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'icon' => 'arrow-left'
            ]
        ];
    }
}
