<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:35:59 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Ordering\UpcomingTransaction
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $order_id
 * @property int|null $transaction_id
 * @property numeric|null $quantity
 * @property string|null $notes
 * @property UpcomingTransactionTypeEnum $type
 * @property UpcomingTransactionStateEnum $state
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Product $product
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Transaction|null $transaction
 * @method static Builder<static>|UpcomingTransaction newModelQuery()
 * @method static Builder<static>|UpcomingTransaction newQuery()
 * @method static Builder<static>|UpcomingTransaction query()
 * @mixin Eloquent
 */
class UpcomingTransaction extends Model
{
    use InCustomer;

    protected $table = 'upcoming_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'  => UpcomingTransactionTypeEnum::class,
            'state' => UpcomingTransactionStateEnum::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
