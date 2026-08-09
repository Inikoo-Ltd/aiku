<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\Artefact\ArtefactComplianceTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $artefact_id
 * @property ArtefactComplianceTypeEnum $type
 * @property string|null $reference
 * @property string|null $notes
 * @property bool $is_required
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Production\Artefact|null $artefact
 * @property-read Group|null $group
 * @property-read Organisation|null $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactComplianceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactComplianceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactComplianceItem query()
 * @mixin \Eloquent
 */
class ArtefactComplianceItem extends Model
{
    protected $table = 'artefact_compliance_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'        => ArtefactComplianceTypeEnum::class,
            'is_required' => 'bool',
            'valid_from'  => 'date',
            'valid_until' => 'date',
        ];
    }

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
