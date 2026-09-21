<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 21 Sep 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\EmailTemplate\EmailTemplateRowTypeEnum;
use App\Models\Comms\EmailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateEmailTemplateRow extends OrgAction
{
    use WithActionUpdate;

    public function handle(EmailTemplate $emailTemplate, array $modelData): EmailTemplate
    {
        if (Arr::has($modelData, 'layout')) {
            $row = Arr::get($modelData, 'layout');
            Arr::forget($row, 'metadata');
            $modelData['layout'] = $row;
        }

        if (Arr::has($modelData, 'row_type')) {
            $data             = $emailTemplate->data;
            $data['row_type'] = Arr::pull($modelData, 'row_type');
            $modelData['data'] = $data;
        }

        return $this->update($emailTemplate, $modelData, ['data']);
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'layout'   => ['sometimes', 'required', 'array'],
            'row_type' => ['sometimes', Rule::enum(EmailTemplateRowTypeEnum::class)],
        ];
    }

    public function action(EmailTemplate $emailTemplate, array $modelData): EmailTemplate
    {
        $this->asAction = true;
        $this->initialisationFromGroup($emailTemplate->group, $modelData);

        return $this->handle($emailTemplate, $this->validatedData);
    }

    public function asController(EmailTemplate $emailTemplate, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromGroup($emailTemplate->group, $request);

        return $this->handle($emailTemplate, $this->validatedData);
    }
}
