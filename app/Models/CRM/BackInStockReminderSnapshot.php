<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: Tue, 23 Dec 2025 04:34:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Models\CRM;

use App\Models\Catalogue\Product;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackInStockReminderSnapshot extends Model
{
    use InShop;

    protected $fillable = [
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
