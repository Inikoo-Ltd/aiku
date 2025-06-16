<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 22:44:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Overview;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\OverviewResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetOrganisationOverview extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Organisation $organisation): AnonymousResourceCollection
    {
        $sections = $this->getSection($organisation);

        $dataRaw = collect($sections)->map(function ($data, $section) {
            return (object)[
                'section' => $section,
                'data'    => collect($data)->map(function ($item) {
                    return (object)$item;
                }),
            ];
        });

        return OverviewResource::collection($dataRaw);
    }

    public function getSection(Organisation $organisation): array
    {
        return [
            __('Sysadmin')                    => $this->getSysAdminSections($organisation),
            __('Comms').' & '.__('Marketing') => $this->getCommsAndMarketingSections($organisation),
            __('Catalogue')                   => $this->getCatalogueSections($organisation),
            __('Billables')                   => $this->getBillablesSections($organisation),
            __('Offer')                       => $this->getOffersSections($organisation),
            __('Web')                         => $this->getWebSections($organisation),
            __('CRM')                         => $this->getCrmSections($organisation),
            __('Ordering')                    => $this->getOrderingSections($organisation),
            __('Accounting')                  => $this->getAccountingSections($organisation),
            __('Inventory')       => $this->getInventorySections($organisation),
            __('Fulfilment')      => $this->getFulfilmentSections($organisation),
            __('Procurement')     => $this->getProcurementSections($organisation),
            __('Human Resources') => $this->getHumanResourcesSections($organisation),

        ];
    }

    protected function getSysAdminSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Changelog'),
                'icon'  => 'fal fa-history',
                'route' => route('grp.org.overview.changelog.index', $organisation),
                'count' => $organisation->stats->number_audits ?? 0
            ],
        ];
    }

    protected function getCommsAndMarketingSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Post Rooms'),
                'icon'  => 'fal fa-booth-curtain',
                'route' => '',
                'count' => $organisation->commsStats->number_org_post_rooms ?? 0
            ],
            [
                'name'  => __('Outboxes'),
                'icon'  => 'fal fa-inbox-out',
                'route' => '',
                'count' => $organisation->commsStats->number_outboxes ?? 0
            ],
            [
                'name'  => __('Newsletters'),
                'icon'  => 'fal fa-newspaper',
                'route' => '',
                'count' => $organisation->commsStats->number_mailshots_type_newsletter ?? 0
            ],
            [
                'name'  => __('Marketing Mailshots'),
                'icon'  => 'fal fa-mail-bulk',
                'route' => '',
                'count' => $organisation->commsStats->number_mailshots_type_marketing ?? 0
            ],
            [
                'name'  => __('Prospects Mailshots'),
                'icon'  => 'fal fa-phone-volume',
                'route' => '',
                'count' => $organisation->commsStats->number_mailshots_type_invite ?? 0
            ],
            [
                'name'  => __('Abandoned Cart Mailshots'),
                'icon'  => 'fal fa-scroll-old',
                'route' => '',
                'count' => $organisation->commsStats->number_mailshots_type_abandoned_cart ?? 0
            ],
            [
                'name'  => __('Email Bulk Runs'),
                'icon'  => 'fal fa-raygun',
                'route' => '',
                'count' => $organisation->commsStats->number_bulk_runs ?? 0
            ],
            [
                'name'  => __('Email Addresses'),
                'icon'  => 'fal fa-envelope',
                'route' => '',
                'count' => $organisation->commsStats->number_email_addresses ?? 0
            ],
            [
                'name'  => __('Dispatched Emails'),
                'icon'  => 'fal fa-paper-plane',
                'route' => '',
                'count' => $organisation->commsStats->number_dispatched_emails ?? 0
            ],

        ];
    }

    protected function getCatalogueSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Shops'),
                'icon'  => 'fal fa-store-alt',
                'route' => route('grp.org.overview.shops.index', $organisation),
                'count' => $organisation->catalogueStats->number_shops ?? 0
            ],
            [
                'name'  => __('Departments'),
                'icon'  => 'fal fa-folder-tree',
                'route' => route('grp.org.overview.departments.index', $organisation),
                'count' => $organisation->catalogueStats->number_departments ?? 0
            ],
            [
                'name'  => __('Families'),
                'icon'  => 'fal fa-folder',
                'route' => '',
                'count' => $organisation->catalogueStats->number_families ?? 0
            ],
            [
                'name'  => __('Products'),
                'icon'  => 'fal fa-boxes',
                'route' => route('grp.org.overview.products.index', $organisation->slug),
                'count' => $organisation->catalogueStats->number_products ?? 0
            ],
            [
                'name'  => __('Collections'),
                'icon'  => 'fal fa-album-collection',
                'route' => '',
                'count' => $organisation->catalogueStats->number_collections ?? 0
            ],
        ];
    }

    protected function getBillablesSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Rentals'),
                'icon'  => 'fal fa-garage',
                'route' => '',
                'count' => $organisation->catalogueStats->number_rentals ?? 0
            ],
            [
                'name'  => __('Charges'),
                'icon'  => 'fal fa-charging-station',
                'route' => '',
                'count' => $organisation->catalogueStats->number_assets_type_charge ?? 0
            ],
            [
                'name'  => __('Services'),
                'icon'  => 'fal fa-concierge-bell',
                'route' => '',
                'count' => $organisation->catalogueStats->number_services ?? 0
            ],
        ];
    }

    protected function getOffersSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Campaigns'),
                'icon'  => 'fal fa-comment-dollar',
                'route' => '',
                'count' => $organisation->discountsStats->number_offer_campaigns ?? 0
            ],
            [
                'name'  => __('Offers'),
                'icon'  => 'fal fa-badge-percent',
                'route' => '',
                'count' => $organisation->discountsStats->number_offers ?? 0
            ],
        ];
    }

    protected function getWebSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Websites'),
                'icon'  => 'fal fa-globe',
                'route' => '',
                'count' => $organisation->webStats->number_websites ?? 0
            ],
            [
                'name'  => __('Webpages'),
                'icon'  => 'fal fa-browser',
                'route' => '',
                'count' => $organisation->webStats->number_webpages ?? 0
            ],
            [
                'name'  => __('Banners'),
                'icon'  => 'fal fa-sign',
                'route' => '',
                'count' => $organisation->webStats->number_banners ?? 0
            ],
        ];
    }

    protected function getCrmSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Customers'),
                'icon'  => 'fal fa-user',
                'route' => route('grp.org.overview.customers.index', $organisation->slug),
                'count' => $organisation->crmStats->number_customers ?? 0
            ],
            [
                'name'  => __('Prospects'),
                'icon'  => 'fal fa-user-plus',
                'route' => '',
                'count' => $organisation->crmStats->number_prospects ?? 0
            ],
            [
                'name'  => __('Web Users'),
                'icon'  => 'fal fa-user-circle',
                'route' => '',
                'count' => $organisation->crmStats->number_web_users ?? 0
            ],
        ];
    }

    protected function getOrderingSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Orders in Basket'),
                'icon'  => 'fal fa-shopping-basket',
                'route' => route('grp.org.overview.orders_in_basket.index', $organisation->slug),
                'count' => $organisation->orderingStats->number_orders_state_creating ?? 0
            ],
            [
                'name'  => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
                'route' => route('grp.org.overview.orders.index', $organisation->slug),
                'count' => $organisation->orderingStats->number_orders ?? 0
            ],
            [
                'name'  => __('Purges'),
                'icon'  => 'fal fa-trash-alt',
                'route' => '',
                'count' => $organisation->orderingStats->number_purges ?? 0
            ],
            [
                'name'  => __('Delivery Notes'),
                'icon'  => 'fal fa-truck',
                'route' => '',
                'count' => $organisation->orderingStats->number_delivery_notes ?? 0
            ],
            [
                'name'  => __('Transactions'),
                'icon'  => 'fal fa-exchange-alt',
                'route' => '',
                'count' => $organisation->orderingStats->number_invoice_transactions ?? 0
            ],
        ];
    }

    protected function getInventorySections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Org Stocks'),
                'icon'  => 'fal fa-warehouse',
                'route' => '',
                'count' => $organisation->inventoryStats->number_org_stocks ?? 0 // need hydrator
            ],
            [
                'name'  => __('Org Stock Families'),
                'icon'  => 'fal fa-boxes-alt',
                'route' => '',
                'count' => $organisation->inventoryStats->number_org_stock_families ?? 0 // need hydrator
            ],
            [
                'name'  => __('Org Stock Movements'),
                'icon'  => 'fal fa-dolly',
                'route' => '',
                'count' => $organisation->inventoryStats->number_org_stock_movements ?? 0 // need hydrator
            ],
            [
                'name'  => __('Warehouses'),
                'icon'  => 'fal fa-warehouse-alt',
                'route' => '',
                'count' => $organisation->inventoryStats->number_warehouses ?? 0
            ],
            [
                'name'  => __('Warehouses Areas'),
                'icon'  => 'fal fa-industry-alt',
                'route' => '',
                'count' => $organisation->inventoryStats->number_warehouse_areas ?? 0
            ],
            [
                'name'  => __('Locations'),
                'icon'  => 'fal fa-location-arrow',
                'route' => '',
                'count' => $organisation->inventoryStats->number_locations ?? 0
            ],
        ];
    }

    protected function getFulfilmentSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Pallets'),
                'icon'  => 'fal fa-pallet',
                'route' => '',
                'count' => $organisation->fulfilmentStats->number_pallets ?? 0
            ],
            [
                'name'  => __("Customer'S SKUs"),
                'icon'  => 'fal fa-box-open',
                'route' => '',
                'count' => $organisation->fulfilmentStats->number_stored_items ?? 0
            ],
            [
                'name'  => __('Stock Deliveries'),
                'icon'  => 'fal fa-truck-loading',
                'route' => '',
                'count' => $organisation->procurementStats->number_stock_deliveries ?? 0 // hydrator ?? 0
            ],
            [
                'name'  => __('Pallet Deliveries'),
                'icon'  => 'fal fa-pallet-alt',
                'route' => '',
                'count' => $organisation->fulfilmentStats->number_pallet_deliveries ?? 0
            ],
            [
                'name'  => __('Pallet Returns'),
                'icon'  => 'fal fa-forklift',
                'route' => '',
                'count' => $organisation->fulfilmentStats->number_pallet_returns ?? 0
            ],
        ];
    }

    protected function getProcurementSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Agents'),
                'icon'  => 'fal fa-people-arrows',
                'route' => '',
                'count' => $organisation->procurementStats->number_org_agents ?? 0
            ],
            [
                'name'  => __('Suppliers'),
                'icon'  => 'fal fa-person-dolly',
                'route' => '',
                'count' => $organisation->procurementStats->number_org_suppliers ?? 0
            ],
            [
                'name'  => __('Supplier Products'),
                'icon'  => 'fal fa-users-class',
                'route' => '',
                'count' => $organisation->procurementStats->number_org_supplier_products ?? 0
            ],
            [
                'name'  => __('Purchase Orders'),
                'icon'  => 'fal fa-clipboard-list',
                'route' => '',
                'count' => $organisation->procurementStats->number_purchase_orders ?? 0
            ],
        ];
    }

    protected function getHumanResourcesSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Workplaces'),
                'icon'  => 'fal fa-building',
                'route' => '',
                'count' => $organisation->humanResourcesStats->number_workplaces ?? 0
            ],
            [
                'name'  => __('Responsibilities'),
                'icon'  => 'fal fa-clipboard-list-check',
                'route' => '',
                'count' => $organisation->humanResourcesStats->number_job_positions ?? 0
            ],
            [
                'name'  => __('Employees'),
                'icon'  => 'fal fa-user-hard-hat',
                'route' => '',
                'count' => $organisation->humanResourcesStats->number_employees ?? 0
            ],
            [
                'name'  => __('Clocking Machines'),
                'icon'  => 'fal fa-chess-clock',
                'route' => '',
                'count' => $organisation->humanResourcesStats->number_clocking_machines ?? 0
            ],
            [
                'name'  => __('Timesheets'),
                'icon'  => 'fal fa-stopwatch',
                'route' => '',
                'count' => $organisation->humanResourcesStats->number_timesheets ?? 0 // need hydrator
            ],
        ];
    }

    protected function getAccountingSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Accounts'),
                'icon'  => 'fal fa-money-check-alt',
                'route' => '',
                'count' => $organisation->accountingStats->number_payment_accounts ?? 0
            ],
            [
                'name'  => __('Invoices'),
                'icon'  => 'fal fa-file-invoice-dollar',
                'route' => route('grp.org.accounting.invoices.index', $organisation->slug),
                'count' => $organisation->orderingStats->number_invoices_type_invoice ?? 0
            ],
            [
                'name'  => __('Refunds'),
                'icon'  => 'fal fa-file-minus',
                'route' => route('grp.org.accounting.refunds.index', $organisation->slug),
                'count' => $organisation->orderingStats->number_invoices_type_refund ?? 0
            ],
            [
                'name'  => __('Payments'),
                'icon'  => 'fal fa-coin',
                'route' => '',
                'count' => $organisation->accountingStats->number_payments ?? 0
            ],
            [
                'name'  => __('Deleted Invoices'),
                'icon'  => 'fal fa-eraser',
                'route' => route('grp.org.accounting.deleted_invoices.index', $organisation->slug),
                'count' => $organisation->orderingStats->number_deleted_invoices ?? 0
            ],
            [
                'name'  => __('Customer Balances'),
                'icon'  => 'fal fa-piggy-bank',
                'route' => '',
                'count' => $organisation->accountingStats->number_customers_with_balances ?? 0 // need stats for this
            ],
        ];
    }

}
