<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 23:11:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $url
 * @property bool $is_up
 * @property int|null $status_code
 * @property string|null $error_message
 * @property string|null $last_deployment_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteHealthLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteHealthLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteHealthLog query()
 * @mixin \Eloquent
 */
class WebsiteHealthLog extends Model
{
    protected $fillable = [
        'url',
        'is_up',
        'status_code',
        'error_message',
        'last_deployment_date',
    ];

    protected $casts = [
        'is_up'       => 'boolean',
        'status_code' => 'integer',
    ];
}
