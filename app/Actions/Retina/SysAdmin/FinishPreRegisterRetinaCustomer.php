<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\FinishPreRegisterCustomer;
use App\Actions\RetinaAction;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Models\CRM\WebUser;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FinishPreRegisterRetinaCustomer extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    use AsAction;
    use WithAttributes;

    protected Shop $shop;

    public function handle(WebUser $webUser, array $modelData)
    {
        if (Arr::get($modelData, 'preview', false)) {
            return;
        }
        FinishPreRegisterCustomer::make()->action($webUser, $modelData);
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
            'phone'                    => ['required', 'max:255'],
            'contact_address'          => ['required', new ValidAddress()],
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

        foreach ($pollReplies as $key => $value) {
            $pollId = Arr::get($value, 'id');
            $pollType = Arr::get($value, 'type');
            $pollAnswer = Arr::get($value, 'answer');

            if (!$pollId || !$pollType || !$pollAnswer) {
                $validator->errors()->add(
                    'poll_replies.' . $key,
                    "Poll reply not valid"
                );
                return;
            }

            $poll = $polls->get($pollId);

            if (!$poll) {
                $validator->errors()->add(
                    'poll_replies.' . $key,
                    "The poll does not exist!"
                );
                return;
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
                    return;
                }
            } elseif ($pollType === PollTypeEnum::OPEN_QUESTION->value && !is_string($pollAnswer)) {
                $validator->errors()->add(
                    'poll_replies.' . $key,
                    "The answer must be a string!"
                );
                return;
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->registerDropshippingInitialisation($shop, $request);
        $this->handle(request()->user(), $this->validatedData);
    }
}
