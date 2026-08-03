<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jun 2026 16:36:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Accounting\Traits\HasPaymentAccountShopFields;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPaymentAccountShop extends OrgAction
{
    use HasPaymentAccountShopFields;


    /**
     * @var Fulfilment|PaymentAccount|PaymentAccountShop|Shop
     */
    private Fulfilment|PaymentAccount|Shop|PaymentAccountShop $parent;

    public function handle(PaymentAccountShop $parent): PaymentAccountShop
    {
        return $parent;
    }

    public function htmlResponse(PaymentAccountShop $paymentAccountShop, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $paymentAccountShop,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Payment Account Shop'),
                'pageHead'    => [
                    'icon'    => ['fal', 'fa-store-alt'],
                    'title'   => __('Edit Payment Account Shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => $this->blueprint($paymentAccountShop),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.payment_account_shop.update',
                            'parameters' => [
                                'paymentAccountShop' => $paymentAccountShop->id
                            ]
                        ],
                    ]
                ]

            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, PaymentAccountShop $paymentAccountShop, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($paymentAccountShop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, PaymentAccountShop $paymentAccountShop, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($paymentAccountShop);
    }


    public function asController(Organisation $organisation, PaymentAccount $paymentAccount, PaymentAccountShop $paymentAccountShop, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccountShop);
    }

    public function getBreadcrumbs(PaymentAccountShop $paymentAccountShop, string $routeName, array $routeParameters): array
    {
        return ShowPaymentAccountShop::make()->getBreadcrumbs(
            paymentAccountShop: $paymentAccountShop,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
