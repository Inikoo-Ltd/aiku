<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person who gets the weekly SEO report by email: of one shop, or of every website when `shop_id`
 * is null.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $shop_id
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoReportSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoReportSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoReportSubscription query()
 * @mixin \Eloquent
 */
class SeoReportSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
