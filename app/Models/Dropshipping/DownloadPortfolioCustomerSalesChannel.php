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
 * @property string $filename
 * @property string $download_url
 * @property float $file_size
 * @property string $size_unit
 * @property \DateTime $file_start_create_at
 * @property \DateTime $file_completed_create_at
 *
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
