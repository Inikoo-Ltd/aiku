<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\CustomerClaim;

use App\Enums\CRM\CustomerClaim\CustomerClaimKindEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A customer's own claim on a dispatched order (HELP-4869), made in Merlin, the account or the
 * chat. It is settled with the staff Claim panel's actions (PlanChatClaim::settle).
 *
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int|null $order_id
 * @property int|null $refund_id
 * @property CustomerClaimKindEnum $kind
 * @property int|null $shopping_assistant_conversation_id
 * @property string $reference
 * @property CustomerClaimSourceEnum $source
 * @property CustomerClaimStateEnum $state
 * @property string $resolution
 * @property string $amount
 * @property string|null $currency_code
 * @property string|null $comment
 * @property string|null $rejection_reason
 * @property string|null $rejection_note
 * @property array<string, mixed>|null $result
 * @property int|null $reviewed_by_user_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Order|null $order
 * @property-read Invoice|null $refund
 * @property-read Shop $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerClaimLine> $lines
 */
class CustomerClaim extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const string PHOTOS = 'customer_claim_photos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind'        => CustomerClaimKindEnum::class,
            'source'      => CustomerClaimSourceEnum::class,
            'state'       => CustomerClaimStateEnum::class,
            'result'      => 'array',
            'reviewed_at' => 'datetime',
            'settled_at'  => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'refund_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ShoppingAssistantConversation::class, 'shopping_assistant_conversation_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerClaimLine::class);
    }
}
