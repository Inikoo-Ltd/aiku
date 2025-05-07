<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:26:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Http\Resources\Accounting\PaymentAccountsResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPaymentAccountsInGroup extends OrgAction
{
    use IsIndexPaymentAccounts;
    use WithGroupOverviewAuthorisation;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
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
                ],
                'data'        => PaymentAccountsResource::collection($paymentAccounts)


            ]
        )->table($this->tableStructure($this->group));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {

        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            $this->headCrumb($routeName, $routeParameters)
        );
    }
}
