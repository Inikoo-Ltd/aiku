<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 20:58:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\SysAdmin;

use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

trait WithRetinaRegistration
{
    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $pollReplies = $request->input('poll_replies', []);

        if (count($pollReplies) === 0) {
            return;
        }

        $pollIds = Arr::pluck($pollReplies, 'id');
        $polls   = Poll::whereIn('id', $pollIds)
            ->where('shop_id', $this->shop->id)
            ->get()
            ->keyBy('id');

        foreach ($pollReplies as $key => $value) {
            $pollId     = Arr::get($value, 'id');
            $pollType   = Arr::get($value, 'type');
            $pollAnswer = Arr::get($value, 'answer');

            if (!$pollId || !$pollType || !$pollAnswer) {
                $validator->errors()->add(
                    'poll_replies.'.$key,
                    "Poll reply not valid"
                );
                continue;
            }

            $poll = $polls->get($pollId);

            if (!$poll) {
                $validator->errors()->add(
                    'poll_replies.'.$key,
                    "The poll does not exist!"
                );
                continue;
            }

            if ($pollType === PollTypeEnum::OPTION->value) {
                $pollOptions = PollOption::where('poll_id', $pollId)
                    ->where('shop_id', $this->shop->id)
                    ->pluck('id')
                    ->toArray();

                if (!in_array((int)$pollAnswer, $pollOptions, true)) {
                    $validator->errors()->add(
                        'poll_replies.'.$key,
                        "The answer option does not exist!"
                    );
                }
            } elseif ($pollType === PollTypeEnum::OPEN_QUESTION->value && !is_string($pollAnswer)) {
                $validator->errors()->add(
                    'poll_replies.'.$key,
                    "The answer must be a string!"
                );
            }
        }
    }

    public function rules(): array
    {
        return [
            'contact_name'    => ['required', 'string', 'max:255'],
            'company_name'    => ['required', 'string', 'max:255'],
            'email'           => [
                'required',
                'string',
                'max:255',
                'exclude_unless:deleted_at,null',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'phone'           => ['required', 'max:255'],
            'contact_address' => ['required', new ValidAddress()],
            'is_opt_in'       => ['sometimes', 'boolean'],
            'poll_replies'    => ['sometimes', 'required', 'array'],
            'password'                 =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],
        ];
    }

}
