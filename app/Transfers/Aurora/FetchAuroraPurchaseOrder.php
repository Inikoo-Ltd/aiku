<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
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
            print_r($this->auroraModelData);

            return;
        }

        if ($this->auroraModelData->{'Purchase Order State'} == 'Cancelled' and ! $this->auroraModelData->{'Purchase Order Public ID'}) {
            return;
        }

        $orgParent = $this->parseProcurementOrderParent(
            $this->auroraModelData->{'Purchase Order Parent'},
            $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Parent Key'}
        );

        if ($orgParent instanceof Production) {
            return;
        }

        if (! $orgParent) {
            echo 'Error No parent found '.$this->auroraModelData->{'Purchase Order Parent'}.'  '.$this->auroraModelData->{'Purchase Order Parent Key'}.' '.$this->auroraModelData->{'Purchase Order Parent Name'}."  \n";

            return;
        }

        $this->parsedData['org_parent'] = $orgParent;

        $cancelledAt = null;
        $createdAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Creation Date'});
        $submittedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Submitted Date'});
        $confirmedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Confirmed Date'});

        $date = $createdAt;

        $state = match ($this->auroraModelData->{'Purchase Order State'}) {
            'InProcess' => PurchaseOrderStateEnum::IN_PROCESS,
            'Submitted' => PurchaseOrderStateEnum::SUBMITTED,
            'Cancelled' => PurchaseOrderStateEnum::CANCELLED,
            default => PurchaseOrderStateEnum::CONFIRMED,
        };

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
            'Placed', 'Costing', 'InvoiceChecked' => PurchaseOrderDeliveryStateEnum::PLACED,
            'NoReceived' => PurchaseOrderDeliveryStateEnum::NOT_RECEIVED,
            'Cancelled' => PurchaseOrderDeliveryStateEnum::CANCELLED,
            'Manufactured', 'Inputted' => PurchaseOrderDeliveryStateEnum::READY_TO_SHIP,
            'Dispatched' => PurchaseOrderDeliveryStateEnum::DISPATCHED,
            'Confirmed' => PurchaseOrderDeliveryStateEnum::CONFIRMED,
            'Received' => PurchaseOrderDeliveryStateEnum::RECEIVED,
            'Checked', 'QC_Pass' => PurchaseOrderDeliveryStateEnum::CHECKED,
            default => PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        };

        $org_exchange = $this->auroraModelData->{'Purchase Order Currency Exchange'};

        $grp_exchange = GetHistoricCurrencyExchange::run(
            Currency::find($this->parseCurrencyID($this->auroraModelData->{'Purchase Order Currency Code'})),
            $this->organisation->group->currency,
            $exchangeDate
        );

        $data = [];
        $this->parsedData['purchase_order'] = [
            'date' => $date,
            'submitted_at' => $submittedAt,
            'confirmed_at' => $confirmedAt,
            // 'manufactured_at' => $this->parseDatetime($this->auroraModelData->{'Purchase Order Manufactured Date'}),
            // 'received_at'     => $this->parseDatetime($this->auroraModelData->{'Purchase Order Received Date'}),
            // 'checked_at'      => $this->parseDatetime($this->auroraModelData->{'Purchase Order Checked Date'}),
            // 'settled_at'      => $this->parseDatetime($this->auroraModelData->{'Purchase Order Consolidated Date'}),

            'parent_code' => $this->auroraModelData->{'Purchase Order Parent Code'},
            'parent_name' => $this->auroraModelData->{'Purchase Order Parent Name'},

            'reference' => (string) $this->auroraModelData->{'Purchase Order Public ID'} ?? $this->auroraModelData->{'Purchase Order Key'},
            'state' => $state,
            'delivery_state' => $deliveryState,

            'cost_items' => $this->auroraModelData->{'Purchase Order Items Net Amount'},
            'cost_shipping' => $this->auroraModelData->{'Purchase Order Shipping Net Amount'},
            'cost_total' => $this->auroraModelData->{'Purchase Order Total Amount'},

            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Key'},
            'org_exchange' => $org_exchange,
            'grp_exchange' => $grp_exchange,
            'currency_id' => $this->parseCurrencyID($this->auroraModelData->{'Purchase Order Currency Code'}),
            'created_at' => $createdAt,
            'cancelled_at' => $cancelledAt,
            'data' => $data,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->where('Purchase Order Key', $id)
            ->first();
    }
}
