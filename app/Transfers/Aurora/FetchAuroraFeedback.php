<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 18:33:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Helpers\Feedback\FeedbackOriginSourceEnum;
use App\Models\Accounting\Invoice;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class FetchAuroraFeedback extends FetchAurora
{
    protected function parseModel(): void
    {

        $user = $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'Feedback User Key'});

        if ($this->auroraModelData->{'Feedback Parent'} == 'Replacement') {
            /** @var DeliveryNote $origin */
            $origin = $this->parseDeliveryNote($this->organisation->id.':'.$this->auroraModelData->{'Feedback Parent Key'});
            $originSource = FeedbackOriginSourceEnum::REPLACEMENT;
        } elseif ($this->auroraModelData->{'Feedback Parent'} == 'Refund') {
            /** @var Invoice $origin */
            $origin = $this->parseInvoice($this->organisation->id.':'.$this->auroraModelData->{'Feedback Parent Key'});
            $originSource = FeedbackOriginSourceEnum::REFUND;
        } else {
            return;
        }

        if (! $origin) {
            return;
        }

        $this->parsedData['origin'] = $origin;

        $userId = $user?->id;

        $this->parsedData['feedback'] = [
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Feedback Date'}),
            'user_id' => $userId,
            'origin_source' => $originSource,
            'blame_supplier' => $this->auroraModelData->{'Feedback Supplier'} == 'Yes',
            'blame_picker' => $this->auroraModelData->{'Feedback Picker'} == 'Yes',
            'blame_packer' => $this->auroraModelData->{'Feedback Packer'} == 'Yes',
            'blame_warehouse' => $this->auroraModelData->{'Feedback Warehouse'} == 'Yes',
            'blame_courier' => $this->auroraModelData->{'Feedback Courier'} == 'Yes',
            'blame_marketing' => $this->auroraModelData->{'Feedback Marketing'} == 'Yes',
            'blame_customer' => $this->auroraModelData->{'Feedback Customer'} == 'Yes',
            'blame_other' => $this->auroraModelData->{'Feedback Other'} == 'Yes',
            'message' => $this->auroraModelData->{'Feedback Message'},
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Feedback Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Feedback Dimension')
            ->where('Feedback Key', $id)->first();
    }
}
