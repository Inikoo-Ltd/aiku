<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\SysAdmin;

use App\Models\Inventory\Warehouse;
use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileOfflineOperation extends Model
{
    public const string STATE_APPLIED = 'applied';
    public const string STATE_PARTLY_APPLIED = 'partly_applied';
    public const string STATE_REFUSED = 'refused';
    public const string STATE_CONFLICT = 'conflict';
    public const string STATE_BLOCKED = 'blocked';
    public const string STATE_RESOLVED = 'resolved';
    public const string STATE_ROLLED_BACK = 'rolled_back';
    public const string STATE_EXPIRED = 'expired';
    public const string STATE_VOID = 'void';

    public const string OPERATION_VOID = 'void';
    public const string OPERATION_ROLLBACK = 'rollback';

    public const array OPEN_STATES = [self::STATE_REFUSED, self::STATE_PARTLY_APPLIED, self::STATE_CONFLICT, self::STATE_BLOCKED];

    public const array BLOCKING_STATES = [self::STATE_REFUSED, self::STATE_CONFLICT, self::STATE_BLOCKED];

    public const array CLOSED_STATES = [self::STATE_APPLIED, self::STATE_RESOLVED, self::STATE_ROLLED_BACK, self::STATE_VOID];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'touched_rows'       => 'array',
            'payload'            => 'array',
            'server_snapshot'    => 'array',
            'requested_quantity' => 'float',
            'applied_quantity'   => 'float',
            'counted_since'      => 'boolean',
            'device_sequence'    => 'integer',
            'seen_at'            => 'immutable_datetime',
            'performed_at'       => 'immutable_datetime',
            'synced_at'          => 'immutable_datetime',
            'resolved_at'        => 'immutable_datetime',
            'expired_at'         => 'immutable_datetime',
            'rolled_back_at'     => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function staffTask(): BelongsTo
    {
        return $this->belongsTo(StaffTask::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'blocked_by_id');
    }

    public function rollbackOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rollback_of_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(MobileOfflineOperationEffect::class);
    }

    public function conflictingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conflicting_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->state, self::OPEN_STATES, true);
    }

    /**
     * @return array<int, int>
     */
    public function touchedLocationOrgStockIds(): array
    {
        return collect($this->touched_rows ?? [])
            ->filter(fn (array $row) => ($row['t'] ?? null) === 'LocationOrgStock' && isset($row['id']))
            ->map(fn (array $row) => (int) $row['id'])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
