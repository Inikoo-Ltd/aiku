<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class FetchAuroraPurchaseOrderNote extends FetchAurora
{
    protected function parseModel(): void
    {
        $purchaseOrder = PurchaseOrder::where('source_id', $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Key'})->first();
        if (!$purchaseOrder) {
            return;
        }

        $note = trim(preg_replace('/\n\s*\n(\s*\n)+/', "\n\n", str_replace("\r", '', $this->auroraModelData->{'History Abstract'}.($this->auroraModelData->{'History Details'} ?? ''))));
        if ($note === '') {
            return;
        }

        $user = $this->parseUserFromHistory();

        $this->parsedData['purchase_order'] = $purchaseOrder;
        $this->parsedData['note']           = [
            'note'       => $note,
            'user_id'    => $user?->id,
            'author'     => $this->auroraModelData->{'Author Name'} ?: null,
            'created_at' => $this->parseDatetime($this->auroraModelData->{'History Date'}),
            'source_id'  => $this->organisation->id.':'.$this->auroraModelData->{'History Key'},
        ];
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('History Dimension')
            ->join('Purchase Order History Bridge', 'Purchase Order History Bridge.History Key', '=', 'History Dimension.History Key')
            ->join('Purchase Order Dimension', 'Purchase Order Dimension.Purchase Order Key', '=', 'Purchase Order History Bridge.Purchase Order Key')
            ->where('History Dimension.History Key', $id)
            ->where('Purchase Order History Bridge.Type', 'Notes')
            ->whereIn('Purchase Order Dimension.Purchase Order Type', ['Parcel', 'Container'])
            ->select('History Dimension.*', 'Purchase Order History Bridge.Purchase Order Key')
            ->first();
    }
}
