<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $website_visitor_id
 * @property int|null $webpage_id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $website_id
 * @property int $shop_id
 * @property string $event_type
 * @property int|null $product_id
 * @property int $quantity
 * @property string $page_url
 * @property string $page_path
 * @property \Illuminate\Support\Carbon $event_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Shop $shop
 * @property-read Website $website
 * @property-read WebsiteVisitor $websiteVisitor
 * @property-read Webpage|null $webpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteConversionEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteConversionEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteConversionEvent query()
 * @mixin \Eloquent
 */
class WebsiteConversionEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function websiteVisitor(): BelongsTo
    {
        return $this->belongsTo(WebsiteVisitor::class);
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }
}
