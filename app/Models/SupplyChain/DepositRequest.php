<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\InGroup;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $agent_id
 * @property int|null $organisation_id
 * @property string|null $notes
 * @property string|null $reference
 * @property int $currency_id
 * @property DepositRequestStateEnum $state
 * @property \Illuminate\Support\Carbon $requested_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $approved_by
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property-read User|null $rejecter
 * @property \Illuminate\Support\Carbon|null $last_reminded_at
 * @property int|null $last_reminded_by
 * @property int $number_reminders
 * @property-read User|null $reminder
 * @property-read Agent $agent
 * @property-read Currency $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DepositRequestItem> $items
 */
class DepositRequest extends Model
{
    use InGroup;

    protected $guarded = [];

    protected $casts = [
        'state'        => DepositRequestStateEnum::class,
        'requested_at' => 'datetime',
        'settled_at'   => 'datetime',
        'cancelled_at' => 'datetime',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'last_reminded_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reminded_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Transfers the client recorded against this request, in the agent's ledger.
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(AgentPayment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DepositRequestItem::class);
    }
}
