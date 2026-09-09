<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotUtmSettings extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $settings = array_merge(Arr::get($mailshot->data, 'utm', []), $modelData);

        return $this->update($mailshot, ['data' => ['utm' => $settings]], ['data']);
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'source'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'medium'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'campaign'   => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function jsonResponse(Mailshot $mailshot): array
    {
        return GetMailshotUtmSettings::run($mailshot);
    }
}
