<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\IrisAction;
use App\Actions\CRM\Customer\RegisterWithGoogleCustomer;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RegisterRetinaWithGoogleCustomer extends IrisAction
{
    /**
     * @throws \Throwable
     */
    use AsAction;
    use WithAttributes;

    protected Shop $shop;

    public function handle(array $modelData)
    {
        RegisterWithGoogleCustomer::make()->action(
            $this->shop,
            $modelData
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name'             => ['required', 'string', 'max:255'],
            'company_name'             => ['required', 'string', 'max:255'],
            'email'                    => [
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
            'phone'                    => ['required', 'max:255'],
            'contact_address'          => ['required', new ValidAddress()],
            'is_opt_in'       => ['sometimes', 'boolean'],
            'password'                 =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],
            'poll_replies'            => ['sometimes', 'required', 'array'],


        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $pollReplies = $request->input('poll_replies', []);

        if (count($pollReplies) === 0) {
            return;
        }

        $pollIds = Arr::pluck($pollReplies, 'id');
        $polls = Poll::whereIn('id', $pollIds)
            ->where('shop_id', $this->shop->id)
            ->get()
            ->keyBy('id');

        $isError = false;
        foreach ($pollReplies as $key => $value) {
            $pollId = Arr::get($value, 'id');
            $pollType = Arr::get($value, 'type');
            $pollAnswer = Arr::get($value, 'answer');

            if (!$pollId || !$pollType || !$pollAnswer) {
                $validator->errors()->add(
                    'poll_replies.' . $key,
                    "Poll reply not valid"
                );
                $isError = true;
                continue;
            }

            $poll = $polls->get($pollId);

            if (!$poll) {
                $validator->errors()->add(
                    'poll_replies.' . $key,
                    "The poll does not exist!"
                );
                $isError = true;
                continue;
            }

            if ($pollType === PollTypeEnum::OPTION->value) {
                $pollOptions = PollOption::where('poll_id', $pollId)
                    ->where('shop_id', $this->shop->id)
                    ->pluck('id')
                    ->toArray();

                if (!in_array((int) $pollAnswer, $pollOptions, true)) {
                    $validator->errors()->add(
                        'poll_replies.' . $key,
                        "The answer option does not exist!"
                    );
                    $isError = true;
                    continue;
                }
            } elseif ($pollType === PollTypeEnum::OPEN_QUESTION->value && !is_string($pollAnswer)) {
                $validator->errors()->add(
                    'poll_replies.' . $key,
                    "The answer must be a string!"
                );
                $isError = true;
                continue;
            }
        }
        if ($isError) {
            return;
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->initialisation($request);
        $this->handle($this->validatedData);
        return redirect()->route('retina.dashboard.show');
    }
}
