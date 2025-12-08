<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraDeletedSupplier extends FetchAurora
{
    protected function parseModel(): void
    {
        $auroraDeletedData = json_decode(gzuncompress($this->auroraModelData->{'Supplier Deleted Metadata'}));

        $agentData = Db::connection('aurora')->table('Agent Supplier Bridge')
            ->leftJoin('Agent Dimension', 'Agent Supplier Agent Key', '=', 'Agent Key')
            ->select('Agent Code', 'Agent Key')
            ->where('Agent Supplier Supplier Key', $auroraDeletedData->{'Supplier Key'})->first();

        $agent = null;

        if ($agentData) {
            $agent = $this->parseAgent(
                $this->organisation->id.':'.$agentData->{'Agent Key'}
            );
            if (! $agent) {
                echo 'agent not found '.$agentData->{'Agent Code'}." \n";

                return;
            }
        }

        if ($agent) {
            $this->parsedData['parent'] = $agent;
        } else {
            $this->parsedData['parent'] = $this->organisation->group;
        }

        $phone = $auroraDeletedData->{'Supplier Main Plain Mobile'} ?? null;
        if ($phone == '') {
            $phone = $auroraDeletedData->{'Supplier Main Plain Telephone'};
        }

        $numberPurchaseOrders = DB::connection('aurora')->table('Purchase Order Dimension')
            ->where('Purchase Order State', '!=', 'Cancelled')->where('Purchase Order Parent', 'Supplier')
            ->where('Purchase Order Parent Key', $auroraDeletedData->{'Supplier Key'})->count();

        $deletedAt = null;
        $archivedAt = null;
        if ($numberPurchaseOrders == 0) {
            $deletedAt = $this->auroraModelData->{'Supplier Deleted Date'};
        } else {
            $archivedAt = $this->auroraModelData->{'Supplier Deleted Date'};
        }

        $createdAt = null;
        if (isset($auroraDeletedData->{'Supplier Valid From'})) {
            $createdAt = $this->parseDatetime($auroraDeletedData->{'Supplier Valid From'}) ?? null;
        }

        $scopeType = 'Group';
        $scopeId = $this->organisation->group_id;

        $code = preg_replace('/\s/', '-', $auroraDeletedData->{'Supplier Code'});

        $code = $code.'-deleted-'.$this->organisation->id;

        $this->parsedData['supplier'] =
            [
                'name' => $auroraDeletedData->{'Supplier Nickname'} ?? $auroraDeletedData->{'Supplier Name'},
                'code' => $code,
                'company_name' => $auroraDeletedData->{'Supplier Company Name'},
                'contact_name' => $auroraDeletedData->{'Supplier Main Contact Name'},
                'email' => $auroraDeletedData->{'Supplier Main Plain Email'},
                'phone' => $phone,
                'currency_id' => $this->parseCurrencyID($auroraDeletedData->{'Supplier Default Currency Code'}),
                'source_id' => $this->organisation->id.':'.$auroraDeletedData->{'Supplier Key'},
                'source_slug' => Str::kebab(strtolower($code)),
                'deleted_at' => $deletedAt,
                'archived_at' => $archivedAt,
                'status' => false,
                'address' => $this->parseAddress(prefix: 'Supplier Contact', auAddressData: $auroraDeletedData),
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'fetched_at' => now(),
                'last_fetched_at' => now(),

            ];

        if ($createdAt) {
            $this->parsedData['supplier']['created_at'] = $createdAt;
        }

        $this->parsedData['address'] = $this->parseAddress(prefix: 'Supplier Contact', auAddressData: $auroraDeletedData);
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Supplier Deleted Dimension')
            ->where('Supplier Deleted Key', $id)->first();
    }
}
