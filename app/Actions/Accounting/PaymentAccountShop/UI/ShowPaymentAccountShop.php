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
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
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

    public function handle(PaymentAccountShop $parent): PaymentAccountShop
    {
        return $parent;
    }

    public function htmlResponse(PaymentAccountShop $paymentAccountShop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/PaymentAccountShop',
            [
                'breadcrumbs'        => $this->getBreadcrumbs(
                    $paymentAccountShop,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'              => $paymentAccountShop->paymentAccount->name.' @'.$paymentAccountShop->shop->code,
                'pageHead'           => [
                    'icon'    => ['fal', 'fa-store-alt'],
                    'title'   => $paymentAccountShop->paymentAccount->name.' @'.$paymentAccountShop->shop->name,
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'tooltip' => __('Edit payment account in shop'),
                            'label'   => __('Edit'),
                            'route'   => [
                                'name'       => preg_replace('/\.show$/', '.edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ],
                    ],
                ],
                'payment_account_shop' => $this->getPaymentAccountShopData($paymentAccountShop)
            ]
        );
    }

    public function getPaymentAccountShopData(PaymentAccountShop $paymentAccountShop): array
    {
        $data = [
            'id'                        => $paymentAccountShop->id,
            'shop_id'                   => $paymentAccountShop->shop_id,
            'shop_code'                 => $paymentAccountShop->shop->code,
            'shop_name'                 => $paymentAccountShop->shop->name,
            'shop_slug'                 => $paymentAccountShop->shop->slug,
            'type'                      => $paymentAccountShop->type->value,
            'payment_account_code'      => $paymentAccountShop->paymentAccount->code,
            'payment_account_name'      => $paymentAccountShop->paymentAccount->name,
            'payment_account_slug'      => $paymentAccountShop->paymentAccount->slug,
            'activated_at'              => $paymentAccountShop->activated_at,
            'state'                     => $paymentAccountShop->state,
            'state_label'               => $paymentAccountShop->state->label(),
            'state_icon'                => $paymentAccountShop->state->stateIcon(),
            'show_in_checkout'          => $paymentAccountShop->show_in_checkout,
            'checkout_display_position' => $paymentAccountShop->checkout_display_position,
            'number_payments'           => $paymentAccountShop->stats->number_payments,
            'amount_successfully_paid'  => $paymentAccountShop->stats->amount_successfully_paid,
            'shop_currency_code'        => $paymentAccountShop->shop->currency->code,
        ];

        if ($paymentAccountShop->type == PaymentAccountTypeEnum::PASTPAY) {
            $creditTerms = Arr::get($paymentAccountShop->data, 'charges.options', []);
            $taxNumber   = Arr::get($paymentAccountShop->paymentAccount->data, 'tax_number');
            $footer      = $paymentAccountShop->invoice_footer;
            $hasFooter   = !blank(trim(strip_tags((string) $footer)));

            $data['pastpay'] = [
                'tax_number'      => $taxNumber,
                'credit_terms'    => $creditTerms,
                'invoice_footer'  => $footer,
                'setup_checklist' => [
                    ['label' => __('Creditor tax number'), 'done' => !blank($taxNumber)],
                    ['label' => __('Credit terms'), 'done' => !empty($creditTerms)],
                    ['label' => __('Invoice footer'), 'done' => $hasFooter],
                    ['label' => __('Activated'), 'done' => $paymentAccountShop->state == PaymentAccountShopStateEnum::ACTIVE],
                ],
            ];
        }

        return $data;
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

    public function getBreadcrumbs(PaymentAccountShop $paymentAccountShop, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function () use ($paymentAccountShop, $routeName, $routeParameters, $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route'  => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label'  => $paymentAccountShop->paymentAccount->name,
                        'suffix' => $suffix

                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.dashboard.payments.accounting.accounts.show' =>
            array_merge(
                IndexPaymentAccountShops::make()->getBreadcrumbs(
                    'grp.org.shops.show.dashboard.payments.accounting.accounts.index',
                    $routeParameters
                ),
                $headCrumb()
            ),

            'grp.org.accounting.payment-accounts.show.shops.index' =>
            array_merge(
                ShowPaymentAccount::make()->getBreadcrumbs('grp.org.accounting.payment-accounts.show', $routeParameters),
                $headCrumb()
            ),
            'grp.org.shops.show.dashboard.payments.accounting.accounts.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            'grp.org.fulfilments.show.operations.accounting.accounts.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
