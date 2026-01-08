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
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $website_id
 * @property int $website_visitor_id
 * @property int|null $webpage_id
 * @property string $page_url
 * @property string $page_path
 * @property string|null $page_type
 * @property string|null $page_sub_type
 * @property \Illuminate\Support\Carbon $view_date
 * @property int $duration_seconds
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Shop|null $shop
 * @property-read \App\Models\Web\Webpage|null $webpage
 * @property-read \App\Models\Web\Website $website
 * @property-read \App\Models\Web\WebsiteVisitor $websiteVisitor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsitePageView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsitePageView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsitePageView query()
 * @mixin \Eloquent
 */
class WebsitePageView extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'view_date' => 'date',
        ];
    }

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

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function websiteVisitor(): BelongsTo
    {
        return $this->belongsTo(WebsiteVisitor::class);
    }
}
