<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 14:32:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int|null $supplier_product_id
 * @property int|null $historic_supplier_product_id
 * @property int|null $org_supplier_product_id
 * @property int|null $stock_id Null allowed when org_stock is exclusive to an organization
 * @property int $org_stock_id
 * @property StockDeliveryItemStateEnum $state
 * @property array<array-key, mixed> $data
 * @property numeric $unit_quantity
 * @property numeric $unit_quantity_checked
 * @property numeric $unit_quantity_placed
 * @property string $net_unit_price
 * @property string $gross_unit_price
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property bool $is_costed
 * @property string $gross_amount
 * @property string|null $grp_gross_amount
 * @property string|null $org_gross_amount
 * @property string|null $grp_exchange
 * @property string|null $org_exchange
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $not_received_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $placed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $deleted_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Procurement\StockDelivery $stockDelivery
 * @property-read SupplierProduct|null $supplierProduct
 * @method static \Database\Factories\Procurement\StockDeliveryItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockDeliveryItem newModelQuery()
 * @method static Builder<static>|StockDeliveryItem newQuery()
 * @method static Builder<static>|StockDeliveryItem query()
 * @mixin Eloquent
 */
class StockDeliveryItem extends Model
{
    use HasFactory;
    use InOrganisation;

    protected $casts = [
        'state'           => StockDeliveryItemStateEnum::class,
        'data'            => 'array',
        'unit_quantity'   => 'decimal:4',
        'unit_quantity_checked' => 'decimal:4',
        'unit_quantity_placed'  => 'decimal:4',
        'unit_price'      => 'decimal:4',

        'dispatched_at'   => 'datetime',
        'not_received_at' => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
        'placed_at'       => 'datetime',
        'cancelled_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
