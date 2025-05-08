<?php
/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\CreditCard;

use App\Actions\Accounting\PaymentAccountShop\UI\GetRetinaPaymentAccountShopCheckoutComData;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\CRM\Customer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaSavedCreditCard extends RetinaAction
{
    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->customer;
    }

    public function htmlResponse(Customer $customer): Response
    {

        return Inertia::render('Dropshipping/RetinaSavedCreditCard', [
            'title'        => __('Saved Credit Card'),
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => __('Saved Credit Card'),
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => __('Saved Credit Card')
                ],
                
            ],
            'checkout_com_data'=> [
                'label'       => __('Online payments'),
                'key'         => 'credit_card',
                'public_key'  => 'XXX',
                'environment' => app()->environment('production') ? 'production' : 'sandbox',
                'locale'      => 'en',
                'icon'        => 'fal fa-credit-card-front',
                'data'        => []
            ]
        ]);
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.saved-credit-card.show'
                            ],
                            'label' => __('Saved Credit Card'),
                        ]
                    ]
                ]
            );

    }
}
