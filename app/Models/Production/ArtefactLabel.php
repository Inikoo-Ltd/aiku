<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Models\Production;

use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Models\Helpers\Media;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $artefact_id
 * @property int $org_stock_id
 * @property string $name
 * @property int|null $artwork_id
 * @property array<array-key, mixed> $layout
 * @property ArtefactLabelStateEnum $state
 * @property array<array-key, mixed> $on_artwork
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Artefact|null $artefact
 * @property-read Media|null $artwork
 * @property-read Group|null $group
 * @property-read OrgStock $orgStock
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
        'layout'     => '{}',
        'on_artwork' => '[]',
        'state'      => 'raw',
    ];

    protected function casts(): array
    {
        return [
            'layout'       => 'array',
            'on_artwork'   => 'array',
            'state'        => ArtefactLabelStateEnum::class,
            'published_at' => 'datetime',
        ];
    }

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    /**
     * What the org stock says every label must carry and this one does not: neither placed as a
     * text on the label nor confirmed as already printed on the artwork.
     *
     * @return array<int, string>
     */
    public function missingMandatoryInformation(): array
    {
        $placed = array_map(
            fn (array $field) => $field['source'] ?? ArtefactLabelInformationEnum::BATCH_CODE->value,
            $this->layout['fields'] ?? []
        );

        return array_values(array_diff(
            $this->orgStock->label_mandatory_information ?? [],
            $placed,
            $this->on_artwork ?? []
        ));
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
