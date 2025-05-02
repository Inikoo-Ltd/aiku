<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreatePurchaseOrder extends OrgAction
{
    public function handle(OrgSupplier|OrgAgent|OrgPartner $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'    => __('new purchase order'),
                'pageHead' => [
                    'title'   => __('new purchase order'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show.purchase_orders.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('purchase order'),
                            'fields' => [

                                'number' => [
                                    'type'  => 'input',
                                    'label' => __('number'),
                                    'value' => ''
                                ],
                            ]
                        ]
                    ],
                    'route'     => match (class_basename($parent)) {
                        'OrgSupplier' => [
                            'name'       => 'grp.models.org-supplier.purchase-order.store',
                            'parameters' => [$parent->id],
                        ],
                        'OrgAgent' => [
                            'name'       => 'grp.models.org-agent.purchase-order.store',
                            'parameters' => [$parent->id],
                        ],
                        'OrgPartner' => [
                            'name'       => 'grp.models.org-partner.purchase-order.store',
                            'parameters' => [$parent->id],
                        ]
                    }
                ],
            ]
        );
    }


    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier, $request);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexPurchaseOrders::make()->getBreadcrumbs(),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating purchase order"),
                    ]
                ]
            ]
        );
    }
}
