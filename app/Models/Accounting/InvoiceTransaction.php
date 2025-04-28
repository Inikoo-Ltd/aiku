<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Models\Catalogue\Asset;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferComponent;
use App\Models\Fulfilment\RecurringBillTransaction;
use App\Models\Helpers\Currency;
use App\Models\Helpers\InvoiceTransactionHasFeedback;
use App\Models\Helpers\TaxCategory;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $shop_id
 * @property int|null $invoice_id
 * @property int $customer_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property int|null $asset_id
 * @property int|null $historic_asset_id
 * @property int|null $family_id
 * @property int|null $department_id
 * @property int|null $order_id
 * @property int|null $transaction_id
 * @property int|null $recurring_bill_transaction_id
 * @property numeric $quantity
 * @property numeric $gross_amount
 * @property numeric $net_amount
 * @property string|null $profit_amount
 * @property int $tax_category_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property numeric|null $grp_net_amount
 * @property numeric|null $org_net_amount
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $source_alt_id to be used in no products transactions
 * @property int|null $original_invoice_transaction_id For refunds link to original invoice transaction
 * @property bool $in_process Used for refunds only
 * @property-read Asset|null $asset
 * @property-read Currency|null $currency
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceTransactionHasFeedback> $feedbackBridges
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read HistoricAsset|null $historicAsset
 * @property-read \App\Models\Accounting\Invoice|null $invoice
 * @property-read Model|\Eloquent $item
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Offer> $offer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OfferCampaign> $offerCampaign
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OfferComponent> $offerComponents
 * @property-read Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read RecurringBillTransaction|null $recurringBillTransaction
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read TaxCategory $taxCategory
 * @property-read Transaction|null $transaction
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceTransaction> $transactionRefunds
 * @method static Builder<static>|InvoiceTransaction newModelQuery()
 * @method static Builder<static>|InvoiceTransaction newQuery()
 * @method static Builder<static>|InvoiceTransaction onlyTrashed()
 * @method static Builder<static>|InvoiceTransaction query()
 * @method static Builder<static>|InvoiceTransaction withTrashed()
 * @method static Builder<static>|InvoiceTransaction withoutTrashed()
 * @mixin Eloquent
 */
class InvoiceTransaction extends Model
{
    use SoftDeletes;
    use InCustomer;

    protected $table = 'invoice_transactions';

    protected $casts = [
        'data'           => 'array',
        'date'           => 'datetime',
        'quantity'       => 'decimal:3',
        'gross_amount'   => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'grp_exchange'   => 'decimal:4',
        'org_exchange'   => 'decimal:4',
        'grp_net_amount' => 'decimal:2',
        'org_net_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recurringBillTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringBillTransaction::class);
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function feedbackBridges(): HasMany
    {
        return $this->hasMany(InvoiceTransactionHasFeedback::class);
    }

    public function offerCampaign(): BelongsToMany
    {
        return $this->belongsToMany(OfferCampaign::class, 'invoice_transaction_has_offer_components');
    }

    public function offer(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'invoice_transaction_has_offer_components');
    }

    public function offerComponents(): BelongsToMany
    {
        return $this->belongsToMany(OfferComponent::class, 'invoice_transaction_has_offer_components');
    }

    public function transactionRefunds(): HasMany
    {
        return $this->hasMany(InvoiceTransaction::class, 'original_invoice_transaction_id');
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }
}
