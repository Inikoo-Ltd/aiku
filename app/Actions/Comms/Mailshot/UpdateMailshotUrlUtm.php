<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotUrlUtm extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $url = Arr::get($modelData, 'url');
        $utm = array_filter(Arr::only($modelData, MailshotUtmParameterEnum::values()), fn ($value) => filled($value));

        $utmLinks = collect(Arr::get($mailshot->data, 'utm_links', []))
            ->reject(fn (array $link) => Arr::get($link, 'url') === $url)
            ->values();

        if ($utm !== []) {
            $utmLinks->push([
                'url' => $url,
                'utm' => $utm,
            ]);
        }

        return $this->update($mailshot, ['data' => ['utm_links' => $utmLinks->all()]], ['data']);
    }

    public function rules(): array
    {
        $rules = [
            'url' => ['required', 'string', 'max:2048'],
        ];

        foreach (MailshotUtmParameterEnum::values() as $parameter) {
            $rules[$parameter] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function jsonResponse(Mailshot $mailshot): array
    {
        return [
            'utm_links' => Arr::get($mailshot->data, 'utm_links', []),
        ];
    }
}
