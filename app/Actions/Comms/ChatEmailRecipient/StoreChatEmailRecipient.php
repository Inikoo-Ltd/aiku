<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 11:19:40 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\ChatEmailRecipient;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Catalogue\Shop;
use App\Models\Comms\ChatEmailRecipient;
use Lorisleiva\Actions\ActionRequest;

class StoreChatEmailRecipient extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ChatEmailRecipient
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        /** @var ChatEmailRecipient $chatEmailRecipient */
        $chatEmailRecipient = $shop->chatEmailRecipients()->create($modelData);

        return $chatEmailRecipient;
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
            'name'  => ['required','nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): ChatEmailRecipient
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($shop->organisation, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
