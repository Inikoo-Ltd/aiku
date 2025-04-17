<?php

/*
 * author Arya Permana - Kirin
 * created on 28-10-2024-11h-11m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $invoice_category_id
 * @property int $number_invoices
 * @property int $number_invoices_type_invoice
 * @property int $number_invoices_type_refund
 * @property string|null $last_invoiced_at
 * @property int $number_invoiced_customers
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_deleted_invoices
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryStats query()
 * @mixin \Eloquent
 */
class InvoiceCategoryStats extends Model
{
    protected $table = 'invoice_category_stats';


}
