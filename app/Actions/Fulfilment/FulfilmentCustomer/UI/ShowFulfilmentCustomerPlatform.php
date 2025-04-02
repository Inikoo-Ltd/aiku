<?php
/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Catalogue\HasRentalAgreement;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\GetFulfilmentCustomerShowcase;
use App\Actions\Fulfilment\RentalAgreementClause\UI\IndexRentalAgreementClauses;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItems;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithWebUserMeta;
use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerPlatformTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerTabsEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Fulfilment\RentalAgreementClausesResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Ordering\ModelHasPlatform;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentCustomerPlatform extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

    public function handle(ModelHasPlatform $modelHasPlatform): ModelHasPlatform
    {
        return $modelHasPlatform;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ModelHasPlatform $modelHasPlatform, ActionRequest $request): ModelHasPlatform
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentCustomerTabsEnum::values());

        return $this->handle($modelHasPlatform);
    }

    public function htmlResponse(ModelHasPlatform $modelHasPlatform, ActionRequest $request): Response
    {
        $navigation = FulfilmentCustomerPlatformTabsEnum::navigation();

        $actions = [];

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomer',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $modelHasPlatform,
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('platform'),
                        'icon'  => 'fal fa-user',
                    ],
                    'model'         => __('Platform'),
                    // 'subNavigation' => $this->getFulfilmentCustomerSubNavigation($fulfilmentCustomer, $request),
                    'title'         => $modelHasPlatform->platform->name,
                    'afterTitle'    => [
                        'label' => '('.$modelHasPlatform->model->name.')',
                    ],
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
            ]
        );
    }
    
    public function getBreadcrumbs(ModelHasPlatform $modelHasPlatform, array $routeParameters): array
    {
        $headCrumb = function (FulfilmentCustomer $fulfilmentCustomer, array $routeParameters, string $suffix = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Customers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $fulfilmentCustomer->customer->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $fulfilmentCustomer = $modelHasPlatform->model->fulfilmentCustomer;

        return array_merge(
            IndexFulfilmentCustomerPlatforms::make()->getBreadcrumbs(
                $routeParameters
            ),
            $headCrumb(
                $fulfilmentCustomer,
                [

                    'index' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show',
                        'parameters' => [
                            'organisation' => $routeParameters['organisation'],
                            'fulfilment'   => $routeParameters['fulfilment'],
                            'fulfilmentCustomer' => $routeParameters['fulfilmentCustomer'],
                            'platform'     => $modelHasPlatform->id
                        ]
                    ]
                ]
            )
        );
    }
}
