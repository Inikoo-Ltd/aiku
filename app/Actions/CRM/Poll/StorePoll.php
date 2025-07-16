<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:01:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Poll;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePolls;
use App\Actions\CRM\Poll\Hydrate\PollHydrateCustomers;
use App\Actions\CRM\PollOption\StorePollOption;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\CRM\Poll\PollOptionReferralSourcesEnum;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class StorePoll extends OrgAction
{
    use WithNoStrictRules;

    public function handle(Shop $shop, array $modelData): Poll
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        $type = Arr::pull($modelData, 'type.type');
        $options = Arr::pull($modelData, 'type.poll_options', []);
        data_forget($modelData, 'type');
        data_set($modelData, 'type', $type);

        if ($type == PollTypeEnum::OPTION_REFERRAL_SOURCES->value) {
            $modelData['in_registration'] = true;
            $modelData['in_registration_required'] = true;
            $modelData['in_iris'] = true;
            $modelData['in_iris_required'] = true;
        }

        /** @var \App\Models\CRM\Poll $poll */
        $poll = $shop->polls()->create($modelData);
        $poll->stats()->create([
            'poll_id' => $poll->id,
        ]);

        if ($poll->type == PollTypeEnum::OPTION) {
            foreach ($options as $index => $option) {
                StorePollOption::make()->action(
                    $poll,
                    [
                        'value' => $shop->id . $poll->id . $index,
                        'label' => $option['label'],
                    ]
                );
            }
        } else if ($poll->type == PollTypeEnum::OPTION_REFERRAL_SOURCES) {
            foreach (PollOptionReferralSourcesEnum::cases() as $option) {
                StorePollOption::make()->action(
                    $poll,
                    [
                        'value' => $option->value,
                        'label' => $option->label(),
                    ]
                );
            }
        }

        ShopHydratePolls::dispatch($shop);
        PollHydrateCustomers::dispatch($poll);

        return $poll;
    }

    public function rules(): array
    {
        $rules = [
            'name'                     => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'polls',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],
            'label'                    => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'polls',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],
            'in_registration'          => ['required', 'boolean'],
            'in_registration_required' => ['required', 'boolean'],
            'in_iris'                  => ['required', 'boolean'],
            'in_iris_required'         => ['sometimes', 'required', 'boolean'],
            'type'                => [
                'required',
                'array',
            ],
            'type.type'        => [
                'required',
                Rule::enum(PollTypeEnum::class)
            ],
            'type.poll_options'        => [
                new RequiredIf($this->get('type.type') === PollTypeEnum::OPTION->value),
                'array',
            ],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function asController(Shop $shop, ActionRequest $request): Poll
    {
        // this because field in_iris hidden for now
        if ($request->get('in_registration', false)) {
            $request->merge([
                'in_iris' => true,
            ]);
        } else {
            $request->merge([
                'in_iris' => false,
            ]);
        }

        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }

    public function afterValidator(Validator $validator): void
    {
        $type = $this->get('type.type');

        if ($type == PollTypeEnum::OPTION_REFERRAL_SOURCES->value) {
            if (Poll::where('shop_id', $this->shop->id)->where('type', PollTypeEnum::OPTION_REFERRAL_SOURCES)->exists()) {
                $validator->errors()->add('type.type', 'A poll of type "Option Referral Sources" already exists for this shop.');
            }
        }
    }

    public function htmlResponse(Poll $poll): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.polls.index', [
            'organisation' => $poll->organisation->slug,
            'shop'         => $poll->shop->slug
        ]);
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Poll
    {
        if (!$audit) {
            Poll::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
