<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 20:53:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Helpers\Ticket;
use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stock value the nightly check (CheckStockValues) finds implausible, who has to act on it
 * and what was raised for them. It resolves itself when a later check no longer finds it.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_stock_id
 * @property string $rule
 * @property string $owner
 * @property string $state
 * @property string $excess_amount
 * @property array<string, mixed> $facts
 * @property string|null $explanation
 * @property int|null $cause_org_stock_movement_id
 * @property int|null $location_id
 * @property int|null $staff_task_id
 * @property int|null $ticket_id
 * @property string|null $ticket_key
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property-read OrgStock $orgStock
 * @property-read Location|null $location
 * @property-read StaffTask|null $staffTask
 * @property-read Ticket|null $ticket
 */
class StockValueFinding extends Model
{
    public const string RULE_COUNT = 'count_above_history';
    public const string RULE_PRICE = 'price_above_purchases';

    public const string OWNER_WAREHOUSE   = 'warehouse';
    public const string OWNER_PROCUREMENT = 'procurement';
    public const string OWNER_ENGINEERING = 'engineering';
    public const string OWNER_LEGACY      = 'legacy';

    public const string STATE_OPEN     = 'open';
    public const string STATE_RESOLVED = 'resolved';

    protected $guarded = [];

    protected $attributes = [
        'facts' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'facts'         => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at'  => 'datetime',
            'resolved_at'   => 'datetime',
        ];
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function staffTask(): BelongsTo
    {
        return $this->belongsTo(StaffTask::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
