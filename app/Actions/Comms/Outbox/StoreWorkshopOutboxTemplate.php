<?php

/*
 * Author: eka yudinata <ekayudinatha@gmail.com>
 * Created: Tue, 12 December 2025 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Comms\EmailTemplate\StoreEmailTemplate;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Models\Comms\EmailTemplate;

class StoreWorkshopOutboxTemplate extends OrgAction
{
    use WithActionUpdate;

    public function handle(Outbox $outbox, array $modelData): EmailTemplate
    {

        $data = [
              'outboxes' => [$outbox->code->value]
           ];
        $emailTemplate =   StoreEmailTemplate::make()->action(
            $this->group,
            [
                          'name'        => Arr::get($modelData, 'name'),
                          'layout'      => Arr::get($modelData, 'layout'),
                          'is_seeded'   => false,
                          'builder'     => EmailTemplateBuilderEnum::BEEFREE,
                          'state'       => EmailTemplateStateEnum::IN_PROCESS,
                          'active_at'   => now(),
                          'language_id' => $this->organisation->language_id,
                          'data'        => $data
                      ],
            strict: false
        );

        return $emailTemplate;
    }

    public function rules(): array
    {
        return [
            'layout' => ['required'],
            'name'   => ['sometimes', 'required', 'string'],

        ];
    }

    public function asController(Shop $shop, Outbox $outbox, ActionRequest $request): EmailTemplate
    {
        $this->initialisation($outbox->organisation, $request);

        return $this->handle($outbox, $this->validatedData);
    }
}
