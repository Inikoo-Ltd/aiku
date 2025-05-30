<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Helpers\Media;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $name
 * @property EmailTemplateStateEnum $state
 * @property bool $is_seeded
 * @property EmailTemplateBuilderEnum $builder
 * @property array<array-key, mixed> $layout
 * @property array<array-key, mixed> $arguments
 * @property array<array-key, mixed> $data
 * @property int|null $screenshot_id
 * @property int $language_id
 * @property \Illuminate\Support\Carbon|null $active_at
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read Media|null $screenshot
 * @property-read Media|null $seoImage
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate query()
 * @mixin \Eloquent
 */
class EmailTemplate extends Model implements HasMedia, Auditable
{
    use HasFactory;
    use InShop;
    use HasHistory;
    use HasSlug;
    use HasImage;

    protected $guarded = [];

    protected $casts = [
        'data'         => 'array',
        'layout'       => 'array',
        'arguments'       => 'array',
        'state'        => EmailTemplateStateEnum::class,
        'builder'      => EmailTemplateBuilderEnum::class,
        'active_at'    => 'datetime',
        'suspended_at' => 'datetime',
    ];

    protected $attributes = [
        'data'   => '{}',
        'layout' => '{}',
        'arguments' => '{}',
    ];


    public function generateTags(): array
    {
        return [
            'comms'
        ];
    }

    protected array $auditInclude = [
        'name',
        'state',
        'provider',
        'active_at',
        'suspended_at',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'screenshot_id');
    }

}
