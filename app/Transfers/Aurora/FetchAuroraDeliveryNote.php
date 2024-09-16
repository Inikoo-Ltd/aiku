<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStatusEnum;
use App\Models\Helpers\Address;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeliveryNote extends FetchAurora
{
    protected function parseModel(): void
    {
        if (!$this->auroraModelData->{'Delivery Note Order Key'}) {
            print "Warning delivery without order ".$this->auroraModelData->{'Delivery Note Key'}."  \n";

            return;
        }

        $order     = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Delivery Note Order Key'});

        $warehouseID=$this->auroraModelData->{'Delivery Note Warehouse Key'};
        if(!$warehouseID) {
            $warehouseID=1;
        }
        $warehouse = $this->parseWarehouse($this->organisation->id.':'.$warehouseID);


        if (!$order) {
            print "Delivery without invalid order key (not found) ".$this->auroraModelData->{'Delivery Note Order Key'}." - ".$this->auroraModelData->{'Delivery Note Key'}."  \n";

            return;
        }


        $this->parsedData["order"] = $order;

        $state = match ($this->auroraModelData->{'Delivery Note State'}) {
            'Picker Assigned' => DeliveryNoteStateEnum::IN_QUEUE,
            'Picking'         => DeliveryNoteStateEnum::PICKING,
            'Picked'          => DeliveryNoteStateEnum::PICKED,
            'Packing'         => DeliveryNoteStateEnum::PACKING,
            'Packed'          => DeliveryNoteStateEnum::PACKED,
            'Packed Done', 'Approved' => DeliveryNoteStateEnum::FINALISED,
            'Dispatched', 'Cancelled', 'Cancelled to Restock' => DeliveryNoteStateEnum::SETTLED,
            default => DeliveryNoteStateEnum::SUBMITTED,
        };

        $status = match ($this->auroraModelData->{'Delivery Note State'}) {
            'Dispatched' => DeliveryNoteStatusEnum::DISPATCHED,
            'Cancelled', 'Cancelled to Restock' => DeliveryNoteStatusEnum::CANCELLED,
            default => DeliveryNoteStatusEnum::HANDLING,
        };

        $cancelled_at = null;
        if ($this->auroraModelData->{'Delivery Note State'} == "Cancelled") {
            $cancelled_at = $this->auroraModelData->{'Delivery Note Date Cancelled'};
            if (!$cancelled_at) {
                $cancelled_at = $this->auroraModelData->{'Delivery Note Date'};
            }
        }


        /*
        $date = match ($state) {
            'packed' => $this->auroraModelData->{'Delivery Note Date Finish Packing'},
            default => $this->auroraModelData->{'Delivery Note Date Created'}
        };
        */

        $shipment  = null;
        $shipperID = null;
        if ($this->auroraModelData->{'Delivery Note Shipper Key'} and $fetchedShipment = $this->parseShipper($this->auroraModelData->{'Delivery Note Shipper Key'})) {
            $shipperID = $fetchedShipment->id;
        }


        if ($state == 'dispatched') {
            $shipmentCode = $this->auroraModelData->{'Delivery Note ID'};
            if ($this->auroraModelData->{'Delivery Note Shipper Consignment'}) {
                $shipmentCode = $this->auroraModelData->{'Delivery Note Shipper Consignment'};
            }

            $shipment = [
                'code'       => $shipmentCode,
                'tracking'   => $this->auroraModelData->{'Delivery Note Shipper Tracking'},
                'shipper_id' => $shipperID,
                "created_at" => $this->auroraModelData->{'Delivery Note Date Dispatched'},
                'source_id'  => $this->organisation->id.':'.$this->auroraModelData->{'Delivery Note Key'},
            ];
        }

        $weight = $this->auroraModelData->{'Delivery Note Weight'};

        $this->parsedData['shipment'] = $shipment;

        $deliveryAddressData = $this->parseAddress(
            prefix: "Delivery Note",
            auAddressData: $this->auroraModelData,
        );
        $deliveryAddress     = new Address(
            $deliveryAddressData,
        );


        $deliveryLocked = false;
        if (in_array($this->auroraModelData->{'Delivery Note State'}, ['Cancelled', 'Approved', 'Dispatched', 'Cancelled to Restock'])) {
            $deliveryLocked = true;
        }

        $reference = $this->auroraModelData->{'Delivery Note ID'};

        if ($this->auroraModelData->{'Delivery Note State'} == "Cancelled") {
            $count = DB::connection('aurora')->table('Delivery Note Dimension')->where('Delivery Note ID', $reference)->count();
            if ($count > 1) {
                $reference = $reference.'-cancelled';
            }
        }

        $this->parsedData["delivery_note"] = [
            "reference"        => $reference,
            'date'             => $this->auroraModelData->{'Delivery Note Date Created'},
            "state"            => $state,
            "status"           => $status,
            "source_id"        => $this->organisation->id.':'.$this->auroraModelData->{'Delivery Note Key'},
            "created_at"       => $this->auroraModelData->{'Delivery Note Date Created'},
            'picking_at'       => $this->auroraModelData->{'Delivery Note Date Start Picking'},
            'picked_at'        => $this->auroraModelData->{'Delivery Note Date Finish Picking'},
            'packing_at'       => $this->auroraModelData->{'Delivery Note Date Start Packing'},
            'packed_at'        => $this->auroraModelData->{'Delivery Note Date Finish Packing'},
            'finalised_at'     => $this->auroraModelData->{'Delivery Note Date Done Approved'},
            'dispatched_at'    => $this->auroraModelData->{'Delivery Note Date Dispatched'},
            'weight'           => $weight,
            'email'            => $this->auroraModelData->{'Delivery Note Email'},
            'phone'            => $this->auroraModelData->{'Delivery Note Telephone'},
            'delivery_address' => $deliveryAddress,
            'warehouse_id'     => $warehouse->id,
            'delivery_locked'  => $deliveryLocked,
            'fetched_at'       => now(),
            'last_fetched_at'  => now()
        ];

        if ($cancelled_at) {
            $this->parsedData["delivery_note"]['cancelled_at'] = $cancelled_at;
        }
    }

    protected function fetchData($id): object|null
    {
        return DB::connection("aurora")
            ->table("Delivery Note Dimension")
            ->where("Delivery Note Key", $id)
            ->first();
    }
}
