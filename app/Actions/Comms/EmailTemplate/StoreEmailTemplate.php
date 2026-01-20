<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Group;
use Illuminate\Validation\Rule;

class StoreEmailTemplate extends OrgAction
{
    public function handle(Group $group, array $modelData): EmailTemplate
    {
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $group->emailTemplates()->create($modelData);


        return $emailTemplate;
    }

    public function rules(): array
    {
        $rules = [
            'layout'      => ['sometimes', 'array'],
            'arguments'   => ['sometimes', 'array'],
            'name'        => ['required', 'string', 'max:255'],
            'builder'     => ['required', Rule::enum(EmailTemplateBuilderEnum::class)],
            'language_id' => ['required', 'exists:languages,id'],
            'data'        => ['sometimes', 'array'],
            'shop_id'     => ['sometimes', 'nullable', 'exists:shops,id'],
        ];

        if (!$this->strict) {
            $rules['is_seeded'] = ['required', 'boolean'];
            $rules['state']     = ['required', Rule::enum(EmailTemplateStateEnum::class)];
            $rules['active_at'] = ['sometimes', 'required', 'date'];
        }

        return $rules;
    }


    public function action(Group $group, array $modelData, bool $strict = true): EmailTemplate
    {
        $this->asAction = true;
        $this->strict   = $strict;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
