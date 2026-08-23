<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\Payment\WithPaymentSubNavigation;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPaymentMethods extends OrgAction
{
    use WithPaymentSubNavigation;
    use WithAccountingSubNavigation;

    protected Organisation|Shop $parent;

    /**
     * One row per provider + method (+ card scheme), amounts in the parent's currency, all time.
     * The scheme only matters for plain cards: Apple Pay is Apple Pay, nobody cares which card sits
     * behind the wallet. The page groups the rows both ways, provider → method and method → provider.
     */
    public function handle(Organisation|Shop $parent): array
    {
        $query = Payment::query()
            ->leftJoin('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->whereNotNull('payments.method')->where('payments.method', '!=', '')
            ->where('payments.type', PaymentTypeEnum::PAYMENT);

        if ($parent instanceof Shop) {
            $query->where('payments.shop_id', $parent->id);
            $amountColumn = 'payments.amount';
            $organisation = $parent->organisation;
        } else {
            $query->where('payments.organisation_id', $parent->id);
            $amountColumn = 'payments.org_amount';
            $organisation = $parent;
        }

        $success = "payments.status = '".PaymentStatusEnum::SUCCESS->value."'";

        $rows = $query->select([
            'payments.method',
            DB::raw("CASE WHEN payments.method = 'card' THEN payments.sub_method END as sub_method"),
            'payment_accounts.type as payment_account_type',
            DB::raw('COUNT(*) as number_payments'),
            DB::raw("COUNT(*) FILTER (WHERE $success) as number_success"),
            DB::raw("COALESCE(SUM($amountColumn) FILTER (WHERE $success), 0) as total_sales"),
            DB::raw("MAX(payments.date) as last_payment_at"),
        ])
            ->groupBy('payments.method', DB::raw("CASE WHEN payments.method = 'card' THEN payments.sub_method END"), 'payment_accounts.type')
            ->orderByDesc('total_sales')
            ->get();

        $paymentsRoute = $parent instanceof Shop
            ? ['name' => 'grp.org.shops.show.dashboard.payments.accounting.payments.index', 'parameters' => ['organisation' => $organisation->slug, 'shop' => $parent->slug]]
            : ['name' => 'grp.org.accounting.payments.index', 'parameters' => ['organisation' => $organisation->slug]];

        return [
            'currency_code' => $parent->currency->code,
            'rows'          => $rows->map(fn ($row) => [
                'method'               => $row->method,
                'method_label'         => $row->method === $row->payment_account_type && in_array($row->method, ['checkout', 'braintree'])
                    ? __('Unidentified')
                    : Payment::methodLabel($row->method),
                'sub_method'           => $row->sub_method,
                'sub_method_label'     => $row->sub_method ? Payment::methodLabel($row->sub_method) : null,
                'payment_account_type' => $row->payment_account_type,
                'payment_account_label' => Payment::methodLabel($row->payment_account_type),
                'number_payments'      => (int) $row->number_payments,
                'number_success'       => (int) $row->number_success,
                'total_sales'          => (float) $row->total_sales,
                'last_payment_at'      => $row->last_payment_at,
                'route'                => [
                    'name'       => $paymentsRoute['name'],
                    'parameters' => array_merge($paymentsRoute['parameters'], [
                        'filter' => array_filter(['method' => $row->method, 'sub_method' => $row->sub_method]),
                    ]),
                ],
            ])->values()->all(),
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->parent = $shop;
        $this->initialisation($organisation, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(array $paymentMethods, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();
        $subNavigation   = $this->parent instanceof Shop
            ? $this->getSubNavigationShop($this->parent)
            : $this->getPaymentSubNavigation($this->parent);

        return Inertia::render(
            'Org/Accounting/PaymentMethods',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeName, $routeParameters),
                'title'       => __('Payment Methods'),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'icon'          => ['fal', 'fa-credit-card'],
                    'title'         => __('Payment Methods'),
                ],
                'data'        => $paymentMethods,
            ]
        );
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
                        'label' => __('Payment Methods'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.payments.methods.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb()
            ),
            'grp.org.shops.show.dashboard.payments.accounting.payments.methods.index' =>
            array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
