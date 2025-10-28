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
use App\Http\Resources\Accounting\PaymentAccountShopsResource;
use App\Http\Resources\Accounting\PaymentAccountsResource;
use App\Enums\UI\Accounting\PaymentAccountTabsEnum; 
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Master\MasterShop;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @property PaymentAccount $paymentAccount
 */
class ShowPaymentAccountShop extends OrgAction
{
    use WithPaymentAccountSubNavigation;
    use WithAccountingSubNavigation;

    private Fulfilment|PaymentAccount|Shop $parent;

    public function handle(PaymentAccount|Shop|Fulfilment $parent, String $slug, $prefix = null): PaymentAccountShop
    {
        $shop = $parent instanceof Fulfilment ? $parent->shop : $parent;

        return PaymentAccountShop::leftJoin('payment_accounts', 'payment_accounts.id', '=', 'payment_account_shop.payment_account_id')
        ->where('payment_accounts.slug', $slug)
        ->where('payment_account_shop.shop_id', $shop->id)
        ->firstOrFail();;
    }

    public function htmlResponse(PaymentAccountShop $paymentAccountShop, ActionRequest $request): Response
    {
        $subNavigation = [];
        $isFulfilment = $this->parent instanceof Fulfilment;
        if (!$isFulfilment) {
            $subNavigation = $this->getSubNavigationShop($this->parent);
        } else {
            $subNavigation = $this->getSubNavigation($this->parent);
        }

        $paymentAccountParent = $paymentAccountShop->paymentAccount()->first();

        return Inertia::render(
            'Org/Accounting/PaymentAccount',
            [
                'title'       => $paymentAccountParent->name,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $isFulfilment,
                    $paymentAccountShop,
                    $paymentAccountParent,
                ),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'fa-money-check-alt'],
                            'title' => __('Payment account')
                        ],
                    'title'         => $paymentAccountParent->name,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PaymentAccountTabsEnum::navigation()
                ],
                'overview' => [
                    'dashboard' => [

                        'table'   => [],
                        'widgets' => [
                            'column_count' => 4,
                            'components'   => []
                        ]
                    ]
                ],
                'stats'    => [
                    'dashboard' => [
                        'table'   => [],
                        'widgets' => [
                            'column_count' => 4,
                            'components'   => []
                        ]
                    ]
                ],

            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, string $showPaymentAccount, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(PaymentAccountTabsEnum::values());

        return $this->handle($shop, $showPaymentAccount);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, string $showPaymentAccount, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PaymentAccountTabsEnum::values());

        return $this->handle($fulfilment, $showPaymentAccount);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAccounting(Organisation $organisation, string $showPaymentAccount, string $shopSlug, ActionRequest $request): PaymentAccountShop
    {
        $parent = Shop::where('slug', $shopSlug)->firstOrFail();
        if($parent->type == ShopTypeEnum::FULFILMENT){
            $parent = $parent->fulfilment()->first();
            $this->parent = $parent;
            $this->initialisationFromFulfilment($parent, $request)->withTab(PaymentAccountTabsEnum::values());
        }else{
            $this->parent = $parent;
            $this->initialisationFromShop($parent, $request)->withTab(PaymentAccountTabsEnum::values());
        }
        return $this->handle($parent, $showPaymentAccount);

    }


    public function asController(Organisation $organisation, PaymentAccountShop $paymentAccountShop, string $showPaymentAccount, ActionRequest $request): PaymentAccountShop
    {
        $this->parent = $paymentAccountShop;
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccount, $showPaymentAccount);
    }

    public function jsonResponse(PaymentAccountShop $paymentAccountShop): PaymentAccountShop
    {
        return PaymentAccountShop::where('id', $id)->firstOrFail();
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, bool $isFulfilment, PaymentAccountShop $paymentAccountShop, PaymentAccount|null $paymentAccountParent = null): array
    {
        $headCrumb = function (PaymentAccountShop $paymentAccountShop,  array $routeParameters, string $routeName, $paymentAccountParent) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Payment Accounts')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $paymentAccountParent->name,
                        ],

                    ],
                    'suffix'         => ''
                ],
            ];
        };

        return match ($isFulfilment) {
            true =>
            array_merge(
                (new ShowFulfilment())->getBreadcrumbs($routeParameters),
                $headCrumb($paymentAccountShop,
                [
                    'index' => [
                        "name" => "grp.org.fulfilments.show.operations.accounting.accounts.index",
                        "parameters" => $routeParameters        
                    ],
                    'model' => [
                        "name" => "grp.org.fulfilments.show.operations.accounting.accounts.show",
                        "parameters" => $routeParameters
                    ]
                ], 
                $routeName,
                $paymentAccountParent
                    
                )
            ),
            false =>
            array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb($paymentAccountShop, 
                [
                    'index' => [
                        "name" => "grp.org.shops.show.dashboard.payments.accounting.accounts.index",
                        "parameters" => $routeParameters        
                    ],
                    'model' => [
                        "name" => "grp.org.shops.show.dashboard.payments.accounting.accounts.show",
                        "parameters" => $routeParameters
                    ]
                ], 
                $routeName,
                $paymentAccountParent)
            ),
            default => []
        };
    }
}
