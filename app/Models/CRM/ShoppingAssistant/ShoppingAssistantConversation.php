<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\Customer;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property-read Website|null $website
 * @property string $ulid
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $website_id
 * @property int|null $customer_id
 * @property int|null $web_user_id
 * @property int|null $website_visitor_id
 * @property int|null $chat_session_id
 * @property ShoppingAssistantEntryPointEnum $entry_point
 * @property string $language_code
 * @property ShoppingAssistantConversationStatusEnum $status
 * @property int $messages_count
 * @property int $model_calls
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string $cost
 * @property string|null $model
 * @property \Illuminate\Support\Carbon|null $handed_over_at
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShoppingAssistantEvent> $events
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShoppingAssistantMessage> $messages
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @mixin \Eloquent
 */
class ShoppingAssistantConversation extends Model
{
    use InShop;

    public const int PENDING_SECONDS = 150;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_point'     => ShoppingAssistantEntryPointEnum::class,
            'status'          => ShoppingAssistantConversationStatusEnum::class,
            'cost'            => 'decimal:6',
            'handed_over_at'  => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ShoppingAssistantMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShoppingAssistantEvent::class);
    }

    public function handOvers(): HasMany
    {
        return $this->hasMany(ShoppingAssistantHandOver::class);
    }

    /**
     * Waiting for an answer: the latest message is the customer's and is younger than the answer job's timeout.
     */
    public function isPending(): bool
    {
        $latest = $this->messages()->latest('id')->first(['role', 'created_at']);

        return $latest?->role === ShoppingAssistantMessageRoleEnum::CUSTOMER && $latest->created_at->gt(now()->subSeconds(self::PENDING_SECONDS));
    }
}
