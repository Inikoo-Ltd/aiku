<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\CustomerClaim;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_claim_id
 * @property int $transaction_id
 * @property string $code
 * @property string|null $name
 * @property string $quantity
 * @property DeliveryNoteItemReplacementReasonEnum $reason
 * @property array<int, array{id: int, quantity: float, reason: string}> $delivery_note_items
 */
class CustomerClaimLine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason'              => DeliveryNoteItemReplacementReasonEnum::class,
            'delivery_note_items' => 'array',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(CustomerClaim::class, 'customer_claim_id');
    }
}
