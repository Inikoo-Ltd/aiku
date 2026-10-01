<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email from a shop's mailbox history, kept as text: what customers wrote to customer service
 * and what we answered, before the inbox read the mailbox. Shown on the customer's page with
 * their other conversations and read to learn what customer service answers; it never becomes a
 * chat conversation. The part of the mail quoted from earlier ones is left out.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $customer_id
 * @property string $gmail_message_id
 * @property string $gmail_thread_id
 * @property bool $is_outbound
 * @property string|null $from_address
 * @property array|null $to_addresses
 * @property string|null $counterpart_address
 * @property string|null $subject
 * @property string|null $text
 * @property \Illuminate\Support\Carbon $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Shop $shop
 */
class EmailArchiveMessage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_outbound'  => 'boolean',
        'to_addresses' => 'array',
        'sent_at'      => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
