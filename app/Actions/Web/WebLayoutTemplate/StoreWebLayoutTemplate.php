<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-14h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreWebLayoutTemplate extends OrgAction
{
    public function handle(Webpage $webpage, array $modelData): void
    {
        if (in_array($webpage->type, [WebpageTypeEnum::STOREFRONT, WebpageTypeEnum::BLOG])) {
            abort(422);
        }

        data_set($modelData, 'type', class_basename($webpage));
        data_set($modelData, 'scope', $webpage->type);

        WebLayoutTemplate::create($modelData);
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', Rule::unique('web_layout_templates', 'name')],
            'blocks'    => ['required', 'array'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisationFromGroup($webpage->group, $request);

        $this->handle($webpage, $this->validatedData);
    }
}
