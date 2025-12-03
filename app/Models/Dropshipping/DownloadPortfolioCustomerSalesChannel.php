<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:08:13 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $customer_sales_channel_id
 * @property string|null $file_name
 * @property string|null $file_path
 * @property string|null $download_url
 * @property float|null $file_size
 * @property string|null $size_unit
 * @property string|null $file_start_create_at
 * @property string|null $file_completed_create_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel $customerSalesChannel
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DownloadPortfolioCustomerSalesChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DownloadPortfolioCustomerSalesChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DownloadPortfolioCustomerSalesChannel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DownloadPortfolioCustomerSalesChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DownloadPortfolioCustomerSalesChannel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DownloadPortfolioCustomerSalesChannel withoutTrashed()
 * @mixin \Eloquent
 */
class DownloadPortfolioCustomerSalesChannel extends Model
{
    use SoftDeletes;
    protected $table = 'download_portfolio_customer_sales_channel';
    protected $fillable = [
        'customer_sales_channel_id',
        'file_name',
        'file_path',
        'download_url',
        'file_size',
        'size_unit',
        'file_start_create_at',
        'file_completed_create_at',
    ];

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }

}
