<?php

/*
 * author Arya Permana - Kirin
 * created on 14-11-2024-10h-10m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Helpers;

use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $warehouse_id
 * @property int|null $user_id The user who created/inputted the feedback
 * @property string $origin_source
 * @property string $origin_type
 * @property int $origin_id
 * @property bool $blame_supplier
 * @property bool $blame_picker
 * @property bool $blame_packer
 * @property bool $blame_warehouse
 * @property bool $blame_courier
 * @property bool $blame_marketing
 * @property bool $blame_customer
 * @property bool $blame_other
 * @property string|null $message
 * @property array<array-key, mixed> $data
 * @property string|null $source_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Model|\Eloquent $origin
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transaction
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read User|null $user
 * @property-read Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback withoutTrashed()
 * @mixin \Eloquent
 */
class Feedback extends Model implements Auditable
{
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use InShop;

    protected $table = 'feedbacks';

    protected $casts = [
        'data'               => 'array',
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'message',
    ];

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transaction(): MorphToMany
    {
        return $this->morphedByMany(Transaction::class, 'model', 'model_has_feedbacks');
    }


}
