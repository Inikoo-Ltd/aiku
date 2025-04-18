<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:35:59 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\HistoricAsset;
use App\Models\CRM\Customer;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferComponent;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Catalogue\Shop;
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
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $in_warehouse_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property TransactionStateEnum $state
 * @property TransactionStatusEnum $status
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
 * @property \Illuminate\Support\Carbon|null $out_of_stock_in_basket_at
 * @property string|null $fail_status
 * @property numeric $gross_amount net amount before discounts
 * @property numeric $net_amount
 * @property numeric|null $grp_net_amount
 * @property numeric|null $org_net_amount
 * @property int $tax_category_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $source_alt_id to be used in no products transactions
 * @property-read Asset|null $asset
 * @property-read Customer $customer
 * @property-read DeliveryNoteItem|null $deliveryNoteItem
 * @property-read Collection<int, DeliveryNoteItem> $deliveryNoteItems
 * @property-read Collection<int, Feedback> $feedbacks
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read HistoricAsset|null $historicAsset
 * @property-read Invoice|null $invoice
 * @property-read Model|\Eloquent $item
 * @property-read Collection<int, Offer> $offer
 * @property-read Collection<int, OfferCampaign> $offerCampaign
 * @property-read Collection<int, OfferComponent> $offerComponents
 * @property-read \App\Models\Ordering\Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Shop $shop
 * @method static \Database\Factories\Ordering\TransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction onlyTrashed()
 * @method static Builder<static>|Transaction query()
 * @method static Builder<static>|Transaction withTrashed()
 * @method static Builder<static>|Transaction withoutTrashed()
 * @mixin Eloquent
 */
class Transaction extends Model
{
    use SoftDeletes;
    use HasFactory;
    use InCustomer;

    protected $table = 'transactions';

    protected $casts = [
        'quantity'                  => 'decimal:3',
        'data'                      => 'array',
        'state'                     => TransactionStateEnum::class,
        'status'                    => TransactionStatusEnum::class,
        'out_of_stock_in_basket'    => 'boolean',
        'date'                      => 'datetime',
        'submitted_at'              => 'datetime',
        'in_warehouse_at'           => 'datetime',
        'settled_at'                => 'datetime',
        'out_of_stock_in_basket_at' => 'datetime',
        'fetched_at'                => 'datetime',
        'last_fetched_at'           => 'datetime',
        'quantity_ordered'          => 'decimal:3',
        'quantity_bonus'            => 'decimal:3',
        'quantity_dispatched'       => 'decimal:3',
        'quantity_fail'             => 'decimal:3',
        'quantity_cancelled'        => 'decimal:3',
        'gross_amount'              => 'decimal:2',
        'net_amount'                => 'decimal:2',
        'grp_net_amount'            => 'decimal:2',
        'org_net_amount'            => 'decimal:2',
        'grp_exchange'              => 'decimal:4',
        'org_exchange'              => 'decimal:4',


    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];


    public function item(): MorphTo
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

    public function offerCampaign(): BelongsToMany
    {
        return $this->belongsToMany(OfferCampaign::class, 'transaction_has_offer_components');
    }

    public function offer(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'transaction_has_offer_components');
    }

    public function offerComponents(): BelongsToMany
    {
        return $this->belongsToMany(OfferComponent::class, 'transaction_has_offer_components');
    }

    public function deliveryNoteItem(): HasOne
    {
        return $this->hasOne(DeliveryNoteItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

}
