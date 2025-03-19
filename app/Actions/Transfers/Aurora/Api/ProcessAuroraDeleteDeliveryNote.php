<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 22:18:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\Dispatching\DeliveryNote\ForceDeleteDeliveryNote;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property string $fetcher
 */
class ProcessAuroraDeleteDeliveryNote extends OrgAction
{
    use WithProcessAurora;

    public function rules(): array
    {
        return [
            'id'              => ['required', 'integer'],
            'bg'              => ['sometimes', 'boolean'],
            'delay'           => ['sometimes', 'integer']
        ];
    }


    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $res = [
            'status'  => 'error',
            'message' => 'Delivery note not found',
            'model'   => 'DeleteDeliverNote'
        ];

        $this->initialisation($organisation, $request);
        $validatedData = $this->validatedData;

        $deliveryNote = DeliveryNote::where('source_id', $organisation->id.':'.$validatedData['id'])->first();

        if ($deliveryNote) {

            ForceDeleteDeliveryNote::make()->action($deliveryNote);

            $res = [
                'status' => 'ok',
                'id'     => $deliveryNote->source_id,
                'model'  => 'DeleteDeliverNote',
            ];
        }

        return $res;
    }

}
