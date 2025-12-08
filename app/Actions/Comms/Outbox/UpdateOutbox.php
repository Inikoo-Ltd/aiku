<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Mail\OutboxesResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOutbox extends OrgAction
{
    use WithActionUpdate;

    private Outbox $outbox;

    public function handle(Outbox $outbox, array $modelData): Outbox
    {
        if ($subject = Arr::pull($modelData, 'subject')) {
            $this->update($outbox->emailOngoingRun?->email, [
                'subject' => $subject
            ]);
        }

        if ($days_after = Arr::pull($modelData, 'days_after')) {
            $this->update($outbox->setting, [
                 'days_after' => $days_after
             ]);

            unset($modelData['days_after']);
        }

        if ($send_time = Arr::pull($modelData, 'send_time')) {

            $timezone = $outbox->shop->timezone;
            $timezoneOffset = str_replace('GMT', '', $timezone->formatOffset());
            $sendTimeWithTimezone = $send_time . $timezoneOffset;

            $this->update($outbox->setting, [
                'send_time' => $sendTimeWithTimezone
            ]);

            unset($modelData['send_time']);
        }

        return $this->update($outbox, $modelData, ['data']);
    }

    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'required', 'string'],
            'subject' => ['sometimes', 'required', 'string'],
            'days_after' => ['sometimes', 'required', 'integer'],
            'send_time' => ['sometimes', 'required', 'date_format:H:i:s']
        ];
    }

    public function inShop(Shop $shop, Outbox $outbox, ActionRequest $request): Outbox
    {
        $this->initialisation($outbox->organisation, $request);

        return $this->handle($outbox, $this->validatedData);
    }

    public function action(Outbox $outbox, array $modelData): Outbox
    {
        $this->asAction = true;
        $this->outbox   = $outbox;
        $this->initialisation($outbox->organisation, $modelData);

        return $this->handle($outbox, $this->validatedData);
    }

    public function asController(Fulfilment $fulfilment, Outbox $outbox, ActionRequest $request): Outbox
    {
        $this->initialisation($outbox->organisation, $request);

        return $this->handle($outbox, $this->validatedData);
    }


    public function jsonResponse(Outbox $outbox): OutboxesResource
    {
        return new OutboxesResource($outbox);
    }
}
