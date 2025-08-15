<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Dec 2024 23:02:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $customer_id
 * @property bool $is_suspended Suspend communication with customer because of spam or bounces
 * @property string|null $suspended_at
 * @property string|null $suspended_cause
 * @property bool $is_subscribed_to_newsletter
 * @property bool $is_subscribed_to_marketing
 * @property bool $is_subscribed_to_abandoned_cart
 * @property bool $is_subscribed_to_reorder_reminder
 * @property bool $is_subscribed_to_basket_low_stock
 * @property bool $is_subscribed_to_basket_reminder_1
 * @property bool $is_subscribed_to_basket_reminder_2
 * @property bool $is_subscribed_to_basket_reminder_3
 * @property string|null $newsletter_unsubscribed_at
 * @property string|null $newsletter_unsubscribed_author_type Customer|User
 * @property string|null $newsletter_unsubscribed_author_id
 * @property string|null $newsletter_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $newsletter_unsubscribed_origin_id
 * @property string|null $marketing_unsubscribed_at
 * @property string|null $marketing_unsubscribed_author_type Customer|User
 * @property string|null $marketing_unsubscribed_author_id
 * @property string|null $marketing_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $marketing_unsubscribed_origin_id
 * @property string|null $abandoned_cart_unsubscribed_at
 * @property string|null $abandoned_cart_unsubscribed_author_type Customer|User
 * @property string|null $abandoned_cart_unsubscribed_author_id
 * @property string|null $abandoned_cart_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $abandoned_cart_unsubscribed_origin_id
 * @property string|null $reorder_reminder_unsubscribed_at
 * @property string|null $reorder_reminder_unsubscribed_author_type Customer|User
 * @property string|null $reorder_reminder_unsubscribed_author_id
 * @property string|null $reorder_reminder_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $reorder_reminder_unsubscribed_origin_id
 * @property string|null $basket_low_stock_unsubscribed_at
 * @property string|null $basket_low_stock_unsubscribed_author_type Customer|User
 * @property string|null $basket_low_stock_unsubscribed_author_id
 * @property string|null $basket_low_stock_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $basket_low_stock_unsubscribed_origin_id
 * @property string|null $basket_reminder_1_unsubscribed_at
 * @property string|null $basket_reminder_1_unsubscribed_author_type Customer|User
 * @property string|null $basket_reminder_1_unsubscribed_author_id
 * @property string|null $basket_reminder_1_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $basket_reminder_1_unsubscribed_origin_id
 * @property string|null $basket_reminder_2_unsubscribed_at
 * @property string|null $basket_reminder_2_unsubscribed_author_type Customer|User
 * @property string|null $basket_reminder_2_unsubscribed_author_id
 * @property string|null $basket_reminder_2_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $basket_reminder_2_unsubscribed_origin_id
 * @property string|null $basket_reminder_3_unsubscribed_at
 * @property string|null $basket_reminder_3_unsubscribed_author_type Customer|User
 * @property string|null $basket_reminder_3_unsubscribed_author_id
 * @property string|null $basket_reminder_3_unsubscribed_origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $basket_reminder_3_unsubscribed_origin_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_traffic_sources
 * @property-read \App\Models\CRM\Customer $customer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerComms newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerComms newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerComms query()
 * @mixin \Eloquent
 */
class CustomerComms extends Model
{
    protected $table = 'customer_comms';
    protected $guarded = [];


    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

}
