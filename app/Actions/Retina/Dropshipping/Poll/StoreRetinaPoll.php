<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 01-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Poll\UI;

use App\Actions\CRM\Poll\StorePoll;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
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

class StoreRetinaPoll extends RetinaAction
{
    use WithNoStrictRules;

    public function handle(Shop $shop, array $modelData): Poll
    {

        // if ($request->get('in_registration', false)) {
        //     $request->merge([
        //         'in_iris' => true,
        //     ]);
        // } else {
        //     $request->merge([
        //         'in_iris' => false,
        //     ]);
        // }

        $inIris = Arr::pull($modelData, 'in_registration', false);
        $inIrisRequired = Arr::pull($modelData, 'in_registration_required', false);



        return StorePoll::make()->action(
            $shop,
            $modelData
        );
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

        return $rules;
    }

    public function asController(ActionRequest $request): Poll
    {
        $this->initialisation($request);
        return $this->handle($this->shop, $this->validatedData);
    }
    public function htmlResponse(Poll $poll): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.polls.index', [
            'organisation' => $poll->organisation->slug,
            'shop'         => $poll->shop->slug
        ]);
    }
}
