<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Transfers\Aurora\FetchAuroraCustomerClients;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrder extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Order Store Key'});

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return;
        }

        $platform = null;
        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        }

        $deliveryData = [];

        if ($this->auroraModelData->{'Order For Collection'} == 'Yes') {
            $deliveryData['collection'] = true;
        } else {
            if ($this->auroraModelData->{'Order Email'}) {
                $deliveryData['email'] = $this->auroraModelData->{'Order Email'};
            }
            if ($this->auroraModelData->{'Order Telephone'}) {
                $deliveryData['phone'] = $this->auroraModelData->{'Order Telephone'};
            }
        }


        if ($this->auroraModelData->{'Order Customer Client Key'} != "") {
            $parent = FetchAuroraCustomerClients::run(
                $this->organisationSource,
                $this->auroraModelData->{'Order Customer Client Key'},
            );

            if ($parent == null and $this->auroraModelData->{'Order State'} == "Cancelled") {
                return;
            }
        } else {
            $parent = $this->parseCustomer(
                $this->organisation->id.':'.$this->auroraModelData->{'Order Customer Key'}
            );
        }

        $this->parsedData["parent"] = $parent;
        if (!$parent) {
            $this->parsedData = null;

            return;
        }

        if ($parent->deleted_at and $this->auroraModelData->{'Order State'} == "Cancelled") {
            $this->parsedData = null;

            return;
        }

        $state = match ($this->auroraModelData->{'Order State'}) {
            "InWarehouse", "Packed" => OrderStateEnum::HANDLING,
            "PackedDone" => OrderStateEnum::PACKED,
            "Approved" => OrderStateEnum::FINALISED,
            "Dispatched" => OrderStateEnum::DISPATCHED,
            "InBasket" => OrderStateEnum::CREATING,
            default => OrderStateEnum::SUBMITTED,
        };


        $status = match ($this->auroraModelData->{'Order State'}) {
            "Cancelled", "Dispatched" => OrderStatusEnum::SETTLED,
            "InBasket" => OrderStatusEnum::CREATING,
            default => OrderStatusEnum::PROCESSING,
        };


        $data = [
            "delivery_data" => $deliveryData
        ];

        $cancelled_at = null;
        if ($this->auroraModelData->{'Order State'} == "Cancelled") {
            $cancelled_at = $this->auroraModelData->{'Order Cancelled Date'};
            if (!$cancelled_at) {
                $cancelled_at = $this->auroraModelData->{'Order Date'};
            }

            if (
                $this->auroraModelData->{'Order Invoiced Date'} != "" or
                $this->auroraModelData->{'Order Dispatched Date'} != ""
            ) {
                $stateWhenCancelled = OrderStateEnum::FINALISED;
            } elseif (
                $this->auroraModelData->{'Order Packed Date'} != "" or
                $this->auroraModelData->{'Order Packed Done Date'} != ""
            ) {
                $stateWhenCancelled = OrderStateEnum::PACKED;
            } elseif (
                $this->auroraModelData->{'Order Send to Warehouse Date'} != ""
            ) {
                $stateWhenCancelled = OrderStateEnum::HANDLING;
            } else {
                $stateWhenCancelled = OrderStateEnum::CREATING;
            }


            $data['cancelled'] = [
                'state' => $stateWhenCancelled
            ];
        }

        $handingType = OrderHandingTypeEnum::SHIPPING;
        if ($this->auroraModelData->{'Order For Collection'} == 'Yes') {
            $handingType = OrderHandingTypeEnum::COLLECTION;
        }

        $deliveryLocked = false;
        $billingLocked  = false;
        if (in_array($this->auroraModelData->{'Order State'}, ['Cancelled', 'Approved', 'Dispatched'])) {
            $deliveryLocked = true;
            $billingLocked  = true;
        }

        $taxCategory = $this->parseTaxCategory($this->auroraModelData->{'Order Tax Category Key'});

        $shop = $parent->shop;

        $date = Carbon::parse($this->auroraModelData->{'Order Date'});

        $orgExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->organisation->currency, $date);
        $grpExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->group->currency, $date);

        $salesChannel = null;

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            $salesChannel = $shop->group->salesChannels()->where('type', SalesChannelTypeEnum::NA)->first();
        } elseif ($this->auroraModelData->{'Order Source Key'}) {
            $salesChannel = $this->parseSalesChannel($this->organisation->id.':'.$this->auroraModelData->{'Order Source Key'});
        }


        $totalAmount = $this->auroraModelData->{'Order Total Amount'};

        $paymentAmount = null;
        if ($date->isBefore('2014-08-01')) {
            $hasPayments = DB::connection('aurora')
                ->table('Order Payment Bridge')
                ->select('Payment Key')
                ->where('Order Key', $this->auroraModelData->{'Order Key'})
                ->exists();

            if ($state == OrderStateEnum::DISPATCHED and !$hasPayments) {
                $paymentAmount = $totalAmount;
            }

            data_set($data, 'warnings.payments', [
                'msg' => 'No payments recorded for this order, assumed was paid',
            ]);
        }


        $this->parsedData["order"] = [
            'date'                   => $date,
            'submitted_at'           => $this->parseDatetime($this->auroraModelData->{'Order Submitted by Customer Date'}),
            'in_warehouse_at'        => $this->parseDatetime($this->auroraModelData->{'Order Send to Warehouse Date'}),
            'packed_at'              => $this->parseDatetime($this->auroraModelData->{'Order Packed Date'}),
            'finalised_at'           => $this->parseDatetime($this->auroraModelData->{'Order Packed Done Date'}),
            'dispatched_at'          => $this->parseDatetime($this->auroraModelData->{'Order Dispatched Date'}),
            'updated_by_customer_at' => $this->parseDatetime($this->auroraModelData->{'Order Last Updated by Customer'}),
            'handing_type'           => $handingType,
            'billing_locked'         => $billingLocked,
            'delivery_locked'        => $deliveryLocked,
            'tax_category_id'        => $taxCategory->id,

            "reference"          => $this->auroraModelData->{'Order Public ID'},
            'customer_reference' => (string)$this->auroraModelData->{'Order Customer Purchase Order ID'},
            "state"              => $state,
            "status"             => $status,
            "source_id"          => $this->organisation->id.':'.$this->auroraModelData->{'Order Key'},

            "created_at"   => $this->auroraModelData->{'Order Created Date'},
            "cancelled_at" => $cancelled_at,
            "data"         => $data,
            'org_exchange' => $orgExchange,
            'grp_exchange' => $grpExchange,

            'gross_amount'     => $this->auroraModelData->{'Order Items Gross Amount'},
            'goods_amount'     => $this->auroraModelData->{'Order Items Net Amount'},
            'shipping_amount'  => $this->auroraModelData->{'Order Shipping Net Amount'},
            'charges_amount'   => $this->auroraModelData->{'Order Charges Net Amount'},
            'insurance_amount' => $this->auroraModelData->{'Order Insurance Net Amount'},


            'net_amount'   => $this->auroraModelData->{'Order Total Net Amount'},
            'tax_amount'   => $this->auroraModelData->{'Order Total Tax Amount'},
            'total_amount' => $totalAmount,

            'fetched_at'      => now(),
            'last_fetched_at' => now(),

        ];

        if ($platform) {
            $this->parsedData["order"]['platform_id'] = $platform->id;
        }

        if ($paymentAmount) {
            $this->parsedData["order"]['payment_amount'] = $paymentAmount;
        }

        if ($salesChannel) {
            $this->parsedData['order']['sales_channel_id'] = $salesChannel->id;
        }

        $billingAddressData = $this->parseAddress(
            prefix: "Order Invoice",
            auAddressData: $this->auroraModelData,
        );

        if (!$billingAddressData['country_id']) {
            $billingAddressData['country_id'] = $parent->addresses->first()->country_id;
        }

        $deliveryAddressData = $this->parseAddress(
            prefix: "Order Delivery",
            auAddressData: $this->auroraModelData,
        );

        if (!$deliveryAddressData['country_id']) {
            $deliveryAddressData['country_id'] = $parent->addresses->first()->country_id;
        }


        if ($billingAddressData['address_line_1'] == '' and
            $billingAddressData['address_line_2'] == '' and
            $billingAddressData['sorting_code'] == '' and
            $billingAddressData['postal_code'] == '' and
            $billingAddressData['dependent_locality'] == '' and
            $billingAddressData['locality'] == '' and
            $billingAddressData['administrative_area'] == '' and
            $billingAddressData['country_id'] == $deliveryAddressData['country_id']

        ) {
            // if billing address is empty , use delivery address, it may have some data
            $billingAddressData = $deliveryAddressData;
        }

        if ($deliveryAddressData['address_line_1'] == '' and
            $deliveryAddressData['address_line_2'] == '' and
            $deliveryAddressData['sorting_code'] == '' and
            $deliveryAddressData['postal_code'] == '' and
            $deliveryAddressData['dependent_locality'] == '' and
            $deliveryAddressData['locality'] == '' and
            $deliveryAddressData['administrative_area'] == '' and
            $deliveryAddressData['country_id'] == $billingAddressData['country_id']

        ) {
            $deliveryAddressData = $billingAddressData;
        }


        $this->parsedData['order']["billing_address"] = new Address($billingAddressData);

        if ($handingType == OrderHandingTypeEnum::SHIPPING) {
            $this->parsedData['order']["delivery_address"] = new Address(
                $deliveryAddressData,
            );
        } else {
            $collectionAddress = $shop->collectionAddress;

            $this->parsedData['order']["delivery_address"] = $collectionAddress;
        }
    }

    protected function fetchData($id): object|null
    {
        return DB::connection("aurora")
            ->table("Order Dimension")
            ->where("Order Key", $id)
            ->first();
    }
}
