<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use App\Enums\CRM\Customer\CustomerWebActivityTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $website_id
 * @property int $customer_id
 * @property int|null $web_user_id
 * @property int|null $website_visitor_id
 * @property CustomerWebActivityTypeEnum $activity_type
 * @property string $page_url
 * @property string $page_path
 * @property string|null $page_type
 * @property string|null $page_sub_type
 * @property int|null $webpage_id
 * @property int|null $product_id
 * @property int $quantity
 * @property int $duration_seconds
 * @property \Illuminate\Support\Carbon $activity_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Shop $shop
 * @property-read \App\Models\CRM\WebUser|null $webUser
 * @property-read Webpage|null $webpage
 * @property-read Website $website
 * @property-read WebsiteVisitor|null $websiteVisitor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerWebActivity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerWebActivity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerWebActivity query()
 * @mixin \Eloquent
 */
class CustomerWebActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activity_type' => CustomerWebActivityTypeEnum::class,
            'activity_date' => 'date',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
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
