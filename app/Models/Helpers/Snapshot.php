<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 09:32:00 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Http\Resources\Web\SlideResource;
use App\Models\Traits\InGroup;
use App\Models\Web\Slide;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Helpers\Snapshot
 *
 * @property int $id
 * @property int $group_id
 * @property SnapshotScopeEnum $scope
 * @property string|null $publisher_type
 * @property int|null $publisher_id
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property int|null $customer_id
 * @property SnapshotBuilderEnum $builder
 * @property SnapshotStateEnum $state
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $published_until
 * @property string $checksum
 * @property array<array-key, mixed> $layout
 * @property string|null $compiled_layout e.g. html in case of email builders
 * @property string|null $comment
 * @property bool $first_commit
 * @property bool|null $recyclable
 * @property string|null $recyclable_tag
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Model|\Eloquent|null $parent
 * @property-read Model|\Eloquent|null $publisher
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Slide> $slides
 * @property-read \App\Models\Helpers\SnapshotStats|null $stats
 * @method static Builder<static>|Snapshot newModelQuery()
 * @method static Builder<static>|Snapshot newQuery()
 * @method static Builder<static>|Snapshot query()
 * @mixin \Eloquent
 */
class Snapshot extends Model
{
    use InGroup;

    protected $dateFormat = 'Y-m-d H:i:s P';
    protected array $dates = ['published_at', 'published_until'];

    protected $casts = [
        'layout'  => 'array',
        'state'   => SnapshotStateEnum::class,
        'scope'   => SnapshotScopeEnum::class,
        'builder' => SnapshotBuilderEnum::class,
        'published_at' => 'datetime',
    ];

    protected $attributes = [
        'layout' => '{}'
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'helpers',
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description',
        'status',
        'state',
        'price',
        'currency_id',
        'units',
        'unit',
        'barcode',
        'rrp',
        'unit_relationship_type'
    ];

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function publisher(): MorphTo
    {
        return $this->morphTo();
    }

    public function stats(): hasOne
    {
        return $this->hasOne(SnapshotStats::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class);
    }

    public function compiledLayout(): array|string
    {
        switch (class_basename($this->parent)) {
            case 'Banner':
                $slides         = $this->slides()->where('visibility', true)->get();
                $compiledLayout = $this->layout;
                data_set($compiledLayout, 'components', json_decode(SlideResource::collection($slides)->toJson(), true));
                data_set($compiledLayout, 'type', $this->parent->type);

                return $compiledLayout;
            case 'Website':
            case 'Webpage':
                return $this->layout['html'];
            default:
                return [];
        }
    }
}
