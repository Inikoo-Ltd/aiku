<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Production\Production;
use Illuminate\Support\Facades\DB;

class FetchAuroraPurchaseOrder extends FetchAurora
{
    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Purchase Order Parent'} == 'Supplier') {
            $supplierData = Db::connection('aurora')->table('Supplier Dimension')
                ->select('aiku_ignore')
                ->where('Supplier Key', $this->auroraModelData->{'Purchase Order Parent Key'})->first();
            if ($supplierData && $supplierData->aiku_ignore == 'Yes') {
                return;
            }
        }


        if (in_array($this->auroraModelData->{'Purchase Order Parent'}, ['Parcel', 'Container'])) {
            return;
        }

        if ($this->auroraModelData->{'Purchase Order State'} == 'Cancelled' and !$this->auroraModelData->{'Purchase Order Public ID'}) {
            return;
        }


        $orgParent = $this->parseProcurementOrderParent(
            $this->auroraModelData->{'Purchase Order Parent'},
            $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Parent Key'}
        );

        if ($orgParent instanceof Production) {
            return;
        }

        if (!$orgParent) {
            print "Error No parent found ".$this->auroraModelData->{'Purchase Order Parent'}."  ".$this->auroraModelData->{'Purchase Order Parent Key'}." ".$this->auroraModelData->{'Purchase Order Parent Name'}."  \n";

            return;
        }


        $this->parsedData["org_parent"] = $orgParent;

        $cancelledAt = null;
        $createdAt   = $this->parseDatetime($this->auroraModelData->{'Purchase Order Creation Date'});
        $submittedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Submitted Date'});
        $confirmedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Confirmed Date'});

        $date = $createdAt;

        $state = match ($this->auroraModelData->{'Purchase Order State'}) {
            "InProcess" => PurchaseOrderStateEnum::IN_PROCESS,
            "Submitted" => PurchaseOrderStateEnum::SUBMITTED,
            "Cancelled" => PurchaseOrderStateEnum::CANCELLED,
            "Received", "Checked", "QC_Pass", "Placed", "Costing", "InvoiceChecked" => PurchaseOrderStateEnum::SETTLED,
            "NoReceived" => PurchaseOrderStateEnum::NOT_RECEIVED,
            default => PurchaseOrderStateEnum::CONFIRMED,
        };

        $settledAt    = null;
        $notReceivedAt = null;
        if ($state == PurchaseOrderStateEnum::SETTLED) {
            $settledAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Consolidated Date'})
                ?? $this->parseDatetime($this->auroraModelData->{'Purchase Order Checked Date'})
                ?? $this->parseDatetime($this->auroraModelData->{'Purchase Order Received Date'});
            $date = $settledAt ?? $confirmedAt ?? $date;
        }
        if ($state == PurchaseOrderStateEnum::NOT_RECEIVED) {
            $notReceivedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Cancelled Date'});
        }


        if ($state == PurchaseOrderStateEnum::SUBMITTED) {
            if ($submittedAt) {
                $date = $submittedAt;
            }
            $confirmedAt = null;
        }
        if ($state == PurchaseOrderStateEnum::CONFIRMED) {
            if ($confirmedAt) {
                $date = $confirmedAt;
            }
        }

        $exchangeDate = $date;

        if ($state == PurchaseOrderStateEnum::CANCELLED) {
            $cancelledAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Cancelled Date'});

            if ($cancelledAt) {
                $date = $cancelledAt;
            }
        }


        $deliveryState = match ($this->auroraModelData->{'Purchase Order State'}) {
            "Placed", "Costing", "InvoiceChecked" => PurchaseOrderDeliveryStateEnum::PLACED,
            "NoReceived" => PurchaseOrderDeliveryStateEnum::NOT_RECEIVED,
            "Cancelled" => PurchaseOrderDeliveryStateEnum::CANCELLED,
            "Manufactured", "Inputted" => PurchaseOrderDeliveryStateEnum::READY_TO_SHIP,
            "Dispatched" => PurchaseOrderDeliveryStateEnum::DISPATCHED,
            "Confirmed" => PurchaseOrderDeliveryStateEnum::CONFIRMED,
            "Received" => PurchaseOrderDeliveryStateEnum::RECEIVED,
            "Checked", "QC_Pass" => PurchaseOrderDeliveryStateEnum::CHECKED,
            default => PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        };


        $org_exchange = $this->auroraModelData->{'Purchase Order Currency Exchange'};

        $grp_exchange = GetHistoricCurrencyExchange::run(
            Currency::find($this->parseCurrencyID($this->auroraModelData->{'Purchase Order Currency Code'})),
            $this->organisation->group->currency,
            $exchangeDate
        );


        $estimatedReceivingDate = $this->parseDatetime($this->auroraModelData->{'Purchase Order Estimated Receiving Date'});

        $data = array_filter([
            'delivery_type'            => strtolower($this->auroraModelData->{'Purchase Order Type'} ?? ''),
            'incoterm'                 => $this->auroraModelData->{'Purchase Order Incoterm'},
            'port_of_export'           => $this->auroraModelData->{'Purchase Order Port of Export'},
            'port_of_import'           => $this->auroraModelData->{'Purchase Order Port of Import'},
            'delivery_address'         => $this->auroraModelData->{'Purchase Order Warehouse Address'},
            'terms_and_conditions'     => $this->auroraModelData->{'Purchase Order Terms and Conditions'},
            'estimated_receiving_date' => $estimatedReceivingDate?->toDateString(),
        ]);
        $this->parsedData["purchase_order"] = [
            'date'            => $date,
            'submitted_at'    => $submittedAt,
            'confirmed_at'    => $confirmedAt,
            'settled_at'      => $settledAt,
            'not_received_at' => $notReceivedAt,

            'parent_code' => $this->auroraModelData->{'Purchase Order Parent Code'},
            'parent_name' => $this->auroraModelData->{'Purchase Order Parent Name'},

            "reference"      => (string)$this->auroraModelData->{'Purchase Order Public ID'} ?? $this->auroraModelData->{'Purchase Order Key'},
            "state"          => $state,
            "delivery_state" => $deliveryState,

            "cost_items"    => $this->auroraModelData->{'Purchase Order Items Net Amount'},
            "cost_shipping" => $this->auroraModelData->{'Purchase Order Shipping Net Amount'},
            "cost_extra"    => $this->auroraModelData->{'Purchase Order Extra Cost Amount'},
            "cost_tax"      => $this->auroraModelData->{'Purchase Order Total Tax Amount'},
            "cost_total"    => $this->auroraModelData->{'Purchase Order Total Amount'},

            "estimated_received_at" => $estimatedReceivingDate,

            "source_id"       => $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Key'},
            "org_exchange"    => $org_exchange,
            "grp_exchange"    => $grp_exchange,
            "currency_id"     => $this->parseCurrencyID($this->auroraModelData->{'Purchase Order Currency Code'}),
            "created_at"      => $createdAt,
            "cancelled_at"    => $cancelledAt,
            "data"            => $data,
            'fetched_at'      => now(),
            'last_fetched_at' => now()
        ];
    }

    protected function fetchData($id): object|null
    {
        return DB::connection("aurora")
            ->table("Purchase Order Dimension")
            ->where("Purchase Order Key", $id)
            ->whereIn("Purchase Order Type", ["Parcel", "Container"])
            ->first();
    }


}
