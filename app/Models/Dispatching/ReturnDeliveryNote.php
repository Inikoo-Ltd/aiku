<?php

/*
 * author Louis Perez
 * created on 30-04-2026-13h-16m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\Dispatching;

use App\Enums\Dispatching\DeliveryNote\Return\ReturnDeliveryNoteStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ReturnDeliveryNote extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InCustomer;
    use HasHistory;

    protected $table = 'return_delivery_notes';

    protected $casts = [
        'return_state'         => ReturnDeliveryNoteStateEnum::class,
        'queued_at'     => 'datetime',
        'handling_at'   => 'datetime',
        'picked_at'     => 'datetime',
        'received_at'   => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    protected $attributes = [

    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'return_state',
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
    
    public function picker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'picker_id');
    }

    public function pickerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_user_id');
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'packer_id');
    }

    public function packerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packer_user_id');
    }

    // TODO
    // public function trolleys(): BelongsToMany
    // {
    //     return $this->belongsToMany(Trolley::class, 'delivery_note_has_trolleys');
    // }

    // public function pickedBays(): BelongsToMany
    // {
    //     return $this->belongsToMany(PickedBay::class, 'picked_bay_has_delivery_notes');
    // }
}
