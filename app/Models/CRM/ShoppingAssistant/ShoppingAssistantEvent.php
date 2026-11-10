<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Products shown, clicked or added from the assistant. Orders are not events: attribution is a
 * query over these rows and the order lines, with an adjustable window.
 *
 * @property int $id
 * @property int $shopping_assistant_conversation_id
 * @property int|null $shopping_assistant_message_id
 * @property ShoppingAssistantEventTypeEnum $type
 * @property int $product_id
 * @property string|null $group
 * @property int|null $position
 * @property string|null $reason_code
 * @property string|null $reason
 * @property int|null $transaction_id
 * @property int|null $order_id
 * @property string|null $quantity
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read ShoppingAssistantConversation $conversation
 * @property-read ShoppingAssistantMessage|null $message
 * @property-read Product $product
 * @mixin \Eloquent
 */
class ShoppingAssistantEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'       => ShoppingAssistantEventTypeEnum::class,
            'quantity'   => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ShoppingAssistantConversation::class, 'shopping_assistant_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ShoppingAssistantMessage::class, 'shopping_assistant_message_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
