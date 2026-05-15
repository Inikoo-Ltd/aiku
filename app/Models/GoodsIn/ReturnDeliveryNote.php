<?php

/*
 * author Louis Perez
 * created on 30-04-2026-13h-16m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int $delivery_note_id
 * @property int $order_id
 * @property string $slug
 * @property string $reference
 * @property ReturnDeliveryNoteStateEnum $state
 * @property string|null $customer_notes
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property string|null $shipping_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $returning_at
 * @property string|null $returned_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|null $handler_id Main handler
 * @property int|null $handler_user_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read DeliveryNote|null $deliveryNote
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Employee|null $handler
 * @property-read User|null $handlerUser
 * @property-read Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsIn\ReturnDeliveryNoteItem> $returnDeliveryNoteItem
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNote onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNote withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNote withoutTrashed()
 * @mixin \Eloquent
 */
class ReturnDeliveryNote extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InCustomer;
    use HasHistory;

    protected $table = 'return_delivery_notes';

    protected $casts = [
        'state'        => ReturnDeliveryNoteStateEnum::class,
        'queued_at'    => 'datetime',
        'handling_at'  => 'datetime',
        'picked_at'    => 'datetime',
        'received_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $attributes = [

    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'state',
        'reference',
        'queued_at',
        'handling_at',
        'picked_at',
        'received_at',
        'cancelled_at',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function returnDeliveryNoteItem(): HasMany
    {
        return $this->hasMany(ReturnDeliveryNoteItem::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handler_id');
    }

    public function handlerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_user_id');
    }
}
