<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Helpers;

use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Production\Artefact;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;
use App\Models\Traits\HasSearch;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string $name
 * @property TagScopeEnum $scope
 * @property array<array-key, mixed> $data
 * @property int $number_models
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $image_id
 * @property array<array-key, mixed>|null $web_image
 * @property array<array-key, mixed> $label
 * @property int|null $order_from_month
 * @property int|null $order_to_month
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $customers
 * @property-read array $translatable_columns_from
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation|null $organisation
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read Shop|null $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TradeUnit> $tradeUnits
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereLocales(string $column, array $locales)
 * @mixin \Eloquent
 */

#[Translatable('label')]
class Tag extends Model implements HasMedia
{
    use HasSearch;
    use HasSlug;
    use HasImage;
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'web_image' => 'array',
        'scope'     => TagScopeEnum::class,
    ];

    protected $attributes = [
        'data'      => '{}',
        'web_image' => '[]',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                // Shop level: tag-slug + shop-slug
                if ($this->shop_id) {
                    return $this->name.'-'.$this->shop->slug;
                }

                // Organisation level: tag-slug + org-slug
                if ($this->organisation_id) {
                    return $this->name.'-'.$this->organisation->slug;
                }

                // Group level: tag-slug only (group is unique)
                return $this->name;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * SQL that is true for a stock whose trade units (or their families) carry seasonal tags, none of
     * which is in its ordering window this month. A window may wrap the year end (November to February).
     */
    public static function outOfOrderingSeasonSql(string $stockIdColumn): string
    {
        $month = (int) now()->month;

        return "coalesce((select bool_and(not case
                when season_tags.order_from_month <= season_tags.order_to_month then $month between season_tags.order_from_month and season_tags.order_to_month
                else $month >= season_tags.order_from_month or $month <= season_tags.order_to_month end)
            from model_has_trade_units season_units
            join trade_units season_trade_units on season_trade_units.id = season_units.trade_unit_id
            join model_has_tags season_links on (season_links.model_type = 'TradeUnit' and season_links.model_id = season_trade_units.id)
                or (season_links.model_type = 'TradeUnitFamily' and season_links.model_id = season_trade_units.trade_unit_family_id)
            join tags season_tags on season_tags.id = season_links.tag_id
            where season_units.model_type = 'Stock'
                and season_units.model_id = $stockIdColumn
                and season_tags.order_from_month is not null
                and season_tags.order_to_month is not null), false)";
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphedByMany(TradeUnit::class, 'model', 'model_has_tags');
    }

    public function artefacts(): MorphToMany
    {
        return $this->morphedByMany(Artefact::class, 'model', 'model_has_tags');
    }

    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'model', 'model_has_tags');
    }

    public function toSearchableArray(): array
    {
        return [
            'id'         => (string)$this->id,
            'group_id'   => $this->group_id,
            'name'       => (string)$this->name,
            'created_at' => $this->created_at?->timestamp ?? 0,
        ];
    }

}
