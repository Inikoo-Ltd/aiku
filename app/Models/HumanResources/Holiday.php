<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 13:02:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Holiday\HolidayTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property HolidayTypeEnum $type
 * @property int $year
 * @property string|null $label
 * @property \Illuminate\Support\Carbon $from
 * @property \Illuminate\Support\Carbon $to
 * @property array<array-key, mixed> $data
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holiday newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holiday newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holiday query()
 * @mixin \Eloquent
 */
class Holiday extends Model implements Auditable
{
    use HasHistory;
    use InOrganisation;


    protected $casts = [
        'data' => 'array',
        'type' => HolidayTypeEnum::class,
        'from' => 'date',
        'to'   => 'date',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'hr'
        ];
    }

    protected array $auditInclude = [
        'label',
        'from',
        'to',

    ];

}
