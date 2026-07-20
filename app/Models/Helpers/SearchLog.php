<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int|null $user_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $warehouse_id
 * @property string $scope
 * @property string $query
 * @property string|null $session_id
 * @property int $results_count
 * @property string|null $clicked_url
 * @property \Illuminate\Support\Carbon|null $clicked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchLog query()
 * @mixin \Eloquent
 */
class SearchLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];
}
