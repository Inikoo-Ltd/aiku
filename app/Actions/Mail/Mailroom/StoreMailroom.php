<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Mar 2023 16:50:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Mail\Mailroom;

use App\Enums\Mail\Mailroom\MailroomCodeEnum;
use App\Models\Mail\Mailroom;
use App\Models\SysAdmin\Group;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreMailroom
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(Group $group, array $modelData): Mailroom
    {
        /** @var Mailroom $mailroom */
        $mailroom = $group->mailrooms()->create($modelData);
        $mailroom->stats()->create();

        return $mailroom;
    }


    public function rules(): array
    {
        return [
            'code' => [Rule::enum(MailroomCodeEnum::class)],
            'name' => ['required', 'string'],
        ];
    }

    public function action(Group $group, array $modelData): Mailroom
    {
        $this->asAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($group, $validatedData);
    }
}
