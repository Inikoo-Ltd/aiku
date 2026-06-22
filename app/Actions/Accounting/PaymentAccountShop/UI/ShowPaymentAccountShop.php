<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Oct 2025 14:09:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Actions\Accounting\PaymentAccount\UI\ShowPaymentAccount;
use App\Actions\Accounting\PaymentAccount\WithPaymentAccountSubNavigation;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Http\Resources\Accounting\PaymentAccountShopResource;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPaymentAccountShop extends OrgAction
{
    use WithPaymentAccountSubNavigation;
    use WithAccountingSubNavigation;


    /**
     * @var Fulfilment|PaymentAccount|PaymentAccountShop|Shop
     */
    private Fulfilment|PaymentAccount|Shop|PaymentAccountShop $parent;

    public function handle(PaymentAccountShop $parent, $prefix = null): PaymentAccountShop
    {
        return $parent;
    }

    public function htmlResponse(PaymentAccountShop $paymentAccountShop, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof PaymentAccount) {
            $subNavigation = $this->getPaymentAccountNavigation($this->parent);
        } elseif ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigationShop($this->parent);
        } elseif ($this->parent instanceof Fulfilment) {
            $subNavigation = $this->getSubNavigation($this->parent);
        }

        return Inertia::render(
            'Org/Accounting/PaymentAccountShop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Payment Account Shops'),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'icon'          => ['fal', 'fa-store-alt'],
                    'title'         => __('Payment Account Shops'),
                    'actions'       => [
                    [
                            'type'    => 'button',
                            'icon'        => 'fal fa-pencil',
                            'style'       => 'transparent',
                            'tooltip' => __('Edit payment account shop'),
                            'route' => [
                                'name' => 'grp.org.accounting.payment-accounts.edit',
                                'parameters' => [
                                    'organisation' => $paymentAccountShop->shop->organisation->slug,
                                    'paymentAccount' => $paymentAccountShop->paymentAccount->slug
                                ]
                            ],
                        ],
                    ],
                ],
                'data'        => PaymentAccountShopResource::make($paymentAccountShop)
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
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ShowPaymentAccount $showPaymentAccount, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function asController(Organisation $organisation, PaymentAccount $paymentAccount, ShowPaymentAccount $showPaymentAccount, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccount);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function () use ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Payment Accounts'),
                        'icon'  => 'fal fa-bars',

                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.payment-accounts.show.shops.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('grp.org.accounting.payment-accounts.show', $routeParameters),
                $headCrumb()
            ),
            'grp.org.shops.show.dashboard.payments.accounting.accounts.index' =>
            array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            'grp.org.fulfilments.show.operations.accounting.accounts.index' =>
            array_merge(
                (new ShowFulfilment())->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
