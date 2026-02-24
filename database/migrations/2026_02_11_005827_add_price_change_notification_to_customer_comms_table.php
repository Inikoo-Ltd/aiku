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
        Schema::table('customer_comms', function (Blueprint $table) {
            $outboxField = 'price_change_notification';

            // Add subscription boolean field
            $table->boolean('is_subscribed_to_' . $outboxField)->default(true)->index();

            // Add unsubscribe tracking fields
            $table->dateTimeTz($outboxField . '_unsubscribed_at')->nullable()->index();
            $table->string($outboxField . '_unsubscribed_author_type')->nullable()->comment('Customer|User');
            $table->string($outboxField . '_unsubscribed_author_id')->nullable();
            $table->string($outboxField . '_unsubscribed_origin_type')->nullable()->comment('EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)');
            $table->string($outboxField . '_unsubscribed_origin_id')->nullable();

            // Add indexes for author and origin tracking
            $table->index([$outboxField . '_unsubscribed_author_type', $outboxField . '_unsubscribed_author_id']);
            $table->index([$outboxField . '_unsubscribed_origin_type', $outboxField . '_unsubscribed_origin_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $outboxField = 'price_change_notification';

            // Drop indexes first
            $table->dropIndex([$outboxField . '_unsubscribed_author_type', $outboxField . '_unsubscribed_author_id']);
            $table->dropIndex([$outboxField . '_unsubscribed_origin_type', $outboxField . '_unsubscribed_origin_id']);

            // Drop columns
            $table->dropColumn('is_subscribed_to_' . $outboxField);
            $table->dropColumn($outboxField . '_unsubscribed_at');
            $table->dropColumn($outboxField . '_unsubscribed_author_type');
            $table->dropColumn($outboxField . '_unsubscribed_author_id');
            $table->dropColumn($outboxField . '_unsubscribed_origin_type');
            $table->dropColumn($outboxField . '_unsubscribed_origin_id');
        });
    }
};
