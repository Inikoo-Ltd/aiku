<?php

/*
 * author Arya Permana - Kirin
 * created on 29-11-2024-16h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int|null $org_post_room_id
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_outboxes_type_send_invoice_to_customer
 * @property int $number_dispatched_emails_state_delay
 * @property int $number_outboxes_type_new_pallet_return_from_customer
 * @property int $number_outboxes_type_new_pallet_delivery_from_customer
 * @property int $number_outboxes_type_pallet_delivery_deleted
 * @property int $number_outboxes_type_pallet_return_deleted
 * @property int $number_outboxes_type_pallet_deleted
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPostRoomStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPostRoomStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPostRoomStats query()
 * @mixin \Eloquent
 */
class OrgPostRoomStats extends Model
{
    protected $table = 'org_post_room_stats';

    protected $guarded = [];


}
