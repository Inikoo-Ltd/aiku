<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 15:40:54 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\Accounting\OrgPaymentServiceProvider\UI\ShowOrgPaymentServiceProvider;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Http\Resources\Accounting\PaymentAccountsResource;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPaymentAccounts extends OrgAction
{
    use IsIndexPaymentAccounts;
    use WithAccountingAuthorisation;


    private Organisation|OrgPaymentServiceProvider $parent;


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inOrgPaymentServiceProvider(Organisation $organisation, OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgPaymentServiceProvider;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPaymentServiceProvider);
    }



    public function htmlResponse(LengthAwarePaginator $paymentAccounts, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Accounting/PaymentAccounts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('Payment Accounts'),
                'pageHead'    => [
                    'icon'      => ['fal', 'fa-money-check-alt'],
                    'title'     => __('Payment Accounts'),
                    'actions'   => [
                        $this->canEdit && $this->parent instanceof OrgPaymentServiceProvider ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('payment account'),
                            'route' => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false
                    ],



                ],
                'data'        => PaymentAccountsResource::collection($paymentAccounts)


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {


        return match ($routeName) {

            'grp.org.accounting.payment-accounts.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $this->headCrumb($routeName, $routeParameters)
            ),
            'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index' =>
            array_merge(
                ShowOrgPaymentServiceProvider::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $this->headCrumb($routeName, $routeParameters)
            ),

            default => []
        };
    }
}
