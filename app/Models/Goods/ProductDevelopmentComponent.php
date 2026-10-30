<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-16h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A packaging item or other component an in-house SKU needs (a bottle, a pump, a gemstone), picked by its
 * trade unit, with the quantity needed in stock to start production. Each one is approved for purchasing
 * on its own.
 *
 * @property int $id
 * @property int $product_development_id
 * @property int $product_development_item_id
 * @property int $trade_unit_id
 * @property string $quantity
 * @property string|null $note
 * @property ProductDevelopmentComponentStatusEnum $status
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $purchase_confirmed_at when purchasing confirmed it is bought
 * @property int|null $purchase_confirmed_by_id
 * @property string|null $purchase_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopment|null $productDevelopment
 * @property-read ProductDevelopmentItem|null $item
 * @property-read TradeUnit|null $tradeUnit
 * @property-read User|null $approvedBy
 * @property-read User|null $purchaseConfirmedBy
 *
 * @mixin \Eloquent
 */
class ProductDevelopmentComponent extends Model
{
    protected $table = 'product_development_components';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'      => ProductDevelopmentComponentStatusEnum::class,
            'approved_at' => 'datetime',
            'purchase_confirmed_at' => 'datetime',
        ];
    }

    public function productDevelopment(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentItem::class, 'product_development_item_id');
    }

    public function tradeUnit(): BelongsTo
    {
        return $this->belongsTo(TradeUnit::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function purchaseConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchase_confirmed_by_id');
    }
}
