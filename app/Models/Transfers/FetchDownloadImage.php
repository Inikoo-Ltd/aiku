<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Oct 2024 00:03:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Transfers;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $domain
 * @property string $path
 * @property string $url
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchDownloadImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchDownloadImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchDownloadImage query()
 * @mixin \Eloquent
 */
class FetchDownloadImage extends Model
{
    protected $casts = [
    ];

    protected $attributes = [
    ];

    protected $guarded = [];
}
