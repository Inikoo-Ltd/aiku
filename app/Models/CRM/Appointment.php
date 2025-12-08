<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 07 Mar 2024 11:49:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Enums\CRM\Appointment\AppointmentEventEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\CRM\Appointment\AppointmentTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\CRM\Appointment
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string|null $slug
 * @property string $name
 * @property int $shop_id
 * @property int $customer_id
 * @property \Illuminate\Support\Carbon $schedule_at
 * @property string|null $description
 * @property AppointmentStateEnum $state
 * @property AppointmentTypeEnum $type
 * @property AppointmentEventEnum $event
 * @property string $event_address
 * @property string|null $deleted_at
 * @property string|null $delete_comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Shop $shop
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment query()
 *
 * @mixin \Eloquent
 */
class Appointment extends Model
{
    use HasFactory;
    use HasSlug;
    use InCustomer;

    protected $casts = [
        'state' => AppointmentStateEnum::class,
        'type' => AppointmentTypeEnum::class,
        'event' => AppointmentEventEnum::class,
        'schedule_at' => 'datetime',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
