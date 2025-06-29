<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Transfers\Aurora\FetchAuroraAdjustments;
use App\Actions\Transfers\Aurora\FetchAuroraAgents;
use App\Actions\Transfers\Aurora\FetchAuroraBarcodes;
use App\Actions\Transfers\Aurora\FetchAuroraCharges;
use App\Actions\Transfers\Aurora\FetchAuroraClockingMachines;
use App\Actions\Transfers\Aurora\FetchAuroraCollections;
use App\Actions\Transfers\Aurora\FetchAuroraCustomerClients;
use App\Actions\Transfers\Aurora\FetchAuroraCustomers;
use App\Actions\Transfers\Aurora\FetchAuroraDeletedCustomers;
use App\Actions\Transfers\Aurora\FetchAuroraDeletedEmployees;
use App\Actions\Transfers\Aurora\FetchAuroraDeletedLocations;
use App\Actions\Transfers\Aurora\FetchAuroraDeletedStocks;
use App\Actions\Transfers\Aurora\FetchAuroraDeletedSuppliers;
use App\Actions\Transfers\Aurora\FetchAuroraDeliveryNotes;
use App\Actions\Transfers\Aurora\FetchAuroraDepartments;
use App\Actions\Transfers\Aurora\FetchAuroraDispatchedEmails;
use App\Actions\Transfers\Aurora\FetchAuroraEmailBulkRuns;
use App\Actions\Transfers\Aurora\FetchAuroraEmailOngoingRuns;
use App\Actions\Transfers\Aurora\FetchAuroraEmails;
use App\Actions\Transfers\Aurora\FetchAuroraEmployees;
use App\Actions\Transfers\Aurora\FetchAuroraFamilies;
use App\Actions\Transfers\Aurora\FetchAuroraHistoricAssets;
use App\Actions\Transfers\Aurora\FetchAuroraHistoricSupplierProducts;
use App\Actions\Transfers\Aurora\FetchAuroraIngredients;
use App\Actions\Transfers\Aurora\FetchAuroraInvoiceCategories;
use App\Actions\Transfers\Aurora\FetchAuroraInvoices;
use App\Actions\Transfers\Aurora\FetchAuroraLocations;
use App\Actions\Transfers\Aurora\FetchAuroraMailshots;
use App\Actions\Transfers\Aurora\FetchAuroraMasterFamilies;
use App\Actions\Transfers\Aurora\FetchAuroraOfferCampaigns;
use App\Actions\Transfers\Aurora\FetchAuroraOfferComponents;
use App\Actions\Transfers\Aurora\FetchAuroraOffers;
use App\Actions\Transfers\Aurora\FetchAuroraOrders;
use App\Actions\Transfers\Aurora\FetchAuroraOrgPaymentServiceProviders;
use App\Actions\Transfers\Aurora\FetchAuroraPaymentAccounts;
use App\Actions\Transfers\Aurora\FetchAuroraPayments;
use App\Actions\Transfers\Aurora\FetchAuroraPollOptions;
use App\Actions\Transfers\Aurora\FetchAuroraPolls;
use App\Actions\Transfers\Aurora\FetchAuroraProducts;
use App\Actions\Transfers\Aurora\FetchAuroraProspects;
use App\Actions\Transfers\Aurora\FetchAuroraPurges;
use App\Actions\Transfers\Aurora\FetchAuroraQueries;
use App\Actions\Transfers\Aurora\FetchAuroraSalesChannels;
use App\Actions\Transfers\Aurora\FetchAuroraServices;
use App\Actions\Transfers\Aurora\FetchAuroraShippers;
use App\Actions\Transfers\Aurora\FetchAuroraShippingZones;
use App\Actions\Transfers\Aurora\FetchAuroraShippingZoneSchemas;
use App\Actions\Transfers\Aurora\FetchAuroraShops;
use App\Actions\Transfers\Aurora\FetchAuroraStocks;
use App\Actions\Transfers\Aurora\FetchAuroraSupplierProducts;
use App\Actions\Transfers\Aurora\FetchAuroraSuppliers;
use App\Actions\Transfers\Aurora\FetchAuroraTradeUnits;
use App\Actions\Transfers\Aurora\FetchAuroraUploads;
use App\Actions\Transfers\Aurora\FetchAuroraWarehouseAreas;
use App\Actions\Transfers\Aurora\FetchAuroraWarehouses;
use App\Actions\Transfers\Aurora\FetchAuroraWebpages;
use App\Actions\Transfers\Aurora\FetchAuroraWebsites;
use App\Actions\Transfers\Aurora\FetchAuroraWebUsers;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Billables\Charge;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailOngoingRun;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferComponent;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Goods\Ingredient;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Query;
use App\Models\Helpers\TaxCategory;
use App\Models\Helpers\Timezone;
use App\Models\Helpers\Upload;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\Masters\MasterProductCategory;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\Ordering\SalesChannel;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Production\Production;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\HistoricSupplierProduct;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait WithAuroraParsers
{
    use WithAuroraCleaners;
    use WithAuroraHumanResourcesParsers;
    use WithAuroraSysAdminParsers;

    protected function parseDate($value): ?string
    {
        return ($value != '' && $value != '0000-00-00 00:00:00' && $value != '2018-00-00 00:00:00') ? Carbon::parse($value)->format('Y-m-d') : null;
    }

    protected function parseDatetime($value): ?Carbon
    {
        return ($value != '' && $value != '0000-00-00 00:00:00' && $value != '2018-00-00 00:00:00') ? Carbon::parse($value) : null;
    }

    protected function parseTaxNumber(?string $number, ?int $countryID, array $rawData = []): ?array
    {
        if (!$number) {
            return null;
        }

        $country = Country::find($countryID);

        if ($country and (Country::isInEU($country->code) or $country->code == 'GB')) {
            if (Str::startsWith(strtolower($number), strtolower($country->code))) {
                $number = substr($number, 2);
            }
        }

        $data = [];

        if (Arr::get($rawData, 'Customer Tax Number Validation Source') == 'Online') {
            $data = [
                'data'   => [],
                'valid'  => Arr::get($rawData, 'Customer Tax Number Valid') == 'Yes',
                'status' => match (Arr::get($rawData, 'Customer Tax Number Valid')) {
                    'Yes' => TaxNumberStatusEnum::VALID,
                    'No' => TaxNumberStatusEnum::INVALID,
                    default => TaxNumberStatusEnum::UNKNOWN
                }

            ];

            $message = Arr::get($rawData, 'Customer Tax Number Validation Message');
            $message = preg_replace('/VIES$/', '', $message);

            if ($data['status'] != TaxNumberStatusEnum::VALID and $message != '') {
                $data['data']['exception']['message'] = $message;
            }

            $data['checked_at'] = Arr::get($rawData, 'Customer Tax Number Validation Date');
            if ($data['status'] == TaxNumberStatusEnum::INVALID) {
                $data['invalid_checked_at'] = Arr::get($rawData, 'Customer Tax Number Validation Date');
            }


            if (Arr::get($rawData, 'Customer Tax Number Valid') == 'API_Down'
                or preg_match('/failed to load|server is busy/i', $message)
            ) {
                $data['external_service_failed_at'] = Arr::get($rawData, 'Customer Tax Number Validation Date');
            }

            $data['data']['name']    = Arr::get($rawData, 'Customer Tax Number Registered Name');
            $data['data']['address'] = Arr::get($rawData, 'Customer Tax Number Registered Address');
        }


        return array_merge(
            $data,
            [
                'number'     => $number,
                'country_id' => $country?->id,
            ]
        );
    }

    protected function parseLanguageID($locale): int|null
    {
        if ($locale != '') {
            try {
                return Language::where(
                    'code',
                    match ($locale) {
                        'zh_CN.UTF-8' => 'zh-CN',
                        default => substr($locale, 0, 2)
                    }
                )->first()->id;
            } catch (Exception) {
                //print "Locale $locale not found\n";

                return null;
            }
        }

        return null;
    }


    protected function parseCurrencyID($currencyCode): int|null
    {
        if ($currencyCode != '') {
            if ($currencyCode == 'LEU') {
                $currencyCode = 'RON';
            }

            return Currency::where('code', $currencyCode)->firstOrFail()->id;
        }

        return null;
    }

    protected function parseTimezoneID($timezone): int|null
    {
        if ($timezone != '') {
            return Timezone::where('name', $timezone)->first()->id;
        }

        return null;
    }

    protected function parseCountryID($country, $source = ''): int|null
    {
        if ($country != '') {
            try {
                if (strlen($country) == 2) {
                    return Country::withTrashed()->where('code', $country)->firstOrFail()->id;
                } elseif (strlen($country) == 3) {
                    return Country::withTrashed()->where('iso3', $country)->firstOrFail()->id;
                } else {
                    return Country::withTrashed()->where('name', $country)->firstOrFail()->id;
                }
            } catch (Exception) {
                abort(404, "Country not found: $country ($source)\n");
            }
        }

        return null;
    }

    protected function parseAddress($prefix, $auAddressData): array
    {
        $country = $auAddressData->{$prefix.' Address Country 2 Alpha Code'} ?? null;
        if ($country == 'XX') {
            $country = null;
        }

        $addressData                        = [];
        $addressData['address_line_1']      = (string)Str::of($auAddressData->{$prefix.' Address Line 1'} ?? null)->limit(251);
        $addressData['address_line_2']      = (string)Str::of($auAddressData->{$prefix.' Address Line 2'} ?? null)->limit(251);
        $addressData['sorting_code']        = (string)Str::of($auAddressData->{$prefix.' Address Sorting Code'} ?? null)->limit(187);
        $addressData['postal_code']         = (string)Str::of($auAddressData->{$prefix.' Address Postal Code'} ?? null)->limit(187);
        $addressData['locality']            = (string)Str::of($auAddressData->{$prefix.' Address Locality'} ?? null)->limit(187);
        $addressData['dependent_locality']  = (string)Str::of($auAddressData->{$prefix.' Address Dependent Locality'} ?? null)->limit(187);
        $addressData['administrative_area'] = (string)Str::of($auAddressData->{$prefix.' Address Administrative Area'} ?? null)->limit(187);
        foreach ($addressData as $key => $value) {
            if ($value) {
                $value = $this->sanitiseText($value);
            }
            $addressData[$key] = $value;
        }
        $addressData['country_id'] = $this->parseCountryID($country, $prefix);


        return $addressData;
    }


    public function parseShop($sourceId): Shop
    {
        $shop = Shop::where('source_id', $sourceId)->first();
        if (!$shop) {
            $sourceData = explode(':', $sourceId);
            $shop       = FetchAuroraShops::run($this->organisationSource, $sourceData[1]);
        }

        return $shop;
    }

    public function parseWebsite($sourceId): Website
    {
        $website = Website::where('source_id', $sourceId)->first();
        if (!$website) {
            $sourceData = explode(':', $sourceId);
            $website    = FetchAuroraWebsites::run($this->organisationSource, $sourceData[1]);
        }

        return $website;
    }


    public function parseWebpage($sourceId): Webpage|null
    {
        $webpage = Webpage::where('source_id', $sourceId)->first();
        if (!$webpage) {
            $sourceData = explode(':', $sourceId);
            $webpage    = FetchAuroraWebpages::run($this->organisationSource, $sourceData[1]);
        }

        return $webpage;
    }


    public function parseHistoricAsset($organisation, $productKey): HistoricAsset|null
    {
        $historicAsset = HistoricAsset::where('source_id', $organisation->id.':'.$productKey)->first();

        if ($historicAsset) {
            return $historicAsset;
        }


        return FetchAuroraHistoricAssets::run($this->organisationSource, $productKey);
    }

    public function parseHistoricSupplierProduct($organisationID, $productKey): HistoricSupplierProduct|null
    {
        $historicSupplierProduct = HistoricSupplierProduct::where('source_id', $organisationID.':'.$productKey)->first();

        if ($historicSupplierProduct) {
            return $historicSupplierProduct;
        }
        $historicSupplierProduct = HistoricSupplierProduct::whereJsonContains('sources->historic_supplier_parts', $organisationID.':'.$productKey)->first();
        if ($historicSupplierProduct) {
            return $historicSupplierProduct;
        }

        return FetchAuroraHistoricSupplierProducts::run($this->organisationSource, $productKey);
    }

    public function parseSupplierProduct(string $sourceId): ?SupplierProduct
    {
        $supplierProduct = SupplierProduct::where('source_id', $sourceId)->first();
        if ($supplierProduct) {
            return $supplierProduct;
        }

        $supplierProduct = SupplierProduct::whereJsonContains('sources->supplier_parts', $sourceId)->first();
        if ($supplierProduct) {
            return $supplierProduct;
        }


        $sourceData = explode(':', $sourceId);

        return FetchAuroraSupplierProducts::run($this->organisationSource, $sourceData[1]);
    }

    public function parseAsset(string $sourceId): Product
    {
        $product = Product::where('source_id', $sourceId)->first();
        if (!$product) {
            $sourceData = explode(':', $sourceId);

            $product = FetchAuroraProducts::run($this->organisationSource, $sourceData[1]);
        }

        return $product;
    }


    public function parseProduct(string $sourceId): ?Product
    {
        $product = Product::where('source_id', $sourceId)->first();
        if (!$product) {
            $sourceData = explode(':', $sourceId);

            $product = FetchAuroraProducts::run($this->organisationSource, $sourceData[1]);
        }

        return $product;
    }

    public function parseService(string $sourceId): Service|Rental|null
    {
        $service = Service::withTrashed()->where('source_id', $sourceId)->first();
        if (!$service) {
            $sourceData = explode(':', $sourceId);
            $service    = FetchAuroraServices::run($this->organisationSource, $sourceData[1]);
        }

        return $service;
    }


    public function parseDepartment(string $sourceId): ?ProductCategory
    {
        $department = ProductCategory::where('type', ProductCategoryTypeEnum::DEPARTMENT)->where('source_department_id', $sourceId)->first();
        if (!$department) {
            $sourceData = explode(':', $sourceId);
            $department = FetchAuroraDepartments::run($this->organisationSource, $sourceData[1]);
        }

        return $department;
    }

    public function parseFamily(string $sourceId): ?ProductCategory
    {
        $family = ProductCategory::where('type', ProductCategoryTypeEnum::FAMILY)->where('source_family_id', $sourceId)->first();
        if (!$family) {
            $sourceData = explode(':', $sourceId);
            $family     = FetchAuroraFamilies::run($this->organisationSource, $sourceData[1]);
        }

        return $family;
    }

    public function parseMasterFamily(string $sourceId): ?MasterProductCategory
    {
        $masterFamily = MasterProductCategory::where('type', MasterProductCategoryTypeEnum::FAMILY)->where('source_family_id', $sourceId)->first();
        if (!$masterFamily) {
            $sourceData = explode(':', $sourceId);
            $masterFamily     = FetchAuroraMasterFamilies::run($this->organisationSource, $sourceData[1]);
        }

        return $masterFamily;
    }


    public function parseCustomer(string $sourceId): ?Customer
    {
        if (!$sourceId) {
            return null;
        }

        $customer = Customer::withTrashed()->where('source_id', $sourceId)->first();
        if (!$customer) {
            $sourceData = explode(':', $sourceId);
            $customer   = FetchAuroraCustomers::run($this->organisationSource, $sourceData[1]);
            if (!$customer) {
                $customer = FetchAuroraDeletedCustomers::run($this->organisationSource, $sourceData[1]);
            }
        }

        return $customer;
    }

    public function parseCustomerClient(string $sourceId): ?CustomerClient
    {
        if (!$sourceId) {
            return null;
        }

        $customerClient = CustomerClient::withTrashed()->where('source_id', $sourceId)->first();
        if (!$customerClient) {
            $sourceData = explode(':', $sourceId);
            $customerClient   = FetchAuroraCustomerClients::run($this->organisationSource, $sourceData[1]);
        }

        return $customerClient;
    }

    public function parseWebUser(string $sourceId): ?WebUser
    {
        if (!$sourceId) {
            return null;
        }
        $webUser = WebUser::withTrashed()->where('source_id', $sourceId)->first();
        if (!$webUser) {
            $sourceData = explode(':', $sourceId);
            $webUser    = FetchAuroraWebUsers::run($this->organisationSource, $sourceData[1]);
        }

        return $webUser;
    }


    public function parseSupplier($sourceID): ?Supplier
    {
        $supplier = Supplier::withTrashed()->where('source_id', $sourceID)->first();
        if ($supplier) {
            return $supplier;
        }

        $supplier = Supplier::withTrashed()
            ->whereJsonContains('sources->suppliers', $sourceID)
            ->first();
        if ($supplier) {
            return $supplier;
        }


        $sourceData = explode(':', $sourceID);
        $supplier   = FetchAuroraSuppliers::run($this->organisationSource, $sourceData[1]);


        if (!$supplier) {
            $supplier = FetchAuroraDeletedSuppliers::run($this->organisationSource, $sourceData[1]);
        }

        return $supplier;
    }

    public function parseAgent($sourceID): ?Agent
    {
        $agent = Agent::withTrashed()->where('source_id', $sourceID)->first();
        if ($agent) {
            return $agent;
        }

        $agent = Agent::withTrashed()
            ->whereJsonContains('sources->agents', $sourceID)
            ->first();
        if ($agent) {
            return $agent;
        }


        $sourceData = explode(':', $sourceID);

        return FetchAuroraAgents::run($this->organisationSource, $sourceData[1]);
    }

    public function parseOrgStock($sourceId): ?OrgStock
    {
        $orgStock   = OrgStock::withTrashed()->where('source_id', $sourceId)->first();
        $sourceData = explode(':', $sourceId);

        if (!$orgStock) {
            $res      = FetchAuroraStocks::run($this->organisationSource, $sourceData[1]);
            $orgStock = $res['orgStock'];
        }

        if (!$orgStock) {
            $res      = FetchAuroraDeletedStocks::run($this->organisationSource, $sourceData[1]);
            $orgStock = $res['orgStock'];
        }

        return $orgStock;
    }

    public function parseOrgSupplierProduct($sourceId): ?OrgSupplierProduct
    {
        $orgSupplierProduct = OrgSupplierProduct::where('source_id', $sourceId)->first();
        $sourceData         = explode(':', $sourceId);

        if (!$orgSupplierProduct) {
            $supplierProduct = FetchAuroraSupplierProducts::run($this->organisationSource, $sourceData[1]);
            if ($supplierProduct) {
                $orgSupplierProduct = OrgSupplierProduct::where('supplier_product_id', $supplierProduct->id)->first();
            }
        }

        return $orgSupplierProduct;
    }


    public function parseStock($sourceId): ?Stock
    {
        $stock = Stock::withTrashed()->where('source_id', $sourceId)->first();
        if ($stock) {
            return $stock;
        }


        $stock = Stock::withTrashed()
            ->whereJsonContains('sources->stocks', $sourceId)
            ->first();
        if ($stock) {
            return $stock;
        }


        $sourceData = explode(':', $sourceId);

        $res   = FetchAuroraStocks::run($this->organisationSource, $sourceData[1]);
        $stock = $res['stock'];

        if (!$stock) {
            $res   = FetchAuroraDeletedStocks::run($this->organisationSource, $sourceData[1]);
            $stock = $res['stock'];
        }

        return $stock;
    }

    public function parseLocation($sourceId, $organisationSource): ?Location
    {
        $location = Location::where('source_id', $sourceId)->first();
        if (!$location) {
            $sourceData = explode(':', $sourceId);
            $location   = FetchAuroraLocations::run($organisationSource, $sourceData[1]);
        }
        if (!$location) {
            $location = FetchAuroraDeletedLocations::run($organisationSource, $sourceData[1]);
        }

        return $location;
    }

    public function parseOrder($sourceId, $forceTransactions = true): ?Order
    {
        if (!$sourceId) {
            return null;
        }

        $order = Order::where('source_id', $sourceId)->first();
        if (!$order) {
            $sourceData = explode(':', $sourceId);
            $order      = FetchAuroraOrders::run($this->organisationSource, $sourceData[1], forceWithTransactions: $forceTransactions);
        }

        return $order;
    }

    public function parseDeliveryNote($sourceId): ?DeliveryNote
    {
        if (!$sourceId) {
            return null;
        }

        $deliveryNote = DeliveryNote::withTrashed()->where('source_id', $sourceId)->first();
        if (!$deliveryNote) {
            $sourceData   = explode(':', $sourceId);
            $deliveryNote = FetchAuroraDeliveryNotes::run($this->organisationSource, $sourceData[1]);
        }

        return $deliveryNote;
    }

    public function parseInvoice($sourceId): ?Invoice
    {
        if (!$sourceId) {
            return null;
        }

        $invoice = Invoice::withTrashed()->where('source_id', $sourceId)->first();
        if (!$invoice) {
            $sourceData = explode(':', $sourceId);
            $invoice    = FetchAuroraInvoices::run($this->organisationSource, $sourceData[1]);
        }

        return $invoice;
    }

    public function parseTransaction($sourceId): ?Transaction
    {
        return Transaction::where('source_id', $sourceId)->first();
    }

    public function parseTransactionNoProduct($sourceId): ?Transaction
    {
        return Transaction::where('source_alt_id', $sourceId)->first();
    }

    public function parseIngredient($sourceId): ?Ingredient
    {
        $ingredient = Ingredient::where('source_id', $sourceId)->first();

        if (!$ingredient) {
            $ingredient = Ingredient::whereJsonContains('sources->ingredients', $sourceId)->first();
        }

        if (!$ingredient) {
            $sourceData = explode(':', $sourceId);
            $ingredient = FetchAuroraIngredients::run($this->organisationSource, $sourceData[1]);
        }

        return $ingredient;
    }

    public function parseShipper($sourceId): Shipper
    {
        $shipper = Shipper::where('source_id', $sourceId)->first();
        if (!$shipper) {
            $shipper = FetchAuroraShippers::run($this->organisationSource, $sourceId);
        }

        return $shipper;
    }

    public function parseOrgPaymentServiceProvider($sourceId): OrgPaymentServiceProvider
    {
        $orgPaymentServiceProvider = OrgPaymentServiceProvider::where('source_id', $sourceId)->first();
        if (!$orgPaymentServiceProvider) {
            $sourceData                = explode(':', $sourceId);
            $orgPaymentServiceProvider = FetchAuroraOrgPaymentServiceProviders::run($this->organisationSource, $sourceData[1]);
        }

        return $orgPaymentServiceProvider;
    }

    public function parsePaymentAccount($sourceId): ?PaymentAccount
    {
        $paymentAccount = PaymentAccount::where('source_id', $sourceId)->first();
        if (!$paymentAccount) {
            $sourceData     = explode(':', $sourceId);
            $paymentAccount = FetchAuroraPaymentAccounts::run($this->organisationSource, $sourceData[1]);
        }

        return $paymentAccount;
    }

    public function parseEmployee($sourceId): ?Employee
    {
        $employee   = Employee::withTrashed()->where('source_id', $sourceId)->first();
        $sourceData = explode(':', $sourceId);
        if (!$employee) {
            $employee = FetchAuroraEmployees::run($this->organisationSource, $sourceData[1]);
        }
        if (!$employee) {
            $employee = FetchAuroraDeletedEmployees::run($this->organisationSource, $sourceData[1]);
        }

        return $employee;
    }


    public function parseClockingMachine($sourceId): ?ClockingMachine
    {
        $clockingMachine = ClockingMachine::where('source_id', $sourceId)->first();
        if (!$clockingMachine) {
            $sourceData = explode(':', $sourceId);

            $clockingMachine = FetchAuroraClockingMachines::run($this->organisationSource, $sourceData[1]);
        }

        return $clockingMachine;
    }

    public function parseMailshot($sourceId): ?Mailshot
    {
        if (!$sourceId) {
            return null;
        }

        $mailshot = Mailshot::where('source_id', $sourceId)->first();
        if (!$mailshot) {
            $sourceData = explode(':', $sourceId);
            $mailshot   = FetchAuroraMailshots::run($this->organisationSource, $sourceData[1]);
        }

        return $mailshot;
    }

    public function parseEmailBulkRun($sourceId): ?EmailBulkRun
    {
        if (!$sourceId) {
            return null;
        }

        $emailBulkRun = EmailBulkRun::where('source_id', $sourceId)->first();
        if (!$emailBulkRun) {
            $sourceData   = explode(':', $sourceId);
            $emailBulkRun = FetchAuroraEmailBulkRuns::run($this->organisationSource, $sourceData[1]);
        }

        return $emailBulkRun;
    }

    public function parseProspect($sourceId): ?Prospect
    {
        if (!$sourceId) {
            return null;
        }

        $prospect = Prospect::where('source_id', $sourceId)->first();
        if (!$prospect) {
            $sourceData = explode(':', $sourceId);
            $prospect   = FetchAuroraProspects::run($this->organisationSource, $sourceData[1]);
        }

        return $prospect;
    }

    public function parseDispatchedEmail($sourceId): ?DispatchedEmail
    {
        $dispatchedEmail = DispatchedEmail::where('source_id', $sourceId)->first();
        if (!$dispatchedEmail) {
            $sourceData      = explode(':', $sourceId);
            $dispatchedEmail = FetchAuroraDispatchedEmails::run($this->organisationSource, $sourceData[1]);
        }

        return $dispatchedEmail;
    }

    public function parseWarehouse($sourceId): ?Warehouse
    {
        if (!$sourceId) {
            return null;
        }

        $warehouse = Warehouse::withTrashed()->where('source_id', $sourceId)->first();
        if (!$warehouse) {
            $sourceData = explode(':', $sourceId);
            $warehouse  = FetchAuroraWarehouses::run($this->organisationSource, $sourceData[1]);
        }

        return $warehouse;
    }

    public function parsePayment($sourceId): ?Payment
    {
        $payment = Payment::withTrashed()->where('source_id', $sourceId)->first();
        if (!$payment) {
            $sourceData = explode(':', $sourceId);
            $payment    = FetchAuroraPayments::run($this->organisationSource, $sourceData[1]);
        }

        return $payment;
    }

    public function parseTradeUnit($sourceSlug, $sourceId): ?TradeUnit
    {
        $tradeUnit = TradeUnit::withTrashed()->where('source_slug', $sourceSlug)->first();
        if (!$tradeUnit) {
            $tradeUnit = FetchAuroraTradeUnits::run($this->organisationSource, $sourceId);
        }

        return $tradeUnit;
    }

    public function parseShippingZoneSchema($sourceId): ShippingZoneSchema
    {
        $shippingZoneSchema = ShippingZoneSchema::where('source_id', $sourceId)->first();
        if (!$shippingZoneSchema) {
            $sourceData         = explode(':', $sourceId);
            $shippingZoneSchema = FetchAuroraShippingZoneSchemas::run($this->organisationSource, $sourceData[1]);
        }

        return $shippingZoneSchema;
    }

    public function parseShippingZone($sourceId): ?ShippingZone
    {
        $shippingZone = ShippingZone::where('source_id', $sourceId)->first();
        if (!$shippingZone) {
            $sourceData   = explode(':', $sourceId);
            $shippingZone = FetchAuroraShippingZones::run($this->organisationSource, $sourceData[1]);
        }

        return $shippingZone;
    }

    public function parseCharge($sourceId): ?Charge
    {
        $charge = Charge::where('source_id', $sourceId)->first();
        if (!$charge) {
            $sourceData = explode(':', $sourceId);
            $charge     = FetchAuroraCharges::run($this->organisationSource, $sourceData[1]);
        }

        return $charge;
    }

    public function parseAdjustment($sourceId): Adjustment
    {
        $adjustment = Adjustment::where('source_id', $sourceId)->first();
        if (!$adjustment) {
            $sourceData = explode(':', $sourceId);
            $adjustment = FetchAuroraAdjustments::run($this->organisationSource, $sourceData[1]);
        }

        return $adjustment;
    }

    public function parseOffer($sourceId): ?Offer
    {
        if (!$sourceId) {
            return null;
        }

        $offer = Offer::withTrashed()->where('source_id', $sourceId)->first();
        if (!$offer) {
            $sourceData = explode(':', $sourceId);
            $offer      = FetchAuroraOffers::run($this->organisationSource, $sourceData[1]);
        }

        return $offer;
    }

    public function parseOfferCampaign($sourceId): ?OfferCampaign
    {
        if (!$sourceId) {
            return null;
        }

        $offerCampaign = OfferCampaign::where('source_id', $sourceId)->first();
        if (!$offerCampaign) {
            $sourceData    = explode(':', $sourceId);
            $offerCampaign = FetchAuroraOfferCampaigns::run($this->organisationSource, $sourceData[1]);
        }

        return $offerCampaign;
    }

    public function parseOfferComponent($sourceId): ?OfferComponent
    {
        if (!$sourceId) {
            return null;
        }

        $offerComponent = OfferComponent::withTrashed()->where('source_id', $sourceId)->first();
        if (!$offerComponent) {
            $sourceData     = explode(':', $sourceId);
            $offerComponent = FetchAuroraOfferComponents::run($this->organisationSource, $sourceData[1]);
        }

        return $offerComponent;
    }


    public function parseTaxCategory($auroraTaxCategoryId): TaxCategory
    {
        $auroraTaxCategoryId = match ($auroraTaxCategoryId) {
            25, 30, 38, 39 => 1,//Outside
            27, 28, 29, 42, 43 => 2,//EU_VTC
            11, 26, 40, 41 => 3,//Exempt
            default => $auroraTaxCategoryId
        };

        $taxCategory = TaxCategory::where('source_id', $auroraTaxCategoryId)->first();
        if (!$taxCategory) {
            dd($auroraTaxCategoryId);
        }

        return $taxCategory;
    }

    public function parseUpload($sourceId): ?Upload
    {
        if (!$sourceId) {
            return null;
        }

        $upload = Upload::where('source_id', $sourceId)->first();
        if (!$upload) {
            $sourceData = explode(':', $sourceId);
            $upload     = FetchAuroraUploads::run($this->organisationSource, $sourceData[1]);
        }

        return $upload;
    }

    public function parseProcurementOrderParent($auroraParentType, $sourceId): null|OrgAgent|OrgSupplier|OrgPartner|Production
    {
        $sourceData = explode(':', $sourceId);


        if ($auroraParentType == 'Agent') {
            $parent = $this->parseAgent(
                $sourceId
            );

            return OrgAgent::where('organisation_id', $sourceData[0])->where('agent_id', $parent->id)->first();
        } else {
            $orgPartner = Production::whereJsonContains('sources->suppliers', $sourceId)->first();
            if ($orgPartner) {
                return $orgPartner;
            }


            $orgPartner = OrgPartner::whereJsonContains('sources->suppliers', $sourceId)
                ->first();
            if ($orgPartner) {
                return $orgPartner;
            }


            $supplier = $this->parseSupplier($sourceId);
            if ($supplier) {
                return $supplier->orgSuppliers()->where('organisation_id', $sourceData[0])->first();
            }
        }


        return null;
    }

    public function parseBarcode($sourceId): Barcode|null
    {
        $barcode = Barcode::where('source_id', $sourceId)->first();
        if (!$barcode) {
            $sourceData = explode(':', $sourceId);
            $barcode    = FetchAuroraBarcodes::run($this->organisationSource, $sourceData[1]);
        }

        return $barcode;
    }

    public function parsePoll($sourceId): ?Poll
    {
        $poll = Poll::withTrashed()->where('source_id', $sourceId)->first();
        if (!$poll) {
            $sourceData = explode(':', $sourceId);
            $poll       = FetchAuroraPolls::run($this->organisationSource, $sourceData[1]);
        }

        return $poll;
    }

    public function parsePollOption($sourceId): ?PollOption
    {
        $pollOption = PollOption::where('source_id', $sourceId)->first();
        if (!$pollOption) {
            $sourceData = explode(':', $sourceId);
            $pollOption = FetchAuroraPollOptions::run($this->organisationSource, $sourceData[1]);
        }

        return $pollOption;
    }

    public function parsePurge($sourceId): ?Purge
    {
        $purge = Purge::where('source_id', $sourceId)->first();
        if (!$purge) {
            $sourceData = explode(':', $sourceId);
            $purge      = FetchAuroraPurges::run($this->organisationSource, $sourceData[1]);
        }

        return $purge;
    }

    public function parseSalesChannel(string $sourceId): ?SalesChannel
    {
        $salesChannel = SalesChannel::where('source_id', $sourceId)->first();
        if ($salesChannel) {
            return $salesChannel;
        }

        $salesChannel = SalesChannel::whereJsonContains('sources->sales_channels', $sourceId)->first();
        if ($salesChannel) {
            return $salesChannel;
        }


        $sourceData = explode(':', $sourceId);

        return FetchAuroraSalesChannels::run($this->organisationSource, $sourceData[1]);
    }

    public function parseOutbox(string $sourceId): ?Outbox
    {
        return Outbox::whereJsonContains('sources->outboxes', $sourceId)->first();
    }

    public function parseEmailRun($sourceId): ?EmailBulkRun
    {
        if (!$sourceId) {
            return null;
        }

        $emailRun = EmailBulkRun::where('source_id', $sourceId)->first();
        if (!$emailRun) {
            $sourceData = explode(':', $sourceId);
            $emailRun   = FetchAuroraEmailBulkRuns::run($this->organisationSource, $sourceData[1]);
        }

        return $emailRun;
    }

    public function parseEmail($sourceId): ?Email
    {
        if (!$sourceId) {
            return null;
        }

        $email = Email::where('source_id', $sourceId)->first();
        if (!$email) {
            $sourceData = explode(':', $sourceId);
            $email      = FetchAuroraEmails::run($this->organisationSource, $sourceData[1]);
        }

        return $email;
    }

    public function parseWarehouseArea($sourceId): ?WarehouseArea
    {
        if (!$sourceId) {
            return null;
        }

        $warehouseArea = WarehouseArea::where('source_id', $sourceId)->first();
        if (!$warehouseArea) {
            $sourceData    = explode(':', $sourceId);
            $warehouseArea = FetchAuroraWarehouseAreas::run($this->organisationSource, $sourceData[1]);
        }

        return $warehouseArea;
    }

    public function parseEmailOngoingRun($sourceId): ?EmailOngoingRun
    {
        if (!$sourceId) {
            return null;
        }

        $emailOngoingRun = EmailOngoingRun::where('source_id', $sourceId)->first();
        if (!$emailOngoingRun) {
            $sourceData      = explode(':', $sourceId);
            $emailOngoingRun = FetchAuroraEmailOngoingRuns::run($this->organisationSource, $sourceData[1]);
        }

        return $emailOngoingRun;
    }

    public function parseQuery($sourceId): ?Query
    {
        if (!$sourceId) {
            return null;
        }

        $query = Query::where('source_id', $sourceId)->first();
        if (!$query) {
            $sourceData      = explode(':', $sourceId);
            $query = FetchAuroraQueries::run($this->organisationSource, $sourceData[1]);
        }

        return $query;
    }

    public function parseInvoiceCategory($sourceId): ?InvoiceCategory
    {
        if (!$sourceId) {
            return null;
        }

        $invoiceCategory = InvoiceCategory::where('source_id', $sourceId)->first();
        if (!$invoiceCategory) {
            $sourceData      = explode(':', $sourceId);
            $invoiceCategory = FetchAuroraInvoiceCategories::run($this->organisationSource, $sourceData[1]);
        }

        return $invoiceCategory;
    }

    public function parseCollection(string $sourceId): ?Collection
    {
        $collection = Collection::where('source_id', $sourceId)->first();
        if (!$collection) {
            $sourceData = explode(':', $sourceId);
            $collection     = FetchAuroraCollections::run($this->organisationSource, $sourceData[1]);
        }

        return $collection;
    }


}
