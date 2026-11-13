<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One instalment of the deposit of a purchase order, paid before the goods ship. The deposit counts as paid once
 * its instalments add up to the deposit amount.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $purchase_order_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $amount
 * @property int $currency_id
 * @property string|null $original_amount
 * @property int|null $original_currency_id
 * @property PurchaseOrderPaymentMethodEnum $method
 * @property string|null $reference
 * @property int|null $media_id
 * @property int|null $created_by
 */
class PurchaseOrderDepositPayment extends Model
{
    use InGroup;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'   => 'date',
            'method' => PurchaseOrderPaymentMethodEnum::class,
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The currency the money was sent in, when it is not the purchase order's: amount stays what it counts for on the order.
     */
    public function originalCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'original_currency_id');
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
