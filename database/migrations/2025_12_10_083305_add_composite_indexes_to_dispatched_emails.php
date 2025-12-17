<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('dispatched_emails', function (Blueprint $table) {
            // Critical: Foreign keys used in WHERE clauses
            $table->index('organisation_id', 'dispatched_emails_organisation_id_idx');
            $table->index('shop_id', 'dispatched_emails_shop_id_idx');
            $table->index('outbox_id', 'dispatched_emails_outbox_id_idx');
            $table->index('post_room_id', 'dispatched_emails_post_room_id_idx');
            $table->index('email_address_id', 'dispatched_emails_email_address_id_idx');

            // Important: Sorting column
            $table->index('sent_at', 'dispatched_emails_sent_at_idx');

            // Composite indexes for parent polymorphic relationship
            $table->index(['parent_type', 'parent_id'], 'dispatched_emails_parent_idx');

            // Composite indexes for recipient polymorphic relationship
            $table->index(['recipient_type', 'recipient_id'], 'dispatched_emails_recipient_idx');

            // Composite indexes for common query patterns
            // For Customer queries: filter by recipient + sort by sent_at
            $table->index(
                ['recipient_type', 'recipient_id', 'sent_at'],
                'dispatched_emails_recipient_sent_at_idx'
            );

            // For PostRoom queries: filter by post_room + sort by sent_at
            $table->index(
                ['post_room_id', 'sent_at'],
                'dispatched_emails_post_room_sent_at_idx'
            );

            // For Outbox queries: filter by outbox + sort by sent_at
            $table->index(
                ['outbox_id', 'sent_at'],
                'dispatched_emails_outbox_sent_at_idx'
            );

            // For Mailshot queries: filter by parent + sort by sent_at
            $table->index(
                ['parent_type', 'parent_id', 'sent_at'],
                'dispatched_emails_parent_sent_at_idx'
            );

            // For Organisation queries: filter by org + sort by sent_at
            $table->index(
                ['organisation_id', 'sent_at'],
                'dispatched_emails_org_sent_at_idx'
            );

            // For Group queries: filter by group + sort by sent_at
            $table->index(
                ['group_id', 'sent_at'],
                'dispatched_emails_group_sent_at_idx'
            );

            // For Shop queries: filter by shop + sort by sent_at
            $table->index(
                ['shop_id', 'sent_at'],
                'dispatched_emails_shop_sent_at_idx'
            );

            // Optional: Index for state if you frequently filter by it
            $table->index('state', 'dispatched_emails_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex('dispatched_emails_organisation_id_idx');
            $table->dropIndex('dispatched_emails_shop_id_idx');
            $table->dropIndex('dispatched_emails_outbox_id_idx');
            $table->dropIndex('dispatched_emails_post_room_id_idx');
            $table->dropIndex('dispatched_emails_email_address_id_idx');
            $table->dropIndex('dispatched_emails_sent_at_idx');
            $table->dropIndex('dispatched_emails_parent_idx');
            $table->dropIndex('dispatched_emails_recipient_idx');
            $table->dropIndex('dispatched_emails_recipient_sent_at_idx');
            $table->dropIndex('dispatched_emails_post_room_sent_at_idx');
            $table->dropIndex('dispatched_emails_outbox_sent_at_idx');
            $table->dropIndex('dispatched_emails_parent_sent_at_idx');
            $table->dropIndex('dispatched_emails_org_sent_at_idx');
            $table->dropIndex('dispatched_emails_group_sent_at_idx');
            $table->dropIndex('dispatched_emails_shop_sent_at_idx');
            $table->dropIndex('dispatched_emails_state_idx');
        });
    }
};
