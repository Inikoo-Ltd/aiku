<?php

/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-16h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $quantity
 * @property string $net_amount
 * @property string $name
 * @property string $currency_code
 * @property mixed $id
 * @property mixed $in_process
 * @property mixed $model_type
 * @property mixed $model_id
 * @property mixed $recurring_bill_transaction_id
 * @property mixed $data
 * @property mixed $asset_id
 * @property mixed $description
 */
class ItemizedInvoiceTransactionsResource extends JsonResource
{
    use WithInvoiceTransactionFulfilmentExtraData;

    public function toArray($request): array
    {
        return [
            'asset_id'        => $this->asset_id,
            'code'            => $this->code,
            'description'     => $this->description,
            'quantity'        => (int)$this->quantity,
            'net_amount'      => $this->net_amount,
            'currency_code'   => $this->currency_code,
            'in_process'      => $this->in_process,
            'fulfilment_info' => [
                'servicePalletInfo' => $this->model_type == 'Service' ? $this->getServicePalletInfo($this->data, false) : null,
                'rentedScopeInfo'   => $this->getRentedScopeInfo(
                    $this->recurring_bill_transaction_id,
                    $this->model_type,
                    $this->model_id,
                    false
                )
            ]


        ];
    }
}
