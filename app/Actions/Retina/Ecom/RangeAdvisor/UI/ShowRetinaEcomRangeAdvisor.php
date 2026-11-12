<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\RangeAdvisor\UI;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\Iris\ShoppingAssistant\ShowIrisShoppingAssistant;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Actions\Web\Website\Layouts\FetchUsedProductsWebBlock;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Range Advisor section of the customer's account: the same advisor as the storefront's assistant
 * page (conversation, history and the ready-made panel), rendered inside the account layout.
 */
class ShowRetinaEcomRangeAdvisor extends RetinaAction
{
    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        $settings = GetShoppingAssistantSettings::run($this->website, $this->customer);
        abort_unless($settings, 404);

        return Inertia::render('Ecom/RangeAdvisor', [
            'breadcrumbs'           => $this->getBreadcrumbs(),
            'title'                 => __('Range Advisor'),
            'pageHead'              => [
                'title' => __('Range Advisor'),
                'icon'  => 'fal fa-lightbulb-on',
            ],
            'assistant_name'        => $settings['name'],
            'products_in_range'     => ShowIrisShoppingAssistant::productsInRange($this->shop),
            'web_block_family'      => data_get($this->website->liveProductsSnapshot?->layout, 'data'),
            'web_block_family_code' => FetchUsedProductsWebBlock::run($this->website),
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'retina.ecom.range_advisor.show'
                        ],
                        'label' => __('Range Advisor'),
                    ]
                ]
            ]
        );
    }
}
