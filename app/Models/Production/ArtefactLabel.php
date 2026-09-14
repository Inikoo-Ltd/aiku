<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Models\Production;

use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $artefact_id
 * @property string $name
 * @property int|null $artwork_id
 * @property array<array-key, mixed> $layout
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Artefact|null $artefact
 * @property-read Media|null $artwork
 * @property-read Group|null $group
 * @property-read Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactLabel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactLabel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactLabel query()
 * @mixin \Eloquent
 */
class ArtefactLabel extends Model
{
    use SoftDeletes;

    protected $table = 'artefact_labels';

    protected $guarded = [];

    protected $attributes = [
        'layout' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
        ];
    }

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class);
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'artwork_id');
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
