<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 11:35:24 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\ExternalSubscriberEmailRecipient;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Catalogue\Shop;
use App\Models\Comms\ExternalSubscriberEmailRecipient;

class StoreExternalSubscriberEmailRecipient extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ExternalSubscriberEmailRecipient
    {
        data_set($modelData, 'group_id', $shop->group_id);

        return ExternalSubscriberEmailRecipient::create($modelData);

    }


    public function rules(): array
    {
        return [
            'name'  => ['required', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): ExternalSubscriberEmailRecipient
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($shop->organisation, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
