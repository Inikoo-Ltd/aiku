<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 13 Feb 2026 08:49:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\InShop;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEmailRecipient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEmailRecipient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEmailRecipient query()
 * @mixin \Eloquent
 */
class ExternalEmailRecipient extends Model
{
    use InShop;

    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
