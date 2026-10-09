<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Events\BroadcastProductionQueuesChanged;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Transaction;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
 * @property bool $suggested_by_hub
 * @property string|null $dismiss_reason
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 * @property int|null $dismissed_by_user_id
 * @property int|null $transaction_id
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $poked_at
 * @property int|null $poked_by_user_id
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

    protected static function booted(): void
    {
        $announce = function (self $item) {
            $sellerId = $item->partner_organisation_id ?? $item->organisation_id;
            if ($sellerId) {
                rescue(fn () => BroadcastProductionQueuesChanged::dispatch($sellerId));
            }
        };

        static::saved($announce);
        static::deleted($announce);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'priority'       => ShoppingListItemPriorityEnum::class,
            'state'          => ShoppingListItemStateEnum::class,
            'needed_by'      => 'date',
            'expiry_date'    => 'date',
            'suggested_by_hub' => 'boolean',
            'dismissed_at'     => 'datetime',
            'poked_at'         => 'datetime',
        ];
    }

    /**
     * Partner lines waiting for shelf stock queue per stock, most urgent first, then the one needed
     * soonest, then the oldest. A line is served only once every line ahead of it is.
     */
    private static function queueRankSql(string $items): string
    {
        return "row(case $items.priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end, coalesce($items.needed_by, '9999-12-31'::date), $items.created_at, $items.id)";
    }

    /** What this line and every line queued ahead of it ask for together. */
    public static function queuedThroughSql(string $items = 'partner_shopping_list_items'): string
    {
        return "(select coalesce(sum(queued.quantity), 0) from partner_shopping_list_items queued
            where queued.partner_organisation_id = $items.partner_organisation_id
                and queued.stock_id = $items.stock_id
                and queued.state = 'open'
                and queued.pre_picked_at is null
                and queued.job_order_id is null
                and queued.preparing_at is null
                and queued.deleted_at is null
                and ".self::queueRankSql('queued').' <= '.self::queueRankSql($items).')';
    }

    /** Shelf stock of the seller nobody has been promised yet. */
    public static function freeStockSql(string $items = 'partner_shopping_list_items'): string
    {
        return "greatest(
            coalesce((select free.quantity_available from org_stocks free where free.organisation_id = $items.partner_organisation_id and free.stock_id = $items.stock_id limit 1), 0)
            - ".self::promisedNotStagedSql("$items.partner_organisation_id", "$items.stock_id").',
            0)';
    }

    /**
     * Pre-picked stock still on the shelf: what each buyer was promised minus what already sits in
     * its bay. Stock in a bay is goods out and has already left quantity_available, while its line
     * stays open until the partner order is made.
     */
    public static function promisedNotStagedSql(string $seller, string $stock): string
    {
        return "coalesce((select sum(greatest(0, promised.quantity - coalesce((
                select sum(staged.quantity) from location_org_stocks staged
                where staged.org_stock_id = (select seller_stock.id from org_stocks seller_stock where seller_stock.organisation_id = promised.partner_organisation_id and seller_stock.stock_id = promised.stock_id limit 1)
                    and staged.location_id = (select ".OrgPartner::bayIdSql('to_partner', 'bay_stock.is_cosmetic')." from org_partners to_partner join stocks bay_stock on bay_stock.id = promised.stock_id
                        where to_partner.organisation_id = promised.partner_organisation_id and to_partner.partner_id = promised.organisation_id limit 1)
            ), 0)))
            from (select picked.organisation_id, picked.partner_organisation_id, picked.stock_id, sum(picked.quantity) as quantity from partner_shopping_list_items picked
                where picked.partner_organisation_id = $seller
                    and picked.stock_id = $stock
                    and picked.state = 'open'
                    and picked.pre_picked_at is not null
                    and picked.deleted_at is null
                group by picked.organisation_id, picked.partner_organisation_id, picked.stock_id) promised), 0)";
    }

    /** What the free stock cannot cover of this line once the lines ahead of it are served. */
    public static function shortfallSql(string $items = 'partner_shopping_list_items'): string
    {
        return "greatest(0, least($items.quantity, ".self::queuedThroughSql($items).' - '.self::freeStockSql($items).'))';
    }

    /**
     * Picking part from stock and making the rest splits a sent line; the buyer still counts it as one.
     *
     * @param array<int, string> $states
     */
    public static function whereNotSplitPiece(Builder|EloquentBuilder $query, array $states, string $items = 'partner_shopping_list_items'): Builder|EloquentBuilder
    {
        return $query->whereNotExists(function ($query) use ($states, $items) {
            $query->from('partner_shopping_list_items as split_from')
                ->whereColumn('split_from.id', "$items.parent_id")
                ->whereColumn('split_from.org_partner_id', "$items.org_partner_id")
                ->whereIn('split_from.state', $states)
                ->whereNull('split_from.deleted_at');
        });
    }

    public static function whereRoutedToProduction(Builder $query, string $items = 'partner_shopping_list_items', string $orgStocks = 'org_stocks'): Builder
    {
        return $query->whereNull("$items.pre_picked_at")
            ->where(function ($query) use ($items) {
                $query->whereNotNull("$items.job_order_id")
                    ->orWhereNotNull("$items.preparing_at")
                    ->orWhereNull("$items.partner_organisation_id")
                    ->orWhereRaw(self::shortfallSql($items)." >= $items.quantity");
            })
            ->where(function ($query) use ($items, $orgStocks) {
                $query->whereNotNull("$items.job_order_id")
                    ->orWhereNull("$orgStocks.state")
                    ->orWhereNotIn("$orgStocks.state", [OrgStockStateEnum::DISCONTINUING->value, OrgStockStateEnum::DISCONTINUED->value]);
            });
    }

    public static function openRestockRequestsFor(OrgStock $orgStock): EloquentBuilder
    {
        return static::query()
            ->where('organisation_id', $orgStock->organisation_id)
            ->where('stock_id', $orgStock->stock_id)
            ->whereNull('partner_organisation_id')
            ->whereNull('transaction_id')
            ->whereNull('job_order_id')
            ->whereNull('pre_picked_at')
            ->where('state', ShoppingListItemStateEnum::OPEN);
    }

    public static function draftPartnerLineFor(int $orgPartnerId, int $orgStockId): EloquentBuilder
    {
        return static::query()
            ->where('org_partner_id', $orgPartnerId)
            ->where('org_stock_id', $orgStockId)
            ->where('state', ShoppingListItemStateEnum::DRAFT);
    }

    public static function openPartnerLineFor(int $orgPartnerId, int $orgStockId): EloquentBuilder
    {
        return static::query()
            ->where('org_partner_id', $orgPartnerId)
            ->where('org_stock_id', $orgStockId)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('job_order_id')
            ->whereNull('pre_picked_at');
    }

    /**
     * Sent to the partner but not started: not picked from stock, not queued to be made, not on a job order or a partner order.
     * The buyer can still change or withdraw it.
     */
    public function isWaitingForPartner(): bool
    {
        return $this->state === ShoppingListItemStateEnum::OPEN
            && !$this->job_order_id
            && !$this->transaction_id
            && !$this->pre_picked_at
            && !$this->preparing_at;
    }

    /** Sent and not yet delivered: the buyer can still hurry the partner along. */
    public function canBePoked(): bool
    {
        return $this->state === ShoppingListItemStateEnum::OPEN && !$this->transaction_id;
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Production\JobOrder::class);
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
     *
     * @param  array<int, int>  $shopIds  the seller's shops in order of preference (GetPartnerSellingShopIds)
     */
    public static function pricePerSkoSql(array $shopIds): string
    {
        return PartnerSkoPrice::pricePerSkoSql(
            "(select sos.id from org_stocks sos
                where sos.stock_id = partner_shopping_list_items.stock_id
                    and sos.organisation_id = partner_shopping_list_items.partner_organisation_id
                limit 1)",
            $shopIds,
            'partner_shopping_list_items.org_partner_id'
        );
    }
}
