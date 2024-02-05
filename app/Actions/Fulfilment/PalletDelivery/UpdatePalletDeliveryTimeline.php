<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePalletDeliveryTimeline extends OrgAction
{
    use WithActionUpdate;


    public function handle(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        match ($modelData['state']) {
            PalletDeliveryStateEnum::IN_PROCESS->value => $modelData['in_process_at']       = now(),
            PalletDeliveryStateEnum::READY->value      => $modelData['ready_at']            = now(),
            PalletDeliveryStateEnum::RECEIVED->value   => $modelData['received_at']         = now(),
            PalletDeliveryStateEnum::DONE->value       => $modelData['done_at']             = now(),
            default                                    => null
        };

        return $this->update($palletDelivery, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("fulfilments.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'state' => ['required', Rule::in(PalletDeliveryStateEnum::values())],
        ];
    }

    public function jsonResponse(PalletDelivery $palletDelivery): JsonResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }

    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }
}
