<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 20:58:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\RegisterCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\RegisterFulfilmentCustomer;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
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
    public function handle(array $modelData): void
    {
        if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
            RegisterFulfilmentCustomer::run(
                $this->shop->fulfilment,
                $modelData
            );
        } else {
            RegisterCustomer::run(
                $this->shop,
                $modelData
            );
        }
    }

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
        $fulfilmentRules = [
            'product'            => ['required', 'string'],
            'shipments_per_week' => ['required', 'string'],
            'size_and_weight'    => ['required', 'string'],
            'interest'           => ['required', 'required'],
        ];

        $rules = [
            'contact_name'    => ['required', 'string', 'max:255'],
            'company_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_website'    => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'is_opt_in'       => ['required', 'boolean'],
            'poll_replies'    => ['sometimes', 'array'],
            'password'        =>
                [
                    'required',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],
        ];

        if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
            $rules = array_merge($rules, $fulfilmentRules);
        }

        return $rules;
    }

}
