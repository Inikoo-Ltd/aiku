<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Analytics;

use App\Models\CRM\WebUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $website_id
 * @property int $web_user_id
 * @property string $date
 * @property string $route_name
 * @property string $route_params
 * @property string $os
 * @property string $device
 * @property string $browser
 * @property string $ip_address
 * @property string $location
 * @property int|null $organisation_id
 * @property int|null $webpage_id
 * @property-read WebUser $webUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserRequest query()
 * @mixin \Eloquent
 */
class WebUserRequest extends Model
{
    public $timestamps = false;

    protected $guarded = [
    ];

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
    }
}
