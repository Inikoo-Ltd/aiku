<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Transaction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Procurement\PartnerShoppingListItem
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_partner_id
 * @property int $partner_organisation_id
 * @property int $stock_id
 * @property int $org_stock_id
 * @property numeric $quantity
 * @property ShoppingListItemPriorityEnum $priority
 * @property ShoppingListItemStateEnum $state
 * @property \Illuminate\Support\Carbon|null $needed_by
 * @property string|null $notes
 * @property int|null $added_by_user_id
 * @property int|null $transaction_id
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartnerShoppingListItem> $children
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\Procurement\OrgPartner $orgPartner
 * @property-read OrgStock $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read PartnerShoppingListItem|null $parent
 * @property-read Organisation $partnerOrganisation
 * @property-read Stock $stock
 * @property-read Transaction|null $transaction
 * @mixin \Eloquent
 */
class PartnerShoppingListItem extends Model
{
    use InOrganisation;
    use SoftDeletes;

    protected $table = 'partner_shopping_list_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'priority'       => ShoppingListItemPriorityEnum::class,
            'state'          => ShoppingListItemStateEnum::class,
            'needed_by'      => 'date',
        ];
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function partnerOrganisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'partner_organisation_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PartnerShoppingListItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PartnerShoppingListItem::class, 'parent_id');
    }

    /**
     * Current seller price per SKO in the partner currency, as a correlated SQL subquery.
     */
    public static function pricePerSkoSql(): string
    {
        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."'
            join org_stocks sos on sos.id = phos.org_stock_id
            where sos.stock_id = partner_shopping_list_items.stock_id
                and sos.organisation_id = partner_shopping_list_items.partner_organisation_id
            limit 1)";
    }
}
