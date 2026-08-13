<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 12:55:09 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentShowcase
{
    use AsObject;

    public function handle(Agent $agent): array
    {
        $organisation = $agent->organisation;

        return [
            'contactCard' => [
                'created_at' => $agent->created_at,
                'company'    => $organisation->name,
                'contact'    => $organisation->contact_name,
                'location'   => $organisation->location,
                'email'      => $organisation->email,
                'phone'      => $organisation->phone,
                'currency'   => $agent->currency ?? $organisation->currency,
                'address'    => AddressResource::make($organisation->address)->getArray(),
                'photo'      => $organisation->imageSources(),
            ],
            'stats'       => [
                [
                    'label' => __('Suppliers'),
                    'icon'  => 'fal fa-person-dolly',
                    'count' => $agent->stats->number_active_suppliers,
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.suppliers.index',
                        'parameters' => [$agent->slug],
                    ],
                ],
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-box-usd',
                    'count' => $agent->stats->number_current_supplier_products,
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.supplier_products.index',
                        'parameters' => [$agent->slug],
                    ],
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $agent->stats->number_purchase_orders,
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.agent_supplier_purchase_orders.index',
                        'parameters' => [$agent->slug],
                    ],
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $agent->stats->number_stock_deliveries,
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.stock_deliveries.index',
                        'parameters' => [$agent->slug],
                    ],
                ],
            ],
            'depositWorkspace' => $this->getDepositWorkspace($agent),
        ];
    }

    private function getDepositWorkspace(Agent $agent): array
    {
        $pendingDeposits = $agent->deposits()
            ->where('state', 'pending')
            ->with('agentSupplierPurchaseOrder.purchaseOrder.organisation')
            ->get()
            ->map(function ($deposit) {
                $organisation = $deposit->agentSupplierPurchaseOrder?->purchaseOrder?->organisation;

                return [
                    'id'                => $deposit->id,
                    'reference'         => $deposit->reference,
                    'amount'            => $deposit->amount,
                    'currency_code'     => $deposit->currency->code,
                    'organisation_id'   => $organisation?->id,
                    'organisation_name' => $organisation?->name,
                ];
            })
            ->filter(fn ($row) => $row['organisation_id'])
            ->values();

        $requests = $agent->depositRequests()->with('items.organisation', 'currency')->orderByDesc('id')->limit(20)->get()
            ->map(fn ($request) => [
                'id'            => $request->id,
                'reference'     => $request->reference,
                'state'         => $request->state->value,
                'currency_code' => $request->currency->code,
                'items'         => $request->items->map(fn ($item) => [
                    'id'                => $item->id,
                    'organisation_name' => $item->organisation->name,
                    'amount'            => $item->amount,
                    'exchange'          => $item->exchange,
                    'paid_at'           => $item->paid_at,
                    'markPaidRoute'     => $item->paid_at ? null : [
                        'name'       => 'grp.models.deposit_request_item.mark_paid',
                        'parameters' => ['depositRequestItem' => $item->id],
                        'method'     => 'patch',
                    ],
                ])->all(),
            ]);

        return [
            'pendingDeposits' => $pendingDeposits->all(),
            'requests'        => $requests->all(),
            'storeRoute'      => [
                'name'       => 'grp.models.agent.deposit_request.store',
                'parameters' => ['agent' => $agent->id],
                'method'     => 'post',
            ],
        ];
    }
}
