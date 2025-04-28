<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:22 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_post_rooms
 * @property int $number_org_post_rooms
 * @property int $number_outboxes
 * @property int $number_outboxes_type_basket_low_stock
 * @property int $number_outboxes_type_basket_push
 * @property int $number_outboxes_type_new_customer_push
 * @property int $number_outboxes_type_new_customer
 * @property int $number_outboxes_type_delivery_note_dispatched
 * @property int $number_outboxes_type_delivery_note_undispatched
 * @property int $number_outboxes_type_invoice_deleted
 * @property int $number_outboxes_type_new_order
 * @property int $number_outboxes_type_abandoned_cart
 * @property int $number_outboxes_type_delivery_confirmation
 * @property int $number_outboxes_type_reorder_reminder
 * @property int $number_outboxes_type_marketing
 * @property int $number_outboxes_type_newsletter
 * @property int $number_outboxes_type_oos_notification
 * @property int $number_outboxes_type_order_confirmation
 * @property int $number_outboxes_type_password_reminder
 * @property int $number_outboxes_type_registration
 * @property int $number_outboxes_type_registration_approved
 * @property int $number_outboxes_type_registration_rejected
 * @property int $number_outboxes_type_rental_agreement
 * @property int $number_outboxes_type_pallet_delivery_processed
 * @property int $number_outboxes_type_pallet_return_dispatched
 * @property int $number_outboxes_type_invite
 * @property int $number_outboxes_type_test
 * @property int $number_outboxes_state_in_process
 * @property int $number_outboxes_state_active
 * @property int $number_outboxes_state_suspended
 * @property int $number_outbox_subscribers
 * @property int $number_mailshots
 * @property int $number_mailshots_state_in_process
 * @property int $number_mailshots_state_ready
 * @property int $number_mailshots_state_scheduled
 * @property int $number_mailshots_state_sending
 * @property int $number_mailshots_state_sent
 * @property int $number_mailshots_state_cancelled
 * @property int $number_mailshots_state_stopped
 * @property int $number_mailshots_type_newsletter
 * @property int $number_mailshots_type_marketing
 * @property int $number_mailshots_type_invite
 * @property int $number_mailshots_type_abandoned_cart
 * @property int $number_bulk_runs
 * @property int $number_bulk_runs_state_scheduled
 * @property int $number_bulk_runs_state_sending
 * @property int $number_bulk_runs_state_sent
 * @property int $number_bulk_runs_state_cancelled
 * @property int $number_bulk_runs_state_stopped
 * @property int $number_dispatched_emails
 * @property int $number_dispatched_emails_state_ready
 * @property int $number_dispatched_emails_state_sent_to_provider
 * @property int $number_dispatched_emails_state_error
 * @property int $number_dispatched_emails_state_rejected_by_provider
 * @property int $number_dispatched_emails_state_sent
 * @property int $number_dispatched_emails_state_delivered
 * @property int $number_dispatched_emails_state_hard_bounce
 * @property int $number_dispatched_emails_state_soft_bounce
 * @property int $number_dispatched_emails_state_opened
 * @property int $number_dispatched_emails_state_clicked
 * @property int $number_dispatched_emails_state_spam
 * @property int $number_dispatched_emails_state_unsubscribed
 * @property int $number_provoked_unsubscribe
 * @property int $number_email_addresses
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_outboxes_type_send_invoice_to_customer
 * @property int $number_dispatched_emails_state_delay
 * @property int $number_outboxes_type_new_pallet_return_from_customer
 * @property int $number_outboxes_type_new_pallet_delivery_from_customer
 * @property int $number_outboxes_type_pallet_delivery_deleted
 * @property int $number_outboxes_type_pallet_return_deleted
 * @property int $number_outboxes_type_pallet_deleted
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCommsStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCommsStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCommsStats query()
 * @mixin \Eloquent
 */
class GroupCommsStats extends Model
{
    protected $table = 'group_comms_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
