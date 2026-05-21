<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:35:59 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\HistoricAsset;
use App\Models\CRM\Customer;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferAllowance;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Catalogue\Shop;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Helpers\Feedback;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Ordering\Transaction
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int|null $customer_client_id
 * @property int|null $order_id
 * @property int|null $invoice_id
 * @property string $date
 * @property string|null $submitted_at
 * @property string|null $in_warehouse_at
 * @property string|null $settled_at
 * @property string $state
 * @property string $status
 * @property string|null $model_type
 * @property int|null $model_id
 * @property int|null $asset_id
 * @property int|null $historic_asset_id
 * @property numeric|null $quantity_ordered
 * @property numeric|null $quantity_bonus
 * @property numeric|null $quantity_dispatched
 * @property numeric|null $quantity_fail
 * @property numeric|null $quantity_cancelled
 * @property bool $out_of_stock_in_basket
 * @property string|null $out_of_stock_in_basket_at
 * @property string|null $fail_status
 * @property numeric $gross_amount net amount before discounts
 * @property numeric $net_amount
 * @property numeric|null $grp_net_amount
 * @property numeric|null $org_net_amount
 * @property int $tax_category_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property string $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $source_alt_id to be used in no product transactions
 * @property int|null $estimated_weight grams
 * @property string|null $platform_transaction_id
 * @property numeric|null $quantity_picked
 * @property numeric $submitted_quantity_ordered
 * @property array<array-key, mixed>|null $offers_data
 * @property string|null $dispatched_at
 * @property string|null $cancelled_at
 * @property int|null $family_id
 * @property int|null $department_id
 * @property int|null $sub_department_id
 * @property numeric|null $discretionary_offer
 * @property string|null $discretionary_offer_label
 * @property string|null $label
 * @property string|null $marketplace_id
 * @property numeric $commission_amount
 * @property numeric $profit_amount
 * @property numeric|null $margin
 * @property bool $is_cut_view
 * @property bool $is_gift
 * @property numeric|null $submitted_gross_amount
 * @property numeric|null $submitted_net_amount
 * @property float $submitted_discount_factor
 * @property float $current_discount_factor
 * @property-read Asset|null $asset
 * @property-read Customer|null $customer
 * @property-read Collection<int, DeliveryNoteItem> $deliveryNoteItems
 * @property-read Collection<int, Feedback> $feedbacks
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read HistoricAsset|null $historicAsset
 * @property-read InvoiceTransaction|null $invoiceTransaction
 * @property-read Model|\Eloquent|null $model
 * @property-read Collection<int, OfferAllowance> $offerAllowances
 * @property-read Collection<int, OfferCampaign> $offerCampaigns
 * @property-read Collection<int, Offer> $offers
 * @property-read \App\Models\Ordering\Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Collection<int, ReturnDeliveryNoteItem> $returnDeliveryNoteItems
 * @property-read Shop|null $shop
 * @method static \Database\Factories\Ordering\TransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction onlyTrashed()
 * @method static Builder<static>|Transaction query()
 * @method static Builder<static>|Transaction withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Transaction withoutTrashed()
 * @mixin Eloquent
 */
class Transaction extends Model
{
    use SoftDeletes;
    use HasFactory;
    use InCustomer;

    protected $table = 'transactions';

    protected $attributes = [
        'data'        => '{}',
        'offers_data' => '{}',
    ];

    protected $casts = [
        'offers_data' => 'array',
    ];

    protected $guarded = [];


    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveryNoteItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function historicAsset(): BelongsTo
    {
        return $this->belongsTo(HistoricAsset::class);
    }

    public function getHistoricAssetWithTrashed(): HistoricAsset
    {
        return HistoricAsset::withTrashed()->where('id', $this->historic_asset_id)->first();
    }

    public function feedbacks(): MorphToMany
    {
        return $this->morphToMany(Feedback::class, 'model', 'model_has_feedbacks');
    }

    public function offerCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(OfferCampaign::class, 'transaction_has_offer_allowances');
    }

    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'transaction_has_offer_allowances');
    }

    public function offerAllowances(): BelongsToMany
    {
        return $this->belongsToMany(OfferAllowance::class, 'transaction_has_offer_allowances');
    }

    public function invoiceTransaction(): HasOne
    {
        return $this->hasOne(InvoiceTransaction::class);
    }

    public function returnDeliveryNoteItems(): HasMany
    {
        return $this->hasMany(ReturnDeliveryNoteItem::class, 'original_transaction_id');
    }

}
