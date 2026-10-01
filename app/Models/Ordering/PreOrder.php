<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * HELP-3432. The order a customer pre-ordered, held out of the warehouse until its goods are
 * here and its balance is paid. When a basket mixed in-stock and pre-order items it was split
 * off at submit and parent_order_id is the in-stock order.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int $order_id
 * @property int|null $parent_order_id
 * @property PreOrderStateEnum $state
 * @property bool $is_trade
 * @property bool $has_back_order
 * @property bool $has_made_to_order
 * @property bool $has_pallet_delivery
 * @property string $upfront_amount
 * @property string $deferred_amount
 * @property \Illuminate\Support\Carbon|null $estimated_dispatch_from
 * @property \Illuminate\Support\Carbon|null $estimated_dispatch_to
 * @property \Illuminate\Support\Carbon|null $free_cancellation_until
 * @property \Illuminate\Support\Carbon|null $supplier_ordered_at
 * @property \Illuminate\Support\Carbon|null $goods_arrived_at
 * @property \Illuminate\Support\Carbon|null $balance_requested_at
 * @property \Illuminate\Support\Carbon|null $balance_due_at
 * @property \Illuminate\Support\Carbon|null $balance_first_reminder_sent_at
 * @property \Illuminate\Support\Carbon|null $balance_second_reminder_sent_at
 * @property \Illuminate\Support\Carbon|null $balance_paid_at
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $pallet_estimate_amount
 * @property string|null $pallet_quote_amount
 * @property array $terms
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Order|null $parentOrder
 * @property-read Customer $customer
 * @property-read Shop $shop
 */
class PreOrder extends Model
{
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'state'                           => PreOrderStateEnum::class,
        'is_trade'                        => 'boolean',
        'has_back_order'                  => 'boolean',
        'has_made_to_order'               => 'boolean',
        'has_pallet_delivery'             => 'boolean',
        'upfront_amount'                  => 'decimal:2',
        'deferred_amount'                 => 'decimal:2',
        'estimated_dispatch_from'         => 'date',
        'estimated_dispatch_to'           => 'date',
        'free_cancellation_until'         => 'datetime',
        'supplier_ordered_at'             => 'datetime',
        'goods_arrived_at'                => 'datetime',
        'balance_requested_at'            => 'datetime',
        'balance_due_at'                  => 'datetime',
        'balance_first_reminder_sent_at'  => 'datetime',
        'balance_second_reminder_sent_at' => 'datetime',
        'balance_paid_at'                 => 'datetime',
        'released_at'                     => 'datetime',
        'cancelled_at'                    => 'datetime',
        'pallet_estimate_amount'          => 'decimal:2',
        'pallet_quote_amount'             => 'decimal:2',
        'terms'                           => 'array',
        'data'                            => 'array',
    ];

    protected $attributes = [
        'terms' => '[]',
        'data'  => '{}',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Past the promised dispatch by the shop's margin, the customer may cancel for a full refund.
     */
    public function isLate(): bool
    {
        return $this->estimated_dispatch_to
            && now()->startOfDay()->gt($this->estimated_dispatch_to->copy()->addDays((int) $this->shop->preOrderSetting('late_cancellation_days')));
    }

    public function isWithinFreeCancellation(): bool
    {
        return !$this->supplier_ordered_at && $this->free_cancellation_until && now()->lte($this->free_cancellation_until);
    }

    /**
     * The customer paid a deposit for these lines and amounts, so while the pre-order is open its
     * order is locked: money-related changes need a member of staff to unlock it for themselves first.
     */
    public function isLocked(): bool
    {
        return in_array($this->state, PreOrderStateEnum::open(), true);
    }

    public function unlockedUntil(): ?Carbon
    {
        $until = Arr::get($this->data, 'unlock.until');

        return $until ? Carbon::parse($until) : null;
    }

    public function unlockedByUserId(): ?int
    {
        return Arr::get($this->data, 'unlock.user_id');
    }

    public function isUnlockedFor(?User $user): bool
    {
        return $user
            && $this->unlockedByUserId() === $user->id
            && $this->unlockedUntil()?->isFuture();
    }

    public function canBeEditedBy(?User $user): bool
    {
        return !$this->isLocked() || $this->isUnlockedFor($user);
    }

    /**
     * Every change of state goes through here, inside a transaction: the row is locked and re-read,
     * so two requests or jobs racing on one pre-order cannot both move it, and a cancelled or
     * released pre-order never comes back.
     *
     * @param  array<int, PreOrderStateEnum>  $states
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function lockInState(array $states): static
    {
        $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
        $this->setRawAttributes($locked->getAttributes(), true);

        if (!in_array($this->state, $states, true)) {
            throw ValidationException::withMessages([
                'pre_order' => __('This pre-order is :state, it cannot be changed this way.', ['state' => $this->state->label()]),
            ]);
        }

        return $this;
    }

    public function lockMessage(): string
    {
        return __('🔒 This is a pre-order the customer has paid a deposit for. Unlock it from the pre-order panel before changing it.');
    }
}
