<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 13:56:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\SubscriptionEvent;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\SubscriptionEvent\SubscriptionEventTypeEnum;
use App\Models\Comms\SubscriptionEvent;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateSubscriptionEvent extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(SubscriptionEvent $subscriptionEvent, array $modelData): SubscriptionEvent
    {
        return $this->update($subscriptionEvent, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return false;

    }

    public function rules(): array
    {
        $rules = [
            'type' => ['sometimes', Rule::enum(SubscriptionEventTypeEnum::class)],
        ];

        if (!$this->strict) {
            $rules['origin_type'] = ['nullable', 'string'];
            $rules['origin_id']   = ['nullable', 'integer'];

            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(SubscriptionEvent $subscriptionEvent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): SubscriptionEvent
    {
        $this->strict = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($subscriptionEvent->shop, $modelData);

        return $this->handle($subscriptionEvent, $this->validatedData);
    }


}
