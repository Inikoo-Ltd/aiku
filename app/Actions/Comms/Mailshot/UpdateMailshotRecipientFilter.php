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

            // by_all_customers filter
            'recipients_recipe.all_customers' => ['sometimes', 'array'],
            'recipients_recipe.all_customers.value' => ['sometimes', 'boolean'],

            // registered_never_ordered filter
            'recipients_recipe.registered_never_ordered' => ['sometimes', 'array'],
            'recipients_recipe.registered_never_ordered.value' => ['sometimes', 'array'],
            'recipients_recipe.registered_never_ordered.value.value' => ['sometimes', 'boolean'],
            'recipients_recipe.registered_never_ordered.value.date_range' => ['sometimes', 'array'],
            'recipients_recipe.registered_never_ordered.value.date_range.*' => ['sometimes', 'date'],
            'recipients_recipe.registered_never_ordered.value.amount_range' => ['sometimes', 'array'],
            'recipients_recipe.registered_never_ordered.value.amount_range.min' => ['sometimes', 'nullable', 'numeric'],
            'recipients_recipe.registered_never_ordered.value.amount_range.max' => ['sometimes', 'nullable', 'numeric'],

            // by_family filter
            'recipients_recipe.by_family' => ['sometimes', 'array'],
            'recipients_recipe.by_family.value' => ['sometimes', 'array'],
            'recipients_recipe.by_family.value.ids' => ['sometimes', 'array'],
            'recipients_recipe.by_family.value.ids.*' => ['sometimes', 'integer'],
            'recipients_recipe.by_family.value.behaviors' => ['sometimes', 'array'],
            'recipients_recipe.by_family.value.behaviors.*' => ['sometimes', 'string'],
            'recipients_recipe.by_family.value.combine_logic' => ['sometimes', 'boolean'],

            // by_interest filter
            'recipients_recipe.by_interest' => ['sometimes', 'array'],
            'recipients_recipe.by_interest.value' => ['sometimes', 'array'],
            'recipients_recipe.by_interest.value.*' => ['sometimes', 'integer'],

            // by_location filter
            'recipients_recipe.by_location' => ['sometimes', 'array'],
            'recipients_recipe.by_location.value' => ['sometimes', 'array'],
            'recipients_recipe.by_location.value.lat' => ['sometimes', 'numeric'],
            'recipients_recipe.by_location.value.lng' => ['sometimes', 'numeric'],
            'recipients_recipe.by_location.value.mode' => ['sometimes', 'string'],
            'recipients_recipe.by_location.value.radius' => ['sometimes', 'numeric'],
            'recipients_recipe.by_location.value.location' => ['sometimes', 'nullable', 'string'],
            'recipients_recipe.by_location.value.country_ids' => ['sometimes', 'array'],
            'recipients_recipe.by_location.value.country_ids.*' => ['sometimes', 'integer'],
            'recipients_recipe.by_location.value.postal_codes' => ['sometimes', 'array'],
            'recipients_recipe.by_location.value.postal_codes.*' => ['sometimes', 'string'],

            // by_departments filter
            'recipients_recipe.by_departments' => ['sometimes', 'array'],
            'recipients_recipe.by_departments.value' => ['sometimes', 'array'],
            'recipients_recipe.by_departments.value.ids' => ['sometimes', 'array'],
            'recipients_recipe.by_departments.value.ids.*' => ['sometimes', 'integer'],
            'recipients_recipe.by_departments.value.behaviors' => ['sometimes', 'array'],
            'recipients_recipe.by_departments.value.behaviors.*' => ['sometimes', 'string'],
            'recipients_recipe.by_departments.value.combine_logic' => ['sometimes', 'boolean'],

            // by_order_value filter
            'recipients_recipe.by_order_value' => ['sometimes', 'array'],
            'recipients_recipe.by_order_value.value' => ['sometimes', 'array'],
            'recipients_recipe.by_order_value.value.value' => ['sometimes', 'boolean'],
            'recipients_recipe.by_order_value.value.date_range' => ['sometimes', 'nullable'],
            'recipients_recipe.by_order_value.value.amount_range' => ['sometimes', 'array'],
            'recipients_recipe.by_order_value.value.amount_range.max' => ['sometimes', 'nullable', 'numeric'],
            'recipients_recipe.by_order_value.value.amount_range.min' => ['sometimes', 'nullable', 'numeric'],

            // by_subdepartment filter
            'recipients_recipe.by_subdepartment' => ['sometimes', 'array'],
            'recipients_recipe.by_subdepartment.value' => ['sometimes', 'array'],
            'recipients_recipe.by_subdepartment.value.ids' => ['sometimes', 'array'],
            'recipients_recipe.by_subdepartment.value.ids.*' => ['sometimes', 'integer'],
            'recipients_recipe.by_subdepartment.value.behaviors' => ['sometimes', 'array'],
            'recipients_recipe.by_subdepartment.value.behaviors.*' => ['sometimes', 'string'],
            'recipients_recipe.by_subdepartment.value.combine_logic' => ['sometimes', 'boolean'],

            // orders_in_basket filter
            'recipients_recipe.orders_in_basket' => ['sometimes', 'array'],
            'recipients_recipe.orders_in_basket.value' => ['sometimes', 'array'],
            'recipients_recipe.orders_in_basket.value.date_range' => ['sometimes', 'array'],
            'recipients_recipe.orders_in_basket.value.date_range.*' => ['sometimes', 'nullable', 'date'],

            'recipients_recipe.orders_in_basket.value.amount_range' => ['sometimes', 'array'],
            'recipients_recipe.orders_in_basket.value.amount_range.max' => ['sometimes', 'nullable', 'numeric'],
            'recipients_recipe.orders_in_basket.value.amount_range.min' => ['sometimes', 'nullable', 'numeric'],

            // orders_collection filter
            'recipients_recipe.orders_collection' => ['sometimes', 'array'],
            'recipients_recipe.orders_collection.value' => ['sometimes', 'array'],
            'recipients_recipe.orders_collection.value.value' => ['sometimes', 'boolean'],
            'recipients_recipe.orders_collection.value.date_range' => ['sometimes', 'nullable'],
            'recipients_recipe.orders_collection.value.amount_range' => ['sometimes', 'nullable'],

            // by_showroom_orders filter
            'recipients_recipe.by_showroom_orders' => ['sometimes', 'array'],
            'recipients_recipe.by_showroom_orders.value' => ['sometimes', 'array'],
            'recipients_recipe.by_showroom_orders.value.value' => ['sometimes', 'boolean'],
            'recipients_recipe.by_showroom_orders.value.date_range' => ['sometimes', 'nullable'],
            'recipients_recipe.by_showroom_orders.value.amount_range' => ['sometimes', 'nullable'],

            // gold_reward_status filter
            'recipients_recipe.gold_reward_status' => ['sometimes', 'array'],
            'recipients_recipe.gold_reward_status.value' => ['sometimes', 'string'],

            // by_family_never_ordered filter
            'recipients_recipe.by_family_never_ordered' => ['sometimes', 'array'],
            'recipients_recipe.by_family_never_ordered.value' => ['sometimes', 'array'],
            'recipients_recipe.by_family_never_ordered.value.*' => ['sometimes', 'integer'],

        ];
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
