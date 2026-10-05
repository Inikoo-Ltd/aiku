<?php

/*
 * author Arya Permana - Kirin
 * created on 24-10-2024-10h-31m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Customer;
use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgPartnerShowcase
{
    use AsObject;

    public function handle(OrgPartner $orgPartner): array
    {
        $partner = $orgPartner->partner;
        return [
            'contactCard' => [
                'company'  => $partner->name,
                'contact'  => $partner->contact_name,
                'email'    => $partner->email,
                'phone'    => $partner->phone,
                'location' => $partner->location,
                // 'address'  => AddressResource::make($agent->organisation->address)->getArray(),
                'photo'    => $partner->imageSources()
            ],
            'miniCart'    => GetPartnerMiniCart::run($orgPartner),
            'customerAccounts' => $this->getCustomerAccounts($orgPartner),
            'stats'       => [
                [
                    'label' => __('Shopping list'),
                    'icon'  => 'fal fa-shopping-basket',
                    'count' => $orgPartner->stats->number_open_shopping_list_items,
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $partner->procurementStats->number_purchase_orders,
                ],
                [
                    'label' => __('Stocks'),
                    'icon'  => 'fal fa-box',
                    'count' => $partner->inventoryStats->number_org_stocks,
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $partner->inventoryStats->number_deliveries,
                ],
            ]
        ];
    }

    /**
     * The one account per shop the partner buys under; orders on it are invoiced as partner sales.
     */
    private function getCustomerAccounts(OrgPartner $orgPartner): array
    {
        return Customer::query()
            ->where('organisation_id', $orgPartner->organisation_id)
            ->where('as_organisation_id', $orgPartner->partner_id)
            ->with('shop:id,slug,name')
            ->orderBy('shop_id')
            ->get(['id', 'slug', 'reference', 'name', 'shop_id'])
            ->map(fn (Customer $customer) => [
                'shop'      => $customer->shop->name,
                'reference' => $customer->reference,
                'name'      => $customer->name,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$orgPartner->organisation->slug, $customer->shop->slug, $customer->slug],
                ],
            ])
            ->all();
    }
}
