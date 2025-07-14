<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Inventory;

use App\Actions\Utils\Abbreviate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $reference
 * @property int $warehouse_id
 * @property int $user_id
 * @property PickingSessionStateEnum $state
 * @property int $number_trolleys
 * @property int $number_delivery_notes
 * @property int $numbe_trolleys_picked
 * @property int $number_delivery_notes_picked
 * @property int $number_locations
 * @property int $number_locations_picked
 * @property int $number_picking_session_items
 * @property int $number_picking_session_items_picked
 * @property string|null $start_at
 * @property string|null $end_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNote> $deliveryNotes
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\PickingSessionItem> $pickingSessionItem
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\Trolley> $trolleys
 * @property-read User $user
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingSession query()
 * @mixin \Eloquent
 */
class PickingSession extends Model
{
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    protected $casts = [
        'state'                  => PickingSessionStateEnum::class,
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->reference, digits: true, maximumLength: 4);
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryNote::class,
            'picking_session_has_delivery_notes',
            'picking_session_id',
            'delivery_note_id'
        );
    }

    public function trolleys(): HasMany
    {
        return $this->hasMany(Trolley::class);
    }

    public function pickingSessionItem(): HasMany
    {
        return $this->hasMany(PickingSessionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
