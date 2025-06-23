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
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Lorisleiva\Actions\ActionRequest;

class StorePoll extends OrgAction
{
    // TODO: raul fix the permissions
    // use WithCRMEditAuthorisation;
    use WithNoStrictRules;

    public function handle(Shop $shop, array $modelData): Poll
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        $poll = $shop->polls()->create($modelData);
        $poll->stats()->create([
            'poll_id' => $poll->id,
        ]);

        if ($poll->type == PollTypeEnum::OPTION) {
            foreach ($modelData['options'] ?? [] as $option) {
                StorePollOption::make()->action(
                    $poll,
                    [
                        'value' => $option . '-' . $poll->id . $poll->shop->id,
                        'label' => $option,
                    ]
                );
            }
        }

        ShopHydratePolls::dispatch($shop);
        PollHydrateCustomers::dispatch($poll);

        //todo add Store,Org,Group hydrators here

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
            'type'                     => ['required', Rule::enum(PollTypeEnum::class)],
            'options'                => [
                new RequiredIf($this->get('type') === PollTypeEnum::OPTION->value),
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
