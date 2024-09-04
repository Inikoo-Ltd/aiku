<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Transfers\Aurora\FetchAuroraCustomerClients;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Helpers\Address;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrder extends FetchAurora
{
    protected function parseModel(): void
    {
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

        if ($this->auroraModelData->{'Order State'} == "InBasket") {
            return;
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
            $this->parsedData= null;
            return;
        }

        if ($parent->deleted_at and $this->auroraModelData->{'Order State'} == "Cancelled") {
            $this->parsedData= null;
            return;
        }

        $state = match ($this->auroraModelData->{'Order State'}) {
            "InWarehouse", "Packed" => OrderStateEnum::HANDLING,
            "PackedDone" => OrderStateEnum::PACKED,
            "Approved"   => OrderStateEnum::FINALISED,
            "Dispatched" => OrderStateEnum::DISPATCHED,
            "InBasket"   => OrderStateEnum::CREATING,
            default      => OrderStateEnum::SUBMITTED,
        };


        $status = match ($this->auroraModelData->{'Order State'}) {
            "Cancelled","Dispatched"  => OrderStatusEnum::SETTLED,
            "InBasket"   => OrderStatusEnum::CREATING,
            default      => OrderStatusEnum::PROCESSING,
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
                $this->auroraModelData->{'Order Invoiced Date'}   != "" or
                $this->auroraModelData->{'Order Dispatched Date'} != ""
            ) {
                $stateWhenCancelled = OrderStateEnum::FINALISED;
            } elseif (
                $this->auroraModelData->{'Order Packed Date'}      != "" or
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

        $taxCategory=$this->parseTaxCategory($this->auroraModelData->{'Order Tax Category Key'});

        $shop = $parent->shop;

        $date = Carbon::parse($this->auroraModelData->{'Order Date'});

        $orgExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->organisation->currency, $date);
        $grpExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->group->currency, $date);


        $this->parsedData["order"] = [
            'date'            => $date,
            'submitted_at'    => $this->parseDate($this->auroraModelData->{'Order Submitted by Customer Date'}),
            'in_warehouse_at' => $this->parseDate($this->auroraModelData->{'Order Send to Warehouse Date'}),
            'packed_at'       => $this->parseDate($this->auroraModelData->{'Order Packed Date'}),
            'finalised_at'    => $this->parseDate($this->auroraModelData->{'Order Packed Done Date'}),
            'dispatched_at'   => $this->parseDate($this->auroraModelData->{'Order Dispatched Date'}),
            'handing_type'    => $handingType,
            'billing_locked'  => $billingLocked,
            'delivery_locked' => $deliveryLocked,
            'tax_category_id' => $taxCategory->id,

            "reference"          => $this->auroraModelData->{'Order Public ID'},
            'customer_reference' => (string)$this->auroraModelData->{'Order Customer Purchase Order ID'},
            "state"              => $state,
            "status"             => $status,
            "source_id"          => $this->organisation->id.':'.$this->auroraModelData->{'Order Key'},

            "created_at"     => $this->auroraModelData->{'Order Created Date'},
            "cancelled_at"   => $cancelled_at,
            "data"           => $data,
            'org_exchange'   => $orgExchange,
            'grp_exchange'   => $grpExchange,

            'gross_amount'       => $this->auroraModelData->{'Order Items Gross Amount'},
            'goods_amount'       => $this->auroraModelData->{'Order Items Net Amount'},
            'shipping_amount'    => $this->auroraModelData->{'Order Shipping Net Amount'},
            'charges_amount'     => $this->auroraModelData->{'Order Charges Net Amount'},
            'insurance_amount'   => $this->auroraModelData->{'Order Insurance Net Amount'},


            'net_amount'      => $this->auroraModelData->{'Order Total Net Amount'},
            'tax_amount'      => $this->auroraModelData->{'Order Total Tax Amount'},
            'total_amount'    => $this->auroraModelData->{'Order Total Amount'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),

        ];

        $billingAddressData = $this->parseAddress(
            prefix: "Order Invoice",
            auAddressData: $this->auroraModelData,
        );

        if (!$billingAddressData['country_id']) {
            $billingAddressData['country_id'] = $parent->addresses->first()->country_id;
        }

        $this->parsedData['order']["billing_address"] = new Address($billingAddressData);

        if ($handingType == OrderHandingTypeEnum::SHIPPING) {
            $deliveryAddressData = $this->parseAddress(
                prefix: "Order Delivery",
                auAddressData: $this->auroraModelData,
            );

            if (!$deliveryAddressData['country_id']) {
                $deliveryAddressData['country_id'] = $parent->addresses->first()->country_id;
            }

            $this->parsedData['order']["delivery_address"] = new Address(
                $deliveryAddressData,
            );
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
