<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 21 Jan 2026 16:46:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotBuilderEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Group;
use Illuminate\Validation\Rule;

class StoreMailshotTemplate extends OrgAction
{
    public function handle(array $modelData): Mailshot
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this->shop->mailshots()->create($modelData);


        return $mailshot;
    }

    public function rules(): array
    {
        $rules = [
            'layout'      => ['sometimes', 'array'],
            'arguments'   => ['sometimes', 'array'],
            'name'        => ['required', 'string', 'max:255'],
            // 'builder'     => ['required', Rule::enum(MailshotBuilderEnum::class)],
            'language_id' => ['required', 'exists:languages,id'],
            'data'        => ['sometimes', 'array'],
            'shop_id'     => ['sometimes', 'nullable', 'exists:shops,id'],
        ];

        if (!$this->strict) {
            $rules['is_seeded'] = ['required', 'boolean'];
            $rules['state']     = ['required', Rule::enum(MailshotStateEnum::class)];
            $rules['active_at'] = ['sometimes', 'required', 'date'];
        }

        return $rules;
    }


    // public function action(Group $group, array $modelData, bool $strict = true): Mailshot
    // {
    //     $this->asAction = true;
    //     $this->strict   = $strict;
    //     $this->initialisationFromGroup($group, $modelData);

    //     return $this->handle($this->validatedData);
    // }

    public function asController()
    {
    }
}
