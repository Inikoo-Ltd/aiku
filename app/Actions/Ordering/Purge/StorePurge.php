<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-08h-45m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge;

use App\Actions\Ordering\Purge\Hydrators\PurgeHydratePurgedOrders;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Purge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StorePurge extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, $modelData): Purge
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        $purge = DB::transaction(function () use ($shop, $modelData) {
            /** @var Purge $purge */
            $purge = $shop->purges()->create($modelData);
            $purge->refresh();
            $purge->stats()->create([
                'currency_id' => $shop->currency_id
            ]);

            return $purge;
        });
        PurgeCreatePurgeOrders::dispatch($purge);
        PurgeHydratePurgedOrders::dispatch($purge);

        return $purge;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function afterValidator(Validator $validator): void
    {
        $dateThreshold       = Carbon::now()->subDays($this->get('inactive_days'));
        $numberEligiblePurge = $this->shop->orders()
            ->where('updated_at', '<', $dateThreshold)
            ->where('state', OrderStateEnum::CREATING)
            ->count();

        if ($this->strict && $numberEligiblePurge == 0) {
            $message = __("There are no eligible orders to purge");
            $validator->errors()->add('purge', $message);
        }
    }

    public function rules(): array
    {
        $rules = [
            'type'          => ['required', Rule::enum(PurgeTypeEnum::class)],
            'scheduled_at'  => ['sometimes', 'required', 'date'],
            'user_id'       => [
                'sometimes',
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],
            'inactive_days' => ['required', 'integer', 'min:1', 'max:3652'],
        ];

        if (!$this->strict) {
            $rules['state']   = ['required', Rule::enum(PurgeStateEnum::class)];
            $rules['start_at'] = ['sometimes', 'required', 'date'];
            $rules['end_at']   = ['sometimes', 'required', 'date'];
            $rules             = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Purge
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Purge
    {
        if (!$audit) {
            Purge::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
