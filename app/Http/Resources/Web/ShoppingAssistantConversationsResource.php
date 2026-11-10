<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Web\ShoppingAssistant\UI\ShowShoppingAssistantPerformance;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingAssistantConversation
 */
class ShoppingAssistantConversationsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ulid'               => $this->ulid,
            'created_at'         => $this->created_at?->toIso8601String(),
            'customer_name'      => $this->customer_name,
            'customer_reference' => $this->customer_reference,
            'customer_route'     => $this->customer_slug ? [
                'name'       => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [$this->organisation_slug, $this->shop_slug, $this->customer_slug],
            ] : null,
            'website'            => $this->website_domain,
            'entry_point'        => $this->entry_point->value,
            'entry_point_label'  => ShowShoppingAssistantPerformance::entryPointLabel($this->entry_point->value),
            'first_question'     => $this->first_question,
            'messages_count'     => (int) $this->messages_count,
            'shown'              => (int) $this->shown,
            'clicked'            => (int) $this->clicked,
            'added'              => (int) $this->added,
            'ordered'            => (bool) $this->ordered,
            'revenue'            => (float) $this->revenue,
            'cost'               => (float) $this->cost_in_page_currency,
            'status'             => $this->status->value,
            'status_label'       => self::statusLabel($this->status),
        ];
    }

    public static function statusLabel(ShoppingAssistantConversationStatusEnum $status): string
    {
        return match ($status) {
            ShoppingAssistantConversationStatusEnum::OPEN        => __('Open'),
            ShoppingAssistantConversationStatusEnum::HANDED_OVER => __('Handed over'),
            ShoppingAssistantConversationStatusEnum::CLOSED      => __('Closed'),
        };
    }
}
