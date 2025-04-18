<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 22:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Overview;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\OverviewResource;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetGroupOverview extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Group $group): AnonymousResourceCollection
    {
        $sections = $this->getSection($group);

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

    public function getSection(Group $group): array
    {
        return [

            __('Sysadmin')                    => $this->getSysAdminSections($group),
            __('Comms').' & '.__('Marketing') => $this->getCommsAndMarketingSections($group),
            __('Catalogue')                   => $this->getCatalogueSections($group),
            __('Billables')                   => $this->getBillablesSections($group),
            __('Offers')                      => $this->getOffersSections($group),
            __('Web')                         => $this->getWebSections($group),
            __('CRM')                         => $this->getCrmSections($group),
            __('Ordering')                    => $this->getOrderingSections($group),
            __('Accounting')                  => $this->getAccountingSections($group),
            __('Inventory')                   => $this->getInventorySections($group),
            __('Fulfilment')                  => $this->getFulfilmentSections($group),
            __('Procurement')                 => $this->getProcurementSections($group),
            __('Human Resources')             => $this->getHumanResourcesSections($group)
        ];
    }

    protected function getSysAdminSections(Group $group): array
    {
        return [
            [
                'name'  => __('Changelog'),
                'icon'  => 'fal fa-history',
                'route' => route('grp.overview.sysadmin.changelog.index'),
                'count' => $group->sysadminStats->number_audits ?? 0
            ],
            [
                'name'  => __('Users'),
                'icon'  => 'fal fa-user-circle',
                'route' => route('grp.sysadmin.users.all.index'),
                'count' => $group->sysadminStats->number_users ?? 0
            ],
            [
                'name'  => __('Guests'),
                'icon'  => 'fal fa-user-alien',
                'route' => route('grp.sysadmin.guests.index'),
                'count' => $group->sysadminStats->number_guests ?? 0
            ],
            [
                'name'  => __('User Requests'),
                'icon'  => 'fal fa-road',
                'route' => route('grp.sysadmin.analytics.request.index'),
                'count' => $group->sysadminStats->number_user_requests ?? 0
            ],
        ];
    }

    protected function getCommsAndMarketingSections(Group $group): array
    {
        return [
            [
                'name'  => __('Post Rooms'),
                'icon'  => 'fal fa-booth-curtain',
                'route' => route('grp.overview.comms-marketing.post-rooms.index'),
                'count' => $group->commsStats->number_post_rooms ?? 0
            ],
            [
                'name'  => __('Outboxes'),
                'icon'  => 'fal fa-inbox-out',
                'route' => route('grp.overview.comms-marketing.outboxes.index'),
                'count' => $group->commsStats->number_outboxes ?? 0
            ],
            [
                'name'  => __('Newsletters'),
                'icon'  => 'fal fa-newspaper',
                'route' => route('grp.overview.comms-marketing.newsletters.index'),
                'count' => $group->commsStats->number_mailshots_type_newsletter ?? 0
            ],
            [
                'name'  => __('Marketing Mailshots'),
                'icon'  => 'fal fa-mail-bulk',
                'route' => route('grp.overview.comms-marketing.marketing-mailshots.index'),
                'count' => $group->commsStats->number_mailshots_type_marketing ?? 0
            ],
            [
                'name'  => __('Invite mailshots'),
                'icon'  => 'fal fa-phone-volume',
                'route' => route('grp.overview.comms-marketing.invite-mailshots.index'),
                'count' => $group->commsStats->number_mailshots_type_invite ?? 0
            ],
            [
                'name'  => __('Abandoned Cart mailshots'),
                'icon'  => 'fal fa-scroll-old',
                'route' => route('grp.overview.comms-marketing.abandoned-cart-mailshots.index'),
                'count' => $group->commsStats->number_mailshots_type_abandoned_cart ?? 0
            ],
            [
                'name'  => __('Email Bulk Runs'),
                'icon'  => 'fal fa-raygun',
                'route' => route('grp.overview.comms-marketing.email-bulk-runs.index'),
                'count' => $group->commsStats->number_bulk_runs ?? 0
            ],
            [
                'name'  => __('Email Addresses'),
                'icon'  => 'fal fa-envelope',
                'route' => route('grp.overview.comms-marketing.email-addresses.index'), // real route index & show in group
                'count' => $group->commsStats->number_email_addresses ?? 0
            ],
            [
                'name'  => __('Dispatched Emails'),
                'icon'  => 'fal fa-paper-plane',
                'route' => route('grp.overview.comms-marketing.dispatched-emails.index'),
                'count' => $group->commsStats->number_dispatched_emails ?? 0
            ],

        ];
    }

    protected function getCatalogueSections(Group $group): array
    {
        return [
            [
                'name'  => __('Departments'),
                'icon'  => 'fal fa-folder-tree',
                'route' => route('grp.overview.catalogue.departments.index'),
                'count' => $group->catalogueStats->number_departments ?? 0
            ],
            [
                'name'  => __('Families'),
                'icon'  => 'fal fa-folder',
                'route' => route('grp.overview.catalogue.families.index'),
                'count' => $group->catalogueStats->number_families ?? 0
            ],
            [
                'name'  => __('Products'),
                'icon'  => 'fal fa-boxes',
                'route' => route('grp.overview.catalogue.products.index'),
                'count' => $group->catalogueStats->number_products ?? 0
            ],
            [
                'name'  => __('Collections'),
                'icon'  => 'fal fa-album-collection',
                'route' => route('grp.overview.catalogue.collections.index'),
                'count' => $group->catalogueStats->number_collections ?? 0
            ],
        ];
    }

    protected function getBillablesSections(Group $group): array
    {
        return [
            // [
            //     'name' => __('Shipping'),
            //     'icon' => 'fal fa-shipping-fast',
            //     'route' => 'grp.overview.billables.shipping.index',
            //     'count' => $group->catalogueStats->number_shipping_zone_schemas ?? 0
            // ],
            [
                'name'  => __('Rentals'),
                'icon'  => 'fal fa-garage',
                'route' => route('grp.overview.billables.rentals.index'),
                'count' => $group->catalogueStats->number_rentals ?? 0
            ],
            [
                'name'  => __('Charges'),
                'icon'  => 'fal fa-charging-station',
                'route' => route('grp.overview.billables.charges.index'),
                'count' => $group->catalogueStats->number_assets_type_charge ?? 0
            ],
            [
                'name'  => __('Services'),
                'icon'  => 'fal fa-concierge-bell',
                'route' => route('grp.overview.billables.services.index'),
                'count' => $group->catalogueStats->number_services ?? 0
            ],
        ];
    }

    protected function getOffersSections(Group $group): array
    {
        return [
            [
                'name'  => __('Campaigns'),
                'icon'  => 'fal fa-comment-dollar',
                'route' => route('grp.overview.offer.campaigns.index'),
                'count' => $group->discountsStats->number_offer_campaigns ?? 0
            ],
            [
                'name'  => __('Offers'),
                'icon'  => 'fal fa-badge-percent',
                'route' => route('grp.overview.offer.offers.index'),
                'count' => $group->discountsStats->number_offers ?? 0
            ],
        ];
    }

    protected function getWebSections(Group $group): array
    {
        return [
            [
                'name'  => __('Websites'),
                'icon'  => 'fal fa-globe',
                'route' => route('grp.overview.web.websites.index'),
                'count' => $group->webStats->number_websites ?? 0
            ],
            [
                'name'  => __('Webpages'),
                'icon'  => 'fal fa-browser',
                'route' => route('grp.overview.web.webpages.index'),
                'count' => $group->webStats->number_webpages ?? 0
            ],
            [
                'name'  => __('Banners'),
                'icon'  => 'fal fa-sign',
                'route' => route('grp.overview.web.banners.index'),
                'count' => $group->webStats->number_banners ?? 0
            ],
        ];
    }

    protected function getCrmSections(Group $group): array
    {
        return [
            [
                'name'  => __('Customers'),
                'icon'  => 'fal fa-user',
                'route' => route('grp.overview.crm.customers.index'),
                'count' => $group->crmStats->number_customers ?? 0
            ],
            [
                'name'  => __('Prospects'),
                'icon'  => 'fal fa-user-plus',
                'route' => route('grp.overview.crm.prospects.index'),
                'count' => $group->crmStats->number_prospects ?? 0
            ],
            [
                'name'  => __('Web Users'),
                'icon'  => 'fal fa-user-circle',
                'route' => route('grp.overview.crm.web-users.index'),
                'count' => $group->crmStats->number_web_users ?? 0
            ],
        ];
    }

    protected function getOrderingSections(Group $group): array
    {
        return [
            [
                'name'  => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
                'route' => route('grp.overview.ordering.orders.index'),
                'count' => $group->orderingStats->number_orders ?? 0
            ],
            [
                'name'  => __('Purges'),
                'icon'  => 'fal fa-trash-alt',
                'route' => route('grp.overview.ordering.purges.index'),
                'count' => $group->orderingStats->number_purges ?? 0
            ],
            [
                'name'  => __('Delivery Notes'),
                'icon'  => 'fal fa-truck',
                'route' => route('grp.overview.ordering.delivery_notes.index'),
                'count' => $group->orderingStats->number_delivery_notes ?? 0
            ],
            [
                'name'  => __('Transactions'),
                'icon'  => 'fal fa-exchange-alt',
                'route' => route('grp.overview.ordering.transactions.index'),
                'count' => $group->orderingStats->number_invoice_transactions ?? 0
            ],
        ];
    }


    protected function getInventorySections(Group $group): array
    {
        return [
            [
                'name'  => __('Warehouses'),
                'icon'  => 'fal fa-warehouse-alt',
                'route' => route('grp.overview.inventory.warehouses.index'),
                'count' => $group->inventoryStats->number_warehouses ?? 0
            ],
            [
                'name'  => __('Warehouses Areas'),
                'icon'  => 'fal fa-industry-alt',
                'route' => route('grp.overview.inventory.warehouses-areas.index'),
                'count' => $group->inventoryStats->number_warehouse_areas ?? 0
            ],
            [
                'name'  => __('Locations'),
                'icon'  => 'fal fa-location-arrow',
                'route' => route('grp.overview.inventory.locations.index'),
                'count' => $group->inventoryStats->number_locations ?? 0
            ],
            [
                'name'  => __('SKUs'),
                'icon'  => 'fal fa-inventory',
                'route' => route('grp.goods.stocks.index'),
                'count' => $group->goodsStats->number_stocks ?? 0
            ],
            [
                'name'  => __('SKUs Families'),
                'icon'  => 'fal fa-box',
                'route' => route('grp.goods.stock-families.index'),
                'count' => $group->goodsStats->number_stock_families ?? 0
            ],
            [
                'name'  => __('SKU Movements'),
                'icon'  => 'fal fa-dolly',
                'route' => route('grp.overview.inventory.org-stock-movements.index'),
                'count' => $group->inventoryStats->number_org_stock_movements ?? 0
            ],

        ];
    }

    protected function getFulfilmentSections(Group $group): array
    {
        return [
            [
                'name'  => __('Pallets'),
                'icon'  => 'fal fa-pallet',
                'route' => route('grp.overview.fulfilment.pallets.index'),
                'count' => $group->fulfilmentStats->number_pallets ?? 0
            ],
            [
                'name'  => __("Customer'S SKUs"),
                'icon'  => 'fal fa-box-open',
                'route' => route('grp.overview.fulfilment.stored-items.index'),
                'count' => $group->fulfilmentStats->number_stored_items ?? 0
            ],
            [
                'name'  => __('Stock Deliveries'),
                'icon'  => 'fal fa-truck-loading',
                'route' => '',
                'count' => $group->procurementStats->number_stock_deliveries ?? 0
            ],
            [
                'name'  => __('Pallet Deliveries'),
                'icon'  => 'fal fa-pallet-alt',
                'route' => route('grp.overview.fulfilment.pallet-deliveries.index'),
                'count' => $group->fulfilmentStats->number_pallet_deliveries ?? 0
            ],
            [
                'name'  => __('Pallet Returns'),
                'icon'  => 'fal fa-forklift',
                'route' => '',
                'count' => $group->fulfilmentStats->number_pallet_returns ?? 0
            ],
        ];
    }

    protected function getProcurementSections(Group $group): array
    {
        return [
            [
                'name'  => __('Agents'),
                'icon'  => 'fal fa-people-arrows',
                'route' => route('grp.supply-chain.agents.index'),
                'count' => $group->supplyChainStats->number_agents ?? 0
            ],
            [
                'name'  => __('Suppliers'),
                'icon'  => 'fal fa-person-dolly',
                'route' => route('grp.supply-chain.suppliers.index'),
                'count' => $group->supplyChainStats->number_suppliers ?? 0
            ],
            [
                'name'  => __('Supplier Products'),
                'icon'  => 'fal fa-users-class',
                'route' => route('grp.supply-chain.supplier_products.index'),
                'count' => $group->supplyChainStats->number_supplier_products ?? 0
            ],
            [
                'name'  => __('Purchase Orders'),
                'icon'  => 'fal fa-clipboard-list',
                'route' => route('grp.overview.procurement.purchase-orders.index'),
                'count' => $group->supplyChainStats->number_purchase_orders ?? 0
            ],
        ];
    }

    protected function getAccountingSections($group): array
    {
        return [
            [
                'name'  => __('Accounts'),
                'icon'  => 'fal fa-money-check-alt',
                'route' => route('grp.overview.accounting.payment-accounts.index'),
                'count' => $group->accountingStats->number_payment_accounts ?? 0
            ],
            [
                'name'  => __('Invoices'),
                'icon'  => 'fal fa-file-invoice-dollar',
                'route' => route('grp.overview.accounting.invoices.index'),
                'count' => $group->orderingStats->number_invoices_type_invoice ?? 0
            ],
            [
                'name'  => __('Refunds'),
                'icon'  => 'fal fa-file-minus',
                'route' => route('grp.overview.accounting.refunds.index'),
                'count' => $group->orderingStats->number_invoices_type_refund ?? 0
            ],
            [
                'name'  => __('Deleted invoices'),
                'icon'  => 'fal fa-eraser',
                'route' => route('grp.overview.accounting.deleted_invoices.index'),
                'count' => $group->orderingStats->number_deleted_invoices ?? 0
            ],
            [
                'name'  => __('Payments'),
                'icon'  => 'fal fa-coin',
                'route' => route('grp.overview.accounting.payments.index'),
                'count' => $group->accountingStats->number_payments ?? 0
            ],
            [
                'name'  => __('Customer Balances'),
                'icon'  => 'fal fa-piggy-bank',
                'route' => route('grp.overview.accounting.customer-balances.index'),
                'count' => $group->accountingStats->number_customers_with_balances ?? 0
            ],
        ];
    }

    protected function getHumanResourcesSections(Group $group): array
    {
        return [
            [
                'name'  => __('Workplaces'),
                'icon'  => 'fal fa-building',
                'route' => route('grp.overview.hr.workplaces.index'),
                'count' => $group->humanResourcesStats->number_workplaces ?? 0
            ],
            [
                'name'  => __('Responsibilities'),
                'icon'  => 'fal fa-clipboard-list-check',
                'route' => route('grp.overview.hr.responsibilities.index'),
                'count' => $group->humanResourcesStats->number_job_positions ?? 0
            ],
            [
                'name'  => __('Employees'),
                'icon'  => 'fal fa-user-hard-hat',
                'route' => route('grp.overview.hr.employees.index'),
                'count' => $group->humanResourcesStats->number_employees ?? 0
            ],
            [
                'name'  => __('Clocking Machines'),
                'icon'  => 'fal fa-chess-clock',
                'route' => route('grp.overview.hr.clocking-machines.index'),
                'count' => $group->humanResourcesStats->number_clocking_machines ?? 0
            ],
            [
                'name'  => __('Timesheets'),
                'icon'  => 'fal fa-stopwatch',
                'route' => route('grp.overview.hr.timesheets.index'),
                'count' => $group->humanResourcesStats->number_timesheets ?? 0
            ],
        ];
    }

}
