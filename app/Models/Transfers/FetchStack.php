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
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $operation
 * @property int $operation_id
 * @property FetchStackStateEnum $state
 * @property array<array-key, mixed> $result
 * @property array<array-key, mixed> $errors
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon|null $send_to_queue_at
 * @property \Illuminate\Support\Carbon|null $start_fetch_at
 * @property \Illuminate\Support\Carbon|null $finish_fetch_at
 * @property \Illuminate\Support\Carbon|null $error_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
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
