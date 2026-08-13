<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CRM\TrafficSourceClick
 *
 * @property int $id
 * @property int $shop_id
 * @property int|null $website_id
 * @property int|null $webpage_id
 * @property string $type
 * @property string|null $campaign_ref
 * @property string|null $click_id
 * @property string|null $ip
 * @property string|null $country_code
 * @property string|null $device_type
 * @property bool $is_bot
 * @property string|null $user_agent
 * @property string|null $url
 * @property bool $is_repeat
 * @property \Illuminate\Support\Carbon $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceClick newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceClick newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceClick query()
 * @mixin \Eloquent
 */
class TrafficSourceClick extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_bot'     => 'boolean',
            'is_repeat'  => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
