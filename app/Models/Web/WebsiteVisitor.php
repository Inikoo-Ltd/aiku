<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
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
 * @property string $session_id
 * @property int|null $web_user_id
 * @property string $visitor_hash
 * @property string $device_type
 * @property string $os
 * @property string $browser
 * @property string $user_agent
 * @property string $ip_hash
 * @property string|null $country_code
 * @property string|null $city
 * @property int $page_views
 * @property int $duration_seconds
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property string|null $referrer_url
 * @property string|null $landing_page
 * @property string|null $exit_page
 * @property bool $is_bounce
 * @property bool $is_new_visitor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Shop|null $shop
 * @property-read WebUser|null $webUser
 * @property-read \App\Models\Web\Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteVisitor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteVisitor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteVisitor query()
 * @mixin \Eloquent
 */
class WebsiteVisitor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at'  => 'datetime',
            'is_bounce'     => 'boolean',
            'is_new_visitor' => 'boolean',
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

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
    }
}
