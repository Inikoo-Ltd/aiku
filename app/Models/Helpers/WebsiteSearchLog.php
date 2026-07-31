<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $website_id
 * @property int|null $web_user_id
 * @property string $scope
 * @property string $query
 * @property string|null $session_id
 * @property int $results_count
 * @property string|null $clicked_url
 * @property \Illuminate\Support\Carbon|null $clicked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteSearchLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteSearchLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteSearchLog query()
 * @mixin \Eloquent
 */
class WebsiteSearchLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];
}
