<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 27 Jan 2026 10:11:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotRecipientFilter extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $this->update($mailshot, $modelData);

        return $mailshot;
    }

    public function rules(): array
    {
        return [
            'recipients_recipe' => ['required', 'array'],

            // Validate each filter key exists in allowed filters
            // 'recipients_recipe.*' => ['sometimes', 'array'],

            // Validate by_family_never_ordered filter
            // 'recipients_recipe.by_family_never_ordered' => ['sometimes', 'array'],
            // 'recipients_recipe.by_family_never_ordered.multiple' => ['required', 'boolean'],

            // Validate orders_in_basket filter
            // 'recipients_recipe.orders_in_basket' => ['sometimes', 'array'],
            // 'recipients_recipe.orders_in_basket.multiple' => ['required', 'boolean'],
            // 'recipients_recipe.orders_in_basket.valid_date' => ['required', 'date_format:d-m-Y'],

        ];
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
