<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Procurement\ShoppingListItem
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_supplier_product_id
 * @property int $supplier_product_id
 * @property int $supplier_id
 * @property int|null $agent_id
 * @property numeric $quantity_units
 * @property int|null $units_per_pack_snapshot
 * @property int|null $units_per_carton_snapshot
 * @property ShoppingListItemPriorityEnum $priority
 * @property ShoppingListItemStateEnum $state
 * @property \Illuminate\Support\Carbon|null $needed_by
 * @property string|null $notes
 * @property int|null $added_by_user_id
 * @property string|null $dismiss_reason
 * @property int|null $dismiss_proposed_by_user_id
 * @property \Illuminate\Support\Carbon|null $dismiss_proposed_at
 * @property int|null $resolved_by_user_id
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int|null $purchase_order_transaction_id
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Agent|null $agent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShoppingListItem> $children
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\Procurement\OrgSupplierProduct $orgSupplierProduct
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read ShoppingListItem|null $parent
 * @property-read Supplier|null $supplier
 * @property-read SupplierProduct|null $supplierProduct
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShoppingListItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShoppingListItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShoppingListItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShoppingListItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShoppingListItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShoppingListItem withoutTrashed()
 * @mixin \Eloquent
 */
class ShoppingListItem extends Model
{
    use InOrganisation;
    use SoftDeletes;

    protected $table = 'shopping_list_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_units'       => 'decimal:3',
            'priority'             => ShoppingListItemPriorityEnum::class,
            'state'                => ShoppingListItemStateEnum::class,
            'needed_by'            => 'date',
            'dismiss_proposed_at'  => 'datetime',
            'resolved_at'          => 'datetime',
        ];
    }

    public function orgSupplierProduct(): BelongsTo
    {
        return $this->belongsTo(OrgSupplierProduct::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ShoppingListItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class, 'parent_id');
    }
}
