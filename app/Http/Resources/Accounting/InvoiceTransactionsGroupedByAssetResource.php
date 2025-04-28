<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:38:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $quantity
 * @property string $net_amount
 * @property string $currency_code
 * @property mixed $id
 * @property mixed $in_process
 * @property mixed $model_type
 * @property mixed $asset_id
 * @property mixed $number_grouped_transactions
 * @property mixed $description
 * @property mixed $data
 */
class InvoiceTransactionsGroupedByAssetResource extends JsonResource
{
    use WithInvoiceTransactionFulfilmentExtraData;

    public function toArray($request): array
    {

        return [
            'asset_id'      => $this->asset_id,
            'code'          => $this->code,
            'description'   => $this->description,
            'quantity'      => (int)$this->quantity,
            'net_amount'    => $this->net_amount,
            'currency_code' => $this->currency_code,
            'in_process'    => $this->in_process,
            'number_grouped_transactions' => $this->number_grouped_transactions,

        ];
    }
}
