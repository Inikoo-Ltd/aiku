<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 18 Aug 2022 23:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Providers;

use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Gnikyt\BasicShopifyAPI\Options;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Facades\Actions;

/**
 * @method forPage(mixed $page, mixed $perPage)
 * @method count()
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('app.scope', function () {
            return 'aiku';
        });

        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->singleton(BasicShopifyAPI::class, function () {
            $opts    = app(Options::class);
            $tsClass = config('shopify-app.api_time_store');
            $lsClass = config('shopify-app.api_limit_store');
            $sdClass = config('shopify-app.api_deferrer');

            return new BasicShopifyAPI(
                $opts,
                new $tsClass(),
                new $lsClass(),
                new $sdClass()
            );
        });
    }


    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            Actions::registerCommands();
        }

        Vite::macro('flushPreloadedAssets', function () {
            $this->preloadedAssets = [];
        });

        Relation::morphMap(
            [
                // Accounting
                'Invoice'                       => 'App\Models\Accounting\Invoice',
                'InvoiceTransaction'            => 'App\Models\Accounting\InvoiceTransaction',
                'OrgPaymentServiceProvider'     => 'App\Models\Accounting\OrgPaymentServiceProvider',
                'OrgPaymentServiceProviderShop' => 'App\Models\Accounting\OrgPaymentServiceProviderShop',
                'Payment'                       => 'App\Models\Accounting\Payment',
                'PaymentAccount'                => 'App\Models\Accounting\PaymentAccount',
                'PaymentAccountShop'            => 'App\Models\Accounting\PaymentAccountShop',
                'PaymentServiceProvider'        => 'App\Models\Accounting\PaymentServiceProvider',

                // Assets
                'Country'                       => 'App\Models\Helpers\Country',
                'Currency'                      => 'App\Models\Helpers\Currency',
                'Language'                      => 'App\Models\Helpers\Language',
                'TariffCode'                    => 'App\Models\Helpers\TariffCode',
                'Timezone'                      => 'App\Models\Helpers\Timezone',

                // Billables
                'Charge'                        => 'App\Models\Billables\Charge',
                'Rental'                        => 'App\Models\Billables\Rental',
                'Service'                       => 'App\Models\Billables\Service',

                // CRM
                'Appointment'                   => 'App\Models\CRM\Appointment',
                'BackInStockReminder'           => 'App\Models\CRM\BackInStockReminder',
                'Customer'                      => 'App\Models\CRM\Customer',
                'CustomerNote'                  => 'App\Models\CRM\CustomerNote',
                'Favourite'                     => 'App\Models\CRM\Favourite',
                'Poll'                          => 'App\Models\CRM\Poll',
                'PollOption'                    => 'App\Models\CRM\PollOption',
                'PollReply'                     => 'App\Models\CRM\PollReply',
                'Prospect'                      => 'App\Models\CRM\Prospect',
                'WebUser'                       => 'App\Models\CRM\WebUser',

                // Dispatching
                'DeliveryNote'                  => 'App\Models\Dispatching\DeliveryNote',
                'DeliveryNoteItem'              => 'App\Models\Dispatching\DeliveryNoteItem',
                'PdfLabel'                      => 'App\Models\Dispatching\PdfLabel',
                'Picking'                       => 'App\Models\Dispatching\Picking',
                'Shipment'                      => 'App\Models\Dispatching\Shipment',
                'Shipper'                       => 'App\Models\Dispatching\Shipper',

                // Dropshipping
                'CustomerClient'                => 'App\Models\Dropshipping\CustomerClient',
                'Portfolio'                     => 'App\Models\Dropshipping\Portfolio',
                'ShopifyUser'                   => 'App\Models\Dropshipping\ShopifyUser',
                'TiktokUser'                    => 'App\Models\Dropshipping\TiktokUser',

                // Fulfilment
                'FulfilmentCustomer'            => 'App\Models\Fulfilment\FulfilmentCustomer',
                'Pallet'                        => 'App\Models\Fulfilment\Pallet',
                'PalletDelivery'                => 'App\Models\Fulfilment\PalletDelivery',
                'PalletReturn'                  => 'App\Models\Fulfilment\PalletReturn',
                'StoredItem'                    => 'App\Models\Fulfilment\StoredItem',
                'Fulfilment'                    => 'App\Models\Fulfilment\Fulfilment',
                'StoredItemMovement'            => 'App\Models\Fulfilment\StoredItemMovement',
                'PalletStoredItem'              => 'App\Models\Fulfilment\PalletStoredItem',
                'PalletDeliveryPallet'          => 'App\Models\Fulfilment\PalletDeliveryPallet',
                'MovementPallet'                => 'App\Models\Fulfilment\MovementPallet',
                'RentalAgreement'               => 'App\Models\Fulfilment\RentalAgreement',
                'RecurringBill'                 => 'App\Models\Fulfilment\RecurringBill',
                'Space'                         => 'App\Models\Fulfilment\Space',


                // Goods
                'Ingredient'                    => 'App\Models\Goods\Ingredient',
                'MasterAsset'                   => 'App\Models\Goods\MasterAsset',
                'MasterProductCategory'         => 'App\Models\Goods\MasterProductCategory',
                'MasterShop'                    => 'App\Models\Goods\MasterShop',
                'Stock'                         => 'App\Models\Goods\Stock',
                'StockFamily'                   => 'App\Models\Goods\StockFamily',
                'TradeUnit'                     => 'App\Models\Goods\TradeUnit',


                // Helpers
                'Address'                       => 'App\Models\Helpers\Address',
                'Audit'                         => 'App\Models\Helpers\Audit',
                'Barcode'                       => 'App\Models\Helpers\Barcode',
                'CurrencyExchange'              => 'App\Models\Helpers\CurrencyExchange',
                'Deployment'                    => 'App\Models\Helpers\Deployment',
                'Fetch'                         => 'App\Models\Transfers\Fetch',
                'FetchRecord'                   => 'App\Models\Transfers\FetchRecord',
                'Feedback'                      => 'App\Models\Helpers\Feedback',
                'Query'                         => 'App\Models\Helpers\Query',
                'SerialReference'               => 'App\Models\Helpers\SerialReference',
                'Snapshot'                      => 'App\Models\Helpers\Snapshot',
                'Tag'                           => 'App\Models\Helpers\Tag',
                'TaxNumber'                     => 'App\Models\Helpers\TaxNumber',
                'Upload'                        => 'App\Models\Helpers\Upload',
                'UploadRecord'                  => 'App\Models\Helpers\UploadRecord',
                'Media'                         => 'App\Models\Helpers\Media',
                'UniversalSearch'               => 'App\Models\Helpers\UniversalSearch',

                // Human Resources
                'JobPosition'                   => 'App\Models\HumanResources\JobPosition',
                'Employee'                      => 'App\Models\HumanResources\Employee',
                'Workplace'                     => 'App\Models\HumanResources\Workplace',
                'Clocking'                      => 'App\Models\HumanResources\Clocking',
                'ClockingMachine'               => 'App\Models\HumanResources\ClockingMachine',
                'EmployeeJobPosition'           => 'App\Models\HumanResources\EmployeeJobPosition',
                'TimeTracker'                   => 'App\Models\HumanResources\TimeTracker',

                // Inventory
                'Warehouse'                     => 'App\Models\Inventory\Warehouse',
                'WarehouseArea'                 => 'App\Models\Inventory\WarehouseArea',
                'Location'                      => 'App\Models\Inventory\Location',
                'OrgStock'                      => 'App\Models\Inventory\OrgStock',
                'LocationOrgStock'              => 'App\Models\Inventory\LocationOrgStock',
                'LostAndFoundStock'             => 'App\Models\Inventory\LostAndFoundStock',
                'OrgStockMovement'              => 'App\Models\Inventory\OrgStockMovement',
                'OrgStockFamily'                => 'App\Models\Inventory\OrgStockFamily',

                // Comms
                'DispatchedEmail'               => 'App\Models\Comms\DispatchedEmail',
                'EmailAddress'                  => 'App\Models\Comms\EmailAddress',
                'EmailTemplate'                 => 'App\Models\Comms\EmailTemplate',
                'EmailTrackingEvent'            => 'App\Models\Comms\EmailTrackingEvent',
                'PostRoom'                      => 'App\Models\Comms\PostRoom',
                'Mailshot'                      => 'App\Models\Comms\Mailshot',
                'Outbox'                        => 'App\Models\Comms\Outbox',
                'SenderEmail'                   => 'App\Models\Comms\SenderEmail',
                'SesNotification'               => 'App\Models\Comms\SesNotification',
                'EmailCopy'                     => 'App\Models\Comms\EmailCopy',
                'Email'                         => 'App\Models\Comms\Email',
                'EmailBulkRun'                  => 'App\Models\Comms\EmailBulkRun',
                'EmailOngoingRun'               => 'App\Models\Comms\EmailOngoingRun',
                'OutBoxHasSubscriber'           => 'App\Models\Comms\OutBoxHasSubscriber',


                // Catalogue
                'Shop'                          => 'App\Models\Catalogue\Shop',
                'ProductCategory'               => 'App\Models\Catalogue\ProductCategory',
                'Asset'                         => 'App\Models\Catalogue\Asset',
                'HistoricAsset'                 => 'App\Models\Catalogue\HistoricAsset',
                'Product'                       => 'App\Models\Catalogue\Product',
                'Collection'                    => 'App\Models\Catalogue\Collection',
                'Shipping'                      => 'App\Models\Catalogue\Shipping',

                // Discounts
                'Offer'                         => 'App\Models\Discounts\Offer',
                'OfferCampaign'                 => 'App\Models\Discounts\OfferCampaign',
                'OfferComponent'                => 'App\Models\Discounts\OfferComponent',

                // Notifications
                'FcmToken'                      => 'App\Models\Notifications\FcmToken',

                // Ordering
                'Adjustment'                    => 'App\Models\Ordering\Adjustment',
                'Order'                         => 'App\Models\Ordering\Order',
                'Purge'                         => 'App\Models\Ordering\Purge',
                'SalesChannel'                  => 'App\Models\Ordering\SalesChannel',
                'ShippingZone'                  => 'App\Models\Ordering\ShippingZone',
                'ShippingZoneSchema'            => 'App\Models\Ordering\ShippingZoneSchema',
                'Transaction'                   => 'App\Models\Ordering\Transaction',

                // Procurement
                'OrgAgent'                      => 'App\Models\Procurement\OrgAgent',
                'OrgSupplier'                   => 'App\Models\Procurement\OrgSupplier',
                'OrgSupplierProduct'            => 'App\Models\Procurement\OrgSupplierProduct',
                'PurchaseOrder'                 => 'App\Models\Procurement\PurchaseOrder',
                'PurchaseOrderTransaction'      => 'App\Models\Procurement\PurchaseOrderTransaction',
                'StockDelivery'                 => 'App\Models\Procurement\StockDelivery',
                'StockDeliveryItem'             => 'App\Models\Procurement\StockDeliveryItem',
                'OrgPartner'                    => 'App\Models\Procurement\OrgPartner',


                // Supply Chain
                'HistoricSupplierProduct'       => 'App\Models\SupplyChain\HistoricSupplierProduct',
                'SupplierProductTradeUnit'      => 'App\Models\SupplyChain\SupplierProductTradeUnit',
                'SupplierProduct'               => 'App\Models\SupplyChain\SupplierProduct',
                'Supplier'                      => 'App\Models\SupplyChain\Supplier',
                'Agent'                         => 'App\Models\SupplyChain\Agent',

                // Sysadmin
                'Admin'                         => 'App\Models\SysAdmin\Admin',
                'Group'                         => 'App\Models\SysAdmin\Group',
                'Guest'                         => 'App\Models\SysAdmin\Guest',
                'Organisation'                  => 'App\Models\SysAdmin\Organisation',
                'UserHasAuthorisedModels'       => 'App\Models\SysAdmin\UserHasAuthorisedModels',
                'User'                          => 'App\Models\SysAdmin\User',
                'Permission'                    => 'App\Models\SysAdmin\Permission',
                'Role'                          => 'App\Models\SysAdmin\Role',

                // Web
                'Website'                       => 'App\Models\Web\Website',
                'Webpage'                       => 'App\Models\Web\Webpage',
                'WebBlock'                      => 'App\Models\Web\WebBlock',
                'WebBlockType'                  => 'App\Models\Web\WebBlockType',
                'Banner'                        => 'App\Models\Web\Banner',

                //Production
                'Production'                    => 'App\Models\Production\Production',
                'RawMaterial'                   => 'App\Models\Production\RawMaterial',
                'ManufactureTask'               => 'App\Models\Production\ManufactureTask',
                'Artefact'                      => 'App\Models\Production\Artefact',
            ]
        );
    }
}
