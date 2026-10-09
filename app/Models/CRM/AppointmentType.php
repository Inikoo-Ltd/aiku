<?php

namespace App\Models\CRM;

use App\Actions\Utils\Abbreviate;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property AppointmentTypeMeetingModeEnum $meeting_mode
 * @property string|null $location
 * @property int $duration_minutes
 * @property int $buffer_minutes
 * @property int $min_notice_hours
 * @property int $booking_window_days
 * @property int $capacity_per_slot
 * @property bool $is_active
 * @property array<int, array<int, array{from: string, to: string}>> $weekly_hours
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $attendees
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AppointmentTypeDate> $dates
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @mixin \Eloquent
 */
class AppointmentType extends Model implements Auditable
{
    use InShop;
    use SoftDeletes;
    use HasHistory;
    use HasSlug;

    protected $casts = [
        'meeting_mode' => AppointmentTypeMeetingModeEnum::class,
        'is_active'    => 'boolean',
        'weekly_hours' => 'array',
        'data'         => 'array',
    ];

    protected $attributes = [
        'weekly_hours' => '{}',
        'data'         => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->name, 8).'-'.$this->shop->slug;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function generateTags(): array
    {
        return [
            'crm'
        ];
    }

    protected array $auditInclude = [
        'name',
        'description',
        'meeting_mode',
        'location',
        'duration_minutes',
        'buffer_minutes',
        'min_notice_hours',
        'booking_window_days',
        'capacity_per_slot',
        'is_active',
        'weekly_hours',
    ];

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'appointment_type_user')->withTimestamps();
    }

    public function dates(): HasMany
    {
        return $this->hasMany(AppointmentTypeDate::class);
    }
}
