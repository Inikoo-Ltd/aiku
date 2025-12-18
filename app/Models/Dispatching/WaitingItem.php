<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Dec 2025 12:13:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\WaitingItem\WaitingItemStateEnum;
use App\Enums\Dispatching\WaitingItem\WaitingItemStatusEnum;
use App\Enums\Dispatching\WaitingItem\WaitingItemTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int $shop_id
 * @property int $order_id
 * @property int $delivery_note_id
 * @property int $transaction_id
 * @property int $delivery_note_item_id
 * @property WaitingItemTypeEnum $type
 * @property WaitingItemStateEnum $state
 * @property WaitingItemStatusEnum $status
 * @property \Illuminate\Support\Carbon|null $state_to_do_at
 * @property \Illuminate\Support\Carbon|null $state_escalated_at
 * @property \Illuminate\Support\Carbon|null $state_in_progress_at
 * @property \Illuminate\Support\Carbon|null $state_done_at
 * @property \Illuminate\Support\Carbon|null $state_cancelled_at
 * @property \Illuminate\Support\Carbon|null $status_to_do_at
 * @property \Illuminate\Support\Carbon|null $status_in_progress_at
 * @property \Illuminate\Support\Carbon|null $status_done_at
 * @property int|null $reporter_user_id
 * @property int|null $assignee_user_id
 * @property int|null $product_id
 * @property int|null $org_stock_id
 * @property int|null $location_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitingItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitingItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitingItem query()
 * @mixin \Eloquent
 */
class WaitingItem extends Model implements Auditable
{
    use HasHistory;
    use InWarehouse;

    protected $casts = [
        'data'                  => 'array',
        'state_to_do_at'        => 'datetime',
        'state_escalated_at'    => 'datetime',
        'state_in_progress_at'  => 'datetime',
        'state_done_at'         => 'datetime',
        'state_cancelled_at'    => 'datetime',
        'status_to_do_at'       => 'datetime',
        'status_in_progress_at' => 'datetime',
        'status_done_at'        => 'datetime',
        'type'                  => WaitingItemTypeEnum::class,
        'status'                => WaitingItemStatusEnum::class,
        'state'                 => WaitingItemStateEnum::class,

    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'warehouse'
        ];
    }

    protected array $auditInclude = [
        'state',
        'status',
        'assignee_user_id',
    ];

}
