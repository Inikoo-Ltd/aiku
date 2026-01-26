<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 21 Jan 2026 16:46:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;

class UpdateMailshotTemplate extends OrgAction
{
    use WithActionUpdate;

    public function handle(EmailTemplate $emailTemplate, array $modelData): EmailTemplate
    {
        data_set($modelData, 'state', EmailTemplateStateEnum::ACTIVE->value);

        return $this->update($emailTemplate, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'name'              => ['sometimes', 'string', 'max:255'],
            'layout'            => ['sometimes', 'array'],
            'compiled_layout'   => ['sometimes', 'string']
        ];

        return $rules;
    }

    public function asController(Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($emailTemplate, $this->validatedData);
    }

    public function jsonResponse(EmailTemplate $emailTemplate): JsonResponse
    {
        return response()->json($emailTemplate);
    }
}
