<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: Tue, 23 Dec 2025 04:34:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Models\Comms;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $back_in_stock_reminder_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $family_id
 * @property int|null $sub_department_id
 * @property int|null $department_id
 * @property string|null $reminder_cancelled_at
 * @property string|null $reminder_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Product|null $product
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackInStockReminderSnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackInStockReminderSnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackInStockReminderSnapshot query()
 * @mixin \Eloquent
 */
class BackInStockReminderSnapshot extends Model
{
    use InShop;

    protected $fillable = [
        'back_in_stock_reminder_id',
        'group_id',
        'organisation_id',
        'shop_id',
        'customer_id',
        'product_id',
        'department_id',
        'sub_department_id',
        'family_id',
        'reminder_cancelled_at',
        'reminder_sent_at'
      ];

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
