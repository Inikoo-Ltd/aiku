<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Transfers;

use App\Enums\Transfers\FetchStack\FetchStackStateEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;

/**
 * @property FetchStackStateEnum $state
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchStack newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchStack newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchStack query()
 * @mixin \Eloquent
 */
class FetchStack extends Model
{
    use InOrganisation;

    protected $casts = [
        'result'           => 'array',
        'errors'           => 'array',
        'state'            => FetchStackStateEnum::class,
        'submitted_at'     => 'datetime',
        'send_to_queue_at' => 'datetime',
        'start_fetch_at'   => 'datetime',
        'finish_fetch_at'  => 'datetime',
        'error_at'         => 'datetime',
    ];

    protected $attributes = [
        'result' => '{}',
        'errors' => '{}'
    ];

    protected $guarded = [];

}
