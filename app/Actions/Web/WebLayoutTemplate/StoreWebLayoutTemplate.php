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
use App\Models\SysAdmin\User;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreWebLayoutTemplate extends OrgAction
{
    private User $user;

    public function handle(Webpage $webpage, array $modelData): void
    {
        if (in_array($webpage->type, [WebpageTypeEnum::STOREFRONT, WebpageTypeEnum::BLOG])) {
            abort(422);
        }

        data_set($modelData, 'scope', class_basename($webpage));
        data_set($modelData, 'type', $webpage->type);
        data_set($modelData, 'sub_type', $webpage->sub_type);
        data_set($modelData, 'author_id', $this->user->id);

        WebLayoutTemplate::create($modelData);
    }

    public function rules(): array
    {
        return [
            'name'                          => ['required', 'string', Rule::unique('web_layout_templates', 'name')],
            'blocks'                        => ['required', 'array'],
            'blocks.*.show'                 => ['required', 'boolean'],
            'blocks.*.type'                 => ['required', 'string'],
            'blocks.*.position'             => ['required', 'numeric'],
            'blocks.*.fieldValue'           => ['required', 'array'],
            'blocks.*.visibility'           => ['required', 'array'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->user = $request->user();
        $this->initialisationFromGroup($webpage->group, $request);

        $this->handle($webpage, $this->validatedData);
    }
}
